<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Atlas\Exception\ConfigException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Verifies {@see LegacyIssuer} tokens for {@see SessionVerifier}: Better Auth `jwt` plugin tokens
 * (EdDSA / Ed25519, ES256, ES512, RS256, PS256) during a cutover.
 *
 * The signature is checked with ext-openssl (RSA PKCS#1 v1.5, ECDSA P-256 / P-521, and RSASSA-PSS
 * via a raw RSA operation plus the RFC 8017 EMSA-PSS check) and ext-sodium (Ed25519), against the
 * issuer's own keys only.
 */
final class LegacyIssuerVerifier
{
    /** How long a resolved sub → Atlas id mapping is reused. */
    public const SUBJECT_TTL_MS = 300_000;

    /** Cap on cached subjects, so a stream of distinct tokens cannot grow memory unbounded. */
    public const SUBJECT_CACHE_MAX = 10_000;

    /** @var list<LegacyIssuer> */
    private readonly array $issuers;

    /** @var array<string,JwksCache> */
    private array $caches = [];

    /** @var array<string,array{0:?string,1:int}> */
    private array $subjects = [];

    /** @var callable(): int epoch ms */
    private $now;

    /**
     * @param list<LegacyIssuer> $issuers
     * @param callable():int|null $now epoch milliseconds
     */
    public function __construct(
        array $issuers,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly ?string $secretKey = null,
        private readonly string $apiUrl = 'https://api.atlasauth.net',
        ?callable $now = null,
        private readonly int $leeway = SessionVerifier::CLOCK_SKEW_SECONDS,
    ) {
        $this->issuers = array_values($issuers);
        $this->now = $now ?? static fn (): int => (int) floor(microtime(true) * 1000);
        foreach ($this->issuers as $entry) {
            if (!$entry instanceof LegacyIssuer) {
                throw new ConfigException('legacyIssuers takes Atlas\\Verify\\LegacyIssuer entries.');
            }
            if ($entry->resolveSubject === null && ($secretKey === null || $secretKey === '')) {
                throw new ConfigException(
                    "legacyIssuers: \"{$entry->issuer}\" maps its sub to an Atlas user through the Backend API, which "
                    . 'needs secretKey on the verifier, or give the issuer a resolveSubject.',
                );
            }
            if ($entry->jwksUrl !== null) {
                $this->caches[$entry->issuer] = new JwksCache(
                    url: $entry->jwksUrl,
                    client: $client,
                    requestFactory: $requestFactory,
                    now: $this->now,
                );
            }
        }
    }

    /** The UNVERIFIED `iss` of a compact JWT, for routing only. */
    public static function peekIssuer(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = json_decode(self::b64url($parts[1]) ?? '', true);
        $iss = is_array($payload) ? ($payload['iss'] ?? null) : null;

        return is_string($iss) ? $iss : null;
    }

    /** The configured entry for this `iss`, when its window is still open. */
    public function match(?string $iss): ?LegacyIssuer
    {
        if ($iss === null || $iss === '') {
            return null;
        }
        foreach ($this->issuers as $entry) {
            if ($entry->issuer === $iss) {
                return $entry->untilMs > ($this->now)() ? $entry : null;
            }
        }

        return null;
    }

