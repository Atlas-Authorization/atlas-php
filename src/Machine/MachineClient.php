<?php

declare(strict_types=1);

namespace Atlas\Machine;

use Atlas\Exception\ConfigException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Machine identity for a daemon, worker or agent: enrol once with a single-use credential, then mint short-lived, audience-bound tokens by
 * proving possession of the enrolled Ed25519 key (challenge, signature, token). Tokens are cached until `refreshSkewSeconds` before they
 * expire. Pass `dpopKey: true` (or a PKCS#8 PEM) to bind tokens to a DPoP key and sign request proofs with {@see dpopProof()}.
 *
 * Needs `ext-sodium`.
 *
 *     $machine = new MachineClient('https://api.example.com', new FileMachineStore('/var/lib/app/machine.json'));
 *     $machine->enroll(credential: $enrolmentToken, name: 'billing-worker');
 *     $token = $machine->getToken(['audience' => 'https://billing.internal', 'scopes' => ['invoices:read']]);
 */
final class MachineClient
{
    private readonly string $issuer;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requests;
    private readonly StreamFactoryInterface $streams;
    /** @var callable():int */
    private $now;
    private readonly ?string $dpopKey;
    /** @var array<string,array<string,mixed>> */
    private array $cache = [];

    /**
     * @param string|bool|null      $dpopKey `true` generates an Ed25519 DPoP key, a string is a PKCS#8 PEM, null/false turns DPoP off
     * @param (callable():int)|null $now     epoch milliseconds (tests)
     */
    public function __construct(
        string $issuer,
        private readonly MachineStore $store,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly int $refreshSkewSeconds = 60,
        string|bool|null $dpopKey = null,
        ?callable $now = null,
    ) {
        $this->issuer = rtrim($issuer, '/');
        $this->now = $now ?? static fn (): int => (int) (microtime(true) * 1000);
        if ($httpClient === null || $requestFactory === null || $streamFactory === null) {
            if (!class_exists(\GuzzleHttp\Client::class)) {
                throw new ConfigException('Pass a PSR-18 client and PSR-17 factories, or install guzzlehttp/guzzle.');
            }
            $factory = new \GuzzleHttp\Psr7\HttpFactory();
            $httpClient ??= new \GuzzleHttp\Client(['timeout' => 15, 'connect_timeout' => 5, 'http_errors' => false]);
            $requestFactory ??= $factory;
            $streamFactory ??= $factory;
        }
        $this->client = $httpClient;
        $this->requests = $requestFactory;
        $this->streams = $streamFactory;
        $this->dpopKey = $dpopKey === true ? self::pem(sodium_crypto_sign_keypair(), true) : (is_string($dpopKey) ? $dpopKey : null);
    }

    /**
     * Enrol this machine: generates an Ed25519 key, registers its public half and saves the identity in the store.
     *
     * @param array<string,mixed> $metadata
     *
     * @return array{machineId:string,status:string,approvalRequired:bool}
     */
    public function enroll(string $credential, string $name, array $metadata = [], ?string $deviceKey = null): array
    {
        $pair = sodium_crypto_sign_keypair();
        $private = self::pem($pair, true);
        $public = self::pem($pair, false);
        $res = $this->post('/v1/machines/enroll', array_filter([
            'enrolment_token' => trim($credential),
            'name' => $name,
            'public_key_pem' => $public,
            'metadata' => $metadata === [] ? null : $metadata,
            'device_key' => $deviceKey,
        ], static fn ($v): bool => $v !== null));
        $this->store->save(['issuer' => $this->issuer, 'machine_id' => $res['id'], 'private_key_pem' => $private, 'public_key_pem' => $public, 'enrolled_at' => $res['enrolled_at'] ?? ($this->now)()]);
        $this->cache = [];

        return ['machineId' => (string) $res['id'], 'status' => (string) ($res['status'] ?? ''), 'approvalRequired' => (bool) ($res['approval_required'] ?? false)];
    }

    public function isEnrolled(): bool
    {
        return $this->identity() !== null;
    }

    public function machineId(): ?string
    {
        return $this->identity()['machine_id'] ?? null;
    }

    /**
     * A token for the request, from the cache when one is still fresh.
     *
     * @param array{audience?:string,scopes?:list<string>,scopePresets?:list<string>,organizationId?:string} $request
     *
     * @return array{token:string,tokenType:string,expiresAt:int,audience:?string,scope:?string,constraints:?array<string,mixed>,organizationId:?string}
     */
    public function getToken(array $request = []): array
    {
        $scopes = $request['scopes'] ?? [];
        $presets = $request['scopePresets'] ?? [];
        sort($scopes);
        sort($presets);
        $key = json_encode([$request['audience'] ?? '', $scopes, $presets, $request['organizationId'] ?? '']);
        $hit = $this->cache[$key] ?? null;
        if ($hit !== null && $hit['expiresAt'] - $this->refreshSkewSeconds * 1000 > ($this->now)()) {
            return $hit;
        }

        return $this->cache[$key] = $this->mint($request);
    }

    /**
     * Sign a DPoP proof (RFC 9449) for one request.
     *
     * @throws MachineClientException when the client was built without a DPoP key
     */
    public function dpopProof(string $method, string $url, ?string $token = null, ?string $nonce = null): string
    {
        if ($this->dpopKey === null) {
            throw new MachineClientException(0, 'dpop_not_configured', 'Create the client with the dpopKey option to sign DPoP proofs.');
        }
        [$seed, $pub] = self::keyOf($this->dpopKey);
        $u = parse_url($url);
        $b = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $header = ['typ' => 'dpop+jwt', 'alg' => 'EdDSA', 'jwk' => ['kty' => 'OKP', 'crv' => 'Ed25519', 'x' => $b($pub)]];
        $payload = ['jti' => bin2hex(random_bytes(16)), 'htm' => strtoupper($method), 'htu' => ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : '') . ($u['path'] ?? '/'), 'iat' => intdiv(($this->now)(), 1000)]
            + ($token !== null ? ['ath' => $b(hash('sha256', $token, true))] : [])
            + ($nonce !== null ? ['nonce' => $nonce] : []);
        $input = $b(json_encode($header, JSON_THROW_ON_ERROR)) . '.' . $b(json_encode($payload, JSON_THROW_ON_ERROR));
        $secret = sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($seed));

        return $input . '.' . $b(sodium_crypto_sign_detached($input, $secret));
    }

    /** The RFC 7638 thumbprint of the DPoP key (the `cnf.jkt` of a token minted with its proofs), or null without one. */
    public function dpopThumbprint(): ?string
    {
        if ($this->dpopKey === null) {
            return null;
        }
        $x = rtrim(strtr(base64_encode(self::keyOf($this->dpopKey)[1]), '+/', '-_'), '=');

        return rtrim(strtr(base64_encode(hash('sha256', '{"crv":"Ed25519","kty":"OKP","x":"' . $x . '"}', true)), '+/', '-_'), '=');
    }

    public function clearTokens(): void
    {
        $this->cache = [];
    }

    /** Forget the identity too (the machine must enrol again). */
    public function reset(): void
    {
        $this->cache = [];
        $this->store->clear();
    }

    /** @return array<string,mixed>|null */
    private function identity(): ?array
    {
        $id = $this->store->load();

        return $id !== null && ($id['issuer'] ?? null) === $this->issuer ? $id : null;
    }

    /**
     * @param array<string,mixed> $req
     *
     * @return array{token:string,tokenType:string,expiresAt:int,audience:?string,scope:?string,constraints:?array<string,mixed>,organizationId:?string}
     */
    private function mint(array $req): array
    {
        $id = $this->identity() ?? throw new MachineClientException(0, 'not_enrolled', 'This machine is not enrolled. Call enroll() first.');
        $challenge = $this->post('/v1/machines/challenge', ['machine_id' => $id['machine_id']]);
        [$seed] = self::keyOf((string) $id['private_key_pem']);
        $signature = sodium_crypto_sign_detached((string) $challenge['nonce'], sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($seed)));
        $res = $this->post('/v1/machines/token', array_filter([
            'machine_id' => $id['machine_id'],
            'nonce' => $challenge['nonce'],
            'signature' => rtrim(strtr(base64_encode($signature), '+/', '-_'), '='),
            'audience' => $req['audience'] ?? null,
            'scopes' => $req['scopes'] ?? null,
            'scope_presets' => $req['scopePresets'] ?? null,
            'organization_id' => $req['organizationId'] ?? null,
        ], static fn ($v): bool => $v !== null), true);

        return [
            'token' => (string) $res['token'],
            'tokenType' => ($res['token_type'] ?? null) === 'DPoP' ? 'DPoP' : 'Bearer',
            'expiresAt' => ($this->now)() + (int) $res['expires_in'] * 1000,
            'audience' => $res['audience'] ?? null,
            'scope' => $res['scope'] ?? null,
            'constraints' => $res['constraints'] ?? null,
            'organizationId' => $res['organization_id'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    private function post(string $path, array $body, bool $proof = false): array
    {
        $url = $this->issuer . $path;
        $request = $this->requests->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streams->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        if ($proof && $this->dpopKey !== null) {
            $request = $request->withHeader('DPoP', $this->dpopProof('POST', $url));
        }
        $response = $this->client->sendRequest($request);
        $json = json_decode((string) $response->getBody(), true);
        $json = is_array($json) ? $json : [];
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $first = is_array($json['errors'][0] ?? null) ? $json['errors'][0] : [];
            throw new MachineClientException($status, $first['code'] ?? (is_string($json['code'] ?? null) ? $json['code'] : null), (string) ($first['message'] ?? $json['message'] ?? "Request failed with status $status."));
        }

        return $json;
    }

    private const PKCS8_PREFIX = "\x30\x2e\x02\x01\x00\x30\x05\x06\x03\x2b\x65\x70\x04\x22\x04\x20";
    private const SPKI_PREFIX = "\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00";

    /** A PKCS#8 (private) or SPKI (public) PEM of an Ed25519 keypair. */
    private static function pem(string $keypair, bool $private): string
    {
        $der = $private
            ? self::PKCS8_PREFIX . substr(sodium_crypto_sign_secretkey($keypair), 0, 32)
            : self::SPKI_PREFIX . sodium_crypto_sign_publickey($keypair);

        return "-----BEGIN " . ($private ? 'PRIVATE' : 'PUBLIC') . " KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END " . ($private ? 'PRIVATE' : 'PUBLIC') . " KEY-----\n";
    }

    /** @return array{0:string,1:string} the Ed25519 seed and public key of a PKCS#8 PEM */
    private static function keyOf(string $pem): array
    {
        $der = base64_decode((string) preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem), true);
        if ($der === false || strlen($der) !== 48 || !str_starts_with($der, self::PKCS8_PREFIX)) {
            throw new MachineClientException(0, 'unsupported_key', 'The machine key must be an Ed25519 PKCS#8 PEM.');
        }
        $seed = substr($der, 16);

        return [$seed, sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($seed))];
    }
}