    /**
     * Verify one token for a matched entry.
     *
     * @return array{0:'ok'|'no_keys'|'invalid',1:array<string,mixed>|null}
     */
    public function verify(string $token, LegacyIssuer $entry): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['invalid', null];
        }
        [$h, $p, $s] = $parts;
        $header = json_decode(self::b64url($h) ?? '', true);
        $payload = json_decode(self::b64url($p) ?? '', true);
        $signature = self::b64url($s);
        if (!is_array($header) || !is_array($payload) || $signature === null) {
            return ['invalid', null];
        }
        $alg = $header['alg'] ?? null;
        $kid = is_string($header['kid'] ?? null) ? $header['kid'] : null;
        if (!is_string($alg) || !in_array($alg, $entry->algorithms, true)) {
            return ['invalid', null];
        }
        $keys = $this->keys($entry, $kid);
        if ($keys === []) {
            return ['no_keys', null];
        }
        $input = $h . '.' . $p;
        $verified = false;
        foreach ($keys as $jwk) {
            if ($kid !== null && ($jwk['kid'] ?? null) !== $kid) {
                continue;
            }
            if (!in_array($alg, self::keyAlgs($jwk), true) || !in_array($jwk['alg'] ?? null, [null, $alg], true)) {
                continue;
            }
            if (self::verifySignature($alg, $jwk, $input, $signature)) {
                $verified = true;
                break;
            }
        }
        if (!$verified) {
            return ['invalid', null];
        }

        $nowSec = intdiv(($this->now)(), 1000);
        if (($payload['iss'] ?? null) !== $entry->issuer) {
            return ['invalid', null];
        }
        // A legacy token without an expiry would outlive the window it is accepted in.
        $exp = $payload['exp'] ?? null;
        if (!is_int($exp) && !is_float($exp)) {
            return ['invalid', null];
        }
        if ($nowSec > $exp + $this->leeway) {
            return ['invalid', null];
        }
        $nbf = $payload['nbf'] ?? null;
        if ((is_int($nbf) || is_float($nbf)) && $nowSec < $nbf - $this->leeway) {
            return ['invalid', null];
        }
        if ($entry->audience !== null) {
            $aud = $payload['aud'] ?? null;
            $tokenAud = is_array($aud) ? $aud : (is_string($aud) ? [$aud] : []);
            if (array_intersect($entry->audience, $tokenAud) === []) {
                return ['invalid', null];
            }
        }
        $legacySub = is_string($payload['sub'] ?? null) ? $payload['sub'] : '';
        if ($legacySub === '' && is_string($payload['id'] ?? null)) {
            $legacySub = $payload['id'];
        }
        if ($legacySub === '') {
            return ['invalid', null];
        }
        $atlasId = $this->resolve($legacySub, $payload, $entry);
        if ($atlasId === null || $atlasId === '') {
            return ['invalid', null];
        }
        $claims = $payload;
        $claims['sub'] = $atlasId;
        $claims['sid'] = is_string($payload['sid'] ?? null) ? $payload['sid'] : '';
        $claims['external_id'] = $legacySub;
        $claims['legacy'] = ['kind' => $entry->kind, 'issuer' => $entry->issuer, 'sub' => $legacySub];

        return ['ok', $claims];
    }

    /** @return list<array<string,mixed>> */
    private function keys(LegacyIssuer $entry, ?string $kid): array
    {
        $set = isset($this->caches[$entry->issuer]) ? $this->caches[$entry->issuer]->get($kid) : $entry->jwks;
        $keys = is_array($set) && is_array($set['keys'] ?? null) ? $set['keys'] : [];

        return array_values(array_filter($keys, 'is_array'));
    }

    private function resolve(string $legacySub, array $claims, LegacyIssuer $entry): ?string
    {
        if ($entry->resolveSubject !== null) {
            try {
                $id = ($entry->resolveSubject)($legacySub, $claims);

                return is_string($id) && $id !== '' ? $id : null;
            } catch (\Throwable) {
                return null;
            }
        }
        $key = $entry->issuer . "\0" . $legacySub;
        $now = ($this->now)();
        $hit = $this->subjects[$key] ?? null;
        if ($hit !== null && $now - $hit[1] < self::SUBJECT_TTL_MS) {
            return $hit[0];
        }
        $url = rtrim($this->apiUrl, '/') . '/v1/users?external_id=' . rawurlencode($legacySub) . '&limit=1';
        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Authorization', 'Bearer ' . $this->secretKey)
            ->withHeader('Accept', 'application/json');
        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface) {
            return null;
        }
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return null; // not cached: a transient failure should not stick
        }
        $body = json_decode((string) $response->getBody(), true);
        if (!is_array($body)) {
            return null;
        }
        $first = is_array($body['data'] ?? null) ? ($body['data'][0] ?? null) : null;
        $found = is_array($first) && is_string($first['id'] ?? null) && $first['id'] !== '' ? $first['id'] : null;
        if (count($this->subjects) >= self::SUBJECT_CACHE_MAX) {
            $this->subjects = [];
        }
        $this->subjects[$key] = [$found, $now];

        return $found;
    }

    /**
     * The algorithms a JWK's type can verify.
     *
     * @param array<string,mixed> $jwk
     *
     * @return list<string>
     */
    public static function keyAlgs(array $jwk): array
    {
        $kty = $jwk['kty'] ?? null;
        $crv = $jwk['crv'] ?? null;

        return match (true) {
            $kty === 'RSA' => ['RS256', 'PS256'],
            $kty === 'EC' && $crv === 'P-256' => ['ES256'],
            $kty === 'EC' && $crv === 'P-521' => ['ES512'],
            $kty === 'OKP' && $crv === 'Ed25519' => ['EdDSA'],
            default => [],
        };
    }

    /** @param array<string,mixed> $jwk */
    public static function verifySignature(string $alg, array $jwk, string $input, string $signature): bool
    {
        try {
            return match ($alg) {
                'RS256' => self::opensslVerify($input, $signature, self::rsaPem($jwk), OPENSSL_ALGO_SHA256),
                'PS256' => self::verifyPss($input, $signature, $jwk),
                'ES256' => self::verifyEcdsa($input, $signature, $jwk, 32, OPENSSL_ALGO_SHA256),
                'ES512' => self::verifyEcdsa($input, $signature, $jwk, 66, OPENSSL_ALGO_SHA512),
                'EdDSA' => self::verifyEd25519($input, $signature, $jwk),
                default => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }

    private static function opensslVerify(string $input, string $signature, ?string $pem, int $algo): bool
    {
        if ($pem === null) {
            return false;
        }
        $key = openssl_pkey_get_public($pem);

        return $key !== false && openssl_verify($input, $signature, $key, $algo) === 1;
    }

    /** @param array<string,mixed> $jwk */
    private static function verifyEcdsa(string $input, string $signature, array $jwk, int $size, int $algo): bool
    {
        if (strlen($signature) !== 2 * $size) {
            return false;
        }
        $der = self::derSequence(
            self::derInteger(substr($signature, 0, $size)) . self::derInteger(substr($signature, $size)),
        );

        return self::opensslVerify($input, $der, self::ecPem($jwk, $size), $algo);
    }

    /** @param array<string,mixed> $jwk */
    private static function verifyEd25519(string $input, string $signature, array $jwk): bool
    {
        $x = is_string($jwk['x'] ?? null) ? self::b64url($jwk['x']) : null;
        if ($x === null || strlen($x) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $input, $x);
    }

    /**
     * RSASSA-PSS with SHA-256, MGF1-SHA-256 and a 32-byte salt (JWA PS256), RFC 8017 §8.1.2 / §9.1.2.
     *
     * @param array<string,mixed> $jwk
     */
    private static function verifyPss(string $input, string $signature, array $jwk): bool
    {
        $n = is_string($jwk['n'] ?? null) ? self::b64url($jwk['n']) : null;
        $pem = self::rsaPem($jwk);
        if ($n === null || $pem === null) {
            return false;
        }
        $n = ltrim($n, "\0");
        $k = strlen($n);
        if ($k === 0 || strlen($signature) !== $k) {
            return false;
        }
        $key = openssl_pkey_get_public($pem);
        if ($key === false || !openssl_public_decrypt($signature, $em, $key, OPENSSL_NO_PADDING)) {
            return false;
        }
        $em = str_pad((string) $em, $k, "\0", STR_PAD_LEFT);
        $modBits = ($k - 1) * 8 + strlen(decbin(ord($n[0])));
        $emBits = $modBits - 1;
        $emLen = intdiv($emBits + 7, 8);
        if ($emLen < $k) {
            if ($em[0] !== "\0") {
                return false;
            }
            $em = substr($em, 1);
        }
        $hLen = 32;
        $sLen = 32;
        if ($emLen < $hLen + $sLen + 2 || substr($em, -1) !== "\xbc") {
            return false;
        }
        $maskedDb = substr($em, 0, $emLen - $hLen - 1);
        $h = substr($em, $emLen - $hLen - 1, $hLen);
        $zeroBits = 8 * $emLen - $emBits;
        $topMask = $zeroBits > 0 ? (0xFF << (8 - $zeroBits)) & 0xFF : 0;
        if ((ord($maskedDb[0]) & $topMask) !== 0) {
            return false;
        }
        $db = $maskedDb ^ self::mgf1($h, strlen($maskedDb));
        $db[0] = chr(ord($db[0]) & (~$topMask & 0xFF));
        $psLen = $emLen - $hLen - $sLen - 2;
        if (strspn($db, "\0", 0, $psLen) !== $psLen || $db[$psLen] !== "\x01") {
            return false;
        }
        $salt = substr($db, -$sLen);
        $expected = hash('sha256', str_repeat("\0", 8) . hash('sha256', $input, true) . $salt, true);

        return hash_equals($expected, $h);
    }

    private static function mgf1(string $seed, int $length): string
    {
        $out = '';
        for ($counter = 0; strlen($out) < $length; $counter++) {
            $out .= hash('sha256', $seed . pack('N', $counter), true);
        }

        return substr($out, 0, $length);
    }

    /** @param array<string,mixed> $jwk */
    private static function rsaPem(array $jwk): ?string
    {
        $n = is_string($jwk['n'] ?? null) ? self::b64url($jwk['n']) : null;
        $e = is_string($jwk['e'] ?? null) ? self::b64url($jwk['e']) : null;
        if ($n === null || $e === null || $n === '' || $e === '') {
            return null;
        }
        $rsaKey = self::derSequence(self::derInteger($n) . self::derInteger($e));
        $algId = self::derSequence(hex2bin('06092a864886f70d010101') . "\x05\x00");

        return self::pem(self::derSequence($algId . self::derBitString($rsaKey)));
    }

    /** @param array<string,mixed> $jwk */
    private static function ecPem(array $jwk, int $size): ?string
    {
        $x = is_string($jwk['x'] ?? null) ? self::b64url($jwk['x']) : null;
        $y = is_string($jwk['y'] ?? null) ? self::b64url($jwk['y']) : null;
        if ($x === null || $y === null || strlen($x) !== $size || strlen($y) !== $size) {
            return null;
        }
        $curve = $size === 32 ? hex2bin('06082a8648ce3d030107') : hex2bin('06052b81040023');
        $algId = self::derSequence(hex2bin('06072a8648ce3d0201') . $curve);

        return self::pem(self::derSequence($algId . self::derBitString("\x04" . $x . $y)));
    }

    private static function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function derLength(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        $bytes = ltrim(pack('N', $len), "\0");

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function derSequence(string $content): string
    {
        return "\x30" . self::derLength(strlen($content)) . $content;
    }

    private static function derInteger(string $unsigned): string
    {
        $v = ltrim($unsigned, "\0");
        if ($v === '' || (ord($v[0]) & 0x80) !== 0) {
            $v = "\0" . $v;
        }

        return "\x02" . self::derLength(strlen($v)) . $v;
    }

    private static function derBitString(string $content): string
    {
        return "\x03" . self::derLength(strlen($content) + 1) . "\0" . $content;
    }

    private static function b64url(string $value): ?string
    {
        if ($value === '' || preg_match('/[^A-Za-z0-9_-]/', $value) === 1) {
            return $value === '' ? '' : null;
        }
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }
}
