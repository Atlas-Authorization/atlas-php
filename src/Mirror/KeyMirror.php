<?php

declare(strict_types=1);

namespace Atlas\Mirror;

use Atlas\Auth\ApiKeyAssertion;
use Atlas\Resources\ApiKeys;

/**
 * A local, authoritative mirror of end-user API keys (`ak_`), with Atlas as the minting and revocation backstop.
 *
 * Your request path verifies a presented key against the LOCAL row (sha256 hash, scopes, expiry, revocation) with no network call.
 * Atlas mints and imports keys and is told about every revoke; a revoke made elsewhere reaches the mirror through {@see sync()} (the
 * `/v1/api_keys/revoked` feed) or {@see applyWebhook()} (`api_key.*` events). Revoking locally happens FIRST, so a failed Atlas call
 * never leaves a key usable here.
 *
 *     $mirror = new KeyMirror(new MemoryKeyMirrorStore(), $atlas->apiKeys, maxStalenessMs: 300_000);
 *     $minted = $mirror->mint(['subject_type' => 'user', 'subject_id' => 'user_1', 'scopes' => ['read']]);
 *     $result = $mirror->verify($presented, ['requiredScopes' => ['read'], 'clientIp' => $ip]);
 */
final class KeyMirror
{
    /** @var callable():int */
    private $now;

    /** @var array<string,int> used assertion jtis (`keyId\njti` => expiry ms) when the store has no {@see AssertionJtiStore} */
    private array $usedJtis = [];

    /**
     * @param array<string,list<string>>                                           $scopeImplications scope implications (`*` = every known scope)
     * @param list<string>                                                         $allowedScopes
     * @param bool                                                                 $verifyAtAtlas     on a local miss ask Atlas to verify the secret (keys imported with a bcrypt/argon2id hash)
     * @param int|null                                                             $maxStalenessMs    when set, `verify` answers `code: stale` if the feed was never synced or last synced longer ago than this
     * @param int                                                                  $serveStaleForMs   with `maxStalenessMs`: keep answering from the last data this many extra ms
     * @param (callable(string,string):(array<string,mixed>|null))|null           $membership        `(userId, organizationId)` -> the user's membership `{permissions: list<string>, ...}` or null
     * @param (callable():int)|null                                                $now               epoch ms (tests)
     */
    public function __construct(
        private readonly KeyMirrorStore $store,
        private readonly ApiKeys $apiKeys,
        private readonly array $scopeImplications = [],
        private readonly array $allowedScopes = [],
        private readonly bool $verifyAtAtlas = false,
        private readonly ?int $maxStalenessMs = null,
        private readonly int $serveStaleForMs = 0,
        private readonly mixed $membership = null,
        ?callable $now = null,
    ) {
        $this->now = $now ?? static fn (): int => (int) (microtime(true) * 1000);
    }

    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    /**
     * Mint a key at Atlas and mirror it.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed> the created key with its one-time `secret`
     */
    public function mint(array $body, ?string $idempotencyKey = null): array
    {
        $created = $this->apiKeys->create($body, $idempotencyKey);
        $this->store->put(self::row($created, self::rowHash($created)));

        return $created;
    }

    /** Revoke locally FIRST, then at Atlas (a 404 or 409 there is fine). */
    public function revoke(string $id): void
    {
        $this->store->markRevoked($id, ($this->now)());
        try {
            $this->apiKeys->delete($id);
        } catch (\Atlas\Exception\AtlasApiException $e) {
            if (!in_array($e->getStatus(), [404, 409], true)) {
                throw $e;
            }
        }
    }

    /**
     * @param array{requiredScopes?:list<string>,clientIp?:string} $options
     *
     * @return array<string,mixed> `{valid: true, key, effective_scopes, stale?, organization?}` or `{valid: false, code?, missing_scopes?}`
     */
    public function verify(string $secret, array $options = []): array
    {
        if ($secret === '') {
            return ['valid' => false];
        }
        $now = ($this->now)();
        [$refusal, $stale] = $this->freshness($now);
        if ($refusal !== null) {
            return $refusal;
        }
        $hash = self::hashSecret($secret);
        $key = $this->store->getByHash($hash);
        $ip = $options['clientIp'] ?? null;
        if ($key === null && $this->verifyAtAtlas) {
            $remote = $this->apiKeys->verify($secret);
            if (($remote['valid'] ?? false) === true && is_string($remote['id'] ?? null)) {
                $key = self::row($this->apiKeys->get($remote['id']), $hash);
                $this->store->put($key);
            }
        }
        if ($key === null || ($key['enabled'] ?? null) === false || ($key['revoked_at'] ?? null) !== null || (($key['expires_at'] ?? null) !== null && $key['expires_at'] <= $now)) {
            return ['valid' => false];
        }
        // A key with allowed_ips needs the end caller's address; no address fails closed.
        if (!empty($key['allowed_ips'])) {
            if ($ip === null || $ip === '') {
                return ['valid' => false, 'code' => 'client_ip_required'];
            }
            if (!Cidr::contains($ip, $key['allowed_ips'])) {
                return ['valid' => false, 'code' => 'ip_not_allowed'];
            }
        }
        $effective = self::expand($key['scopes'] ?? [], $this->scopeImplications, $this->allowedScopes);
        $organization = null;
        if (($key['subject_type'] ?? null) === 'user' && !empty($key['organization_id'])) {
            // A bound key stands for the user's CURRENT membership; never answer from the key's own scopes.
            if ($this->membership !== null) {
                $held = ($this->membership)((string) $key['subject_id'], (string) $key['organization_id']);
                if ($held === null) {
                    return ['valid' => false];
                }
                $organization = $held;
                $effective = self::intersect($effective, $held['permissions'] ?? []);
            } else {
                $remote = $this->apiKeys->verify($secret);
                if (($remote['valid'] ?? false) !== true || !isset($remote['organization'])) {
                    return ['valid' => false];
                }
                $organization = $remote['organization'];
                $effective = $remote['effective_scopes'] ?? [];
            }
        }
        $missing = array_values(array_diff($options['requiredScopes'] ?? [], $effective));
        if ($missing !== []) {
            return ['valid' => false, 'code' => 'insufficient_scope', 'missing_scopes' => $missing];
        }

        return ['valid' => true, 'key' => $key, 'effective_scopes' => $effective]
            + ($organization !== null ? ['organization' => $organization] : [])
            + ($stale ? ['stale' => true] : []);
    }

    /**
     * Verify a key-pair assertion (`ApiKeyAssertion::sign`) locally with the checks Atlas verify makes: the signature with the
     * stored public key (`alg` must fit its type), `iss` = `kid`, `aud`, `iat`, `exp` and the lifetime cap, `nbf`, `scope` (a subset of
     * the key's, narrows the result), revocation, expiry, the IP allowlist, then the single-use `jti`. A refusal carries the server's
     * `code`; a key the mirror does not hold answers `valid: false` with none. The `jti` is claimed through the store's optional
     * {@see AssertionJtiStore}, else in this process's memory, and only after every other check passed.
     *
     * @param array{requiredScopes?:list<string>,clientIp?:string} $options
     *
     * @return array<string,mixed> `{valid: true, key, effective_scopes, credential: 'key_pair', jti?, exp, ...}` or `{valid: false, code?, missing_scopes?}`
     */
    public function verifyAssertion(string $token, array $options = []): array
    {
        $nowMs = ($this->now)();
        [$refusal, $stale] = $this->freshness($nowMs);
        if ($refusal !== null) {
            return $refusal;
        }
        $bad = static fn (string $code): array => ['valid' => false, 'code' => $code];
        $parsed = ApiKeyAssertion::parse($token);
        $kid = $parsed[0]['kid'] ?? null;
        if ($parsed === null || !is_string($kid) || $kid === '') {
            return $bad('invalid_assertion');
        }
        [$header, $claims, $input, $signature] = $parsed;
        $key = $this->store->get($kid);
        if ($key === null) {
            return ['valid' => false];
        }
        if (($key['credential'] ?? 'secret') !== 'key_pair' || empty($key['public_key_pem'])) {
            return $bad('invalid_assertion');
        }
        if (!is_string($header['alg'] ?? null) || !ApiKeyAssertion::verifySignature($header['alg'], $input, $signature, $key['public_key_pem']) || ($claims['iss'] ?? null) !== $kid) {
            return $bad('invalid_assertion');
        }
        if (($key['enabled'] ?? null) === false) {
            return $bad('invalid_assertion');
        }
        if (($key['revoked_at'] ?? null) !== null || (($key['expires_at'] ?? null) !== null && $key['expires_at'] <= $nowMs)) {
            return ['valid' => false];
        }
        $nowS = intdiv($nowMs, 1000);
        $aud = $claims['aud'] ?? null;
        if (!(is_string($aud) ? $aud === ($key['audience'] ?? null) : is_array($aud) && in_array($key['audience'] ?? null, $aud, true))) {
            return $bad('wrong_audience');
        }
        $num = static fn (mixed $v): bool => is_int($v) || is_float($v);
        $iat = $claims['iat'] ?? null;
        $exp = $claims['exp'] ?? null;
        $nbf = $claims['nbf'] ?? null;
        $jti = $claims['jti'] ?? null;
        $scope = $claims['scope'] ?? null;
        if (!$num($iat) || !$num($exp) || (array_key_exists('nbf', $claims) && !$num($nbf))
            || (array_key_exists('jti', $claims) && (!is_string($jti) || $jti === ''))
            || (array_key_exists('scope', $claims) && !is_string($scope))) {
            return $bad('invalid_assertion');
        }
        if ($iat > $nowS + 60 || ($nbf !== null && $nbf > $nowS + 60)) {
            return $bad('assertion_not_yet_valid');
        }
        if ($exp <= $nowS - 30) {
            return $bad('assertion_expired');
        }
        if ($exp - $iat > min($key['max_token_lifetime_seconds'] ?? 3600, 86400)) {
            return $bad('assertion_lifetime_too_long');
        }
        $ip = $options['clientIp'] ?? null;
        if (!empty($key['allowed_ips'])) {
            if ($ip === null || $ip === '') {
                return $bad('client_ip_required');
            }
            if (!Cidr::contains($ip, $key['allowed_ips'])) {
                return $bad('ip_not_allowed');
            }
        }
        $effective = self::expand($key['scopes'] ?? [], $this->scopeImplications, $this->allowedScopes);
        $organization = null;
        if (($key['subject_type'] ?? null) === 'user' && !empty($key['organization_id'])) {
            if ($this->membership === null) {
                return $bad('membership_unresolved');
            }
            $organization = ($this->membership)((string) $key['subject_id'], (string) $key['organization_id']);
            if ($organization === null) {
                return ['valid' => false];
            }
            $effective = self::intersect($effective, $organization['permissions'] ?? []);
        }
        if (is_string($scope) && trim($scope) !== '') {
            $asked = preg_split('/\s+/', trim($scope)) ?: [];
            $notGranted = array_values(array_diff($asked, $effective));
            if ($notGranted !== []) {
                return ['valid' => false, 'code' => 'scope_not_granted', 'missing_scopes' => $notGranted];
            }
            $effective = array_values(array_intersect($effective, $asked));
        }
        $missing = array_values(array_diff($options['requiredScopes'] ?? [], $effective));
        if ($missing !== []) {
            return ['valid' => false, 'code' => 'insufficient_scope', 'missing_scopes' => $missing];
        }
        // Last, so a refused assertion never burns its jti.
        if (is_string($jti)) {
            $until = (int) ($exp * 1000);
            if ($this->store instanceof AssertionJtiStore) {
                $first = $this->store->claimAssertionJti($key['id'], $jti, $until);
            } else {
                $this->usedJtis = array_filter($this->usedJtis, static fn (int $t): bool => $t > $nowMs);
                $id = $key['id'] . "\n" . $jti;
                $first = !isset($this->usedJtis[$id]);
                if ($first) {
                    $this->usedJtis[$id] = $until;
                }
            }
            if (!$first) {
                return $bad('assertion_replayed');
            }
        }

        return ['valid' => true, 'key' => $key, 'effective_scopes' => $effective, 'credential' => 'key_pair']
            + (is_string($jti) ? ['jti' => $jti] : []) + ['exp' => (int) $exp]
            + ($organization !== null ? ['organization' => $organization] : [])
            + ($stale ? ['stale' => true] : []);
    }

    /**
     * Consume the revocation feed (`/v1/api_keys/revoked`) into the store.
     *
     * @return int how many revocations were applied
     */
    public function sync(): int
    {
        $since = $this->store->getCursor();
        $revoked = 0;
        while (true) {
            $page = $this->apiKeys->listRevoked(['limit' => 100] + ($since !== null ? ['since' => $since] : []));
            foreach ($page['data'] ?? [] as $ref) {
                $this->store->markRevoked((string) $ref['id'], (int) ($ref['revoked_at'] ?? ($this->now)()), $ref['revoked_reason'] ?? null);
                $revoked++;
            }
            if (is_string($page['next_cursor'] ?? null)) {
                $since = $page['next_cursor'];
                $this->store->setCursor($since);
            }
            if (empty($page['has_more']) || !is_string($page['next_cursor'] ?? null)) {
                $this->store->setLastSyncAt(($this->now)());

                return $revoked;
            }
        }
    }

    /**
     * Apply an `api_key.*` or `organization_membership.deleted` webhook event to the mirror.
     *
     * @param array{type?:mixed,data?:mixed} $event
     *
     * @return bool whether the event changed the mirror
     */
    public function applyWebhook(array $event): bool
    {
        $type = $event['type'] ?? null;
        $data = is_array($event['data'] ?? null) ? $event['data'] : [];
        if (!is_string($type)) {
            return false;
        }
        if ($type === 'organization_membership.deleted' || $type === 'organizationMembership.deleted') {
            if (is_string($data['user_id'] ?? null) && is_string($data['organization_id'] ?? null)) {
                $this->store->markMembershipEnded($data['user_id'], $data['organization_id'], ($this->now)());

                return true;
            }

            return false;
        }
        if (!str_starts_with($type, 'api_key.') || !is_string($data['id'] ?? null) || ($held = $this->store->get($data['id'])) === null) {
            return false;
        }
        if ($type === 'api_key.restored') {
            if ($held['revoked_at'] === null) {
                return false;
            }
            $this->store->put(['revoked_at' => null, 'revoked_reason' => null, 'expires_at' => array_key_exists('expires_at', $data) ? $data['expires_at'] : $held['expires_at']] + $held);

            return true;
        }
        if ($type === 'api_key.revoked' || (is_int($data['revoked_at'] ?? null) && $held['revoked_at'] === null)) {
            $this->store->markRevoked($held['id'], is_int($data['revoked_at'] ?? null) ? $data['revoked_at'] : ($this->now)(), $data['revoked_reason'] ?? null);

            return true;
        }
        if ($type === 'api_key.updated' || $type === 'api_key.created') {
            $merged = $held;
            foreach (['name', 'scopes', 'allowed_ips', 'expires_at', 'enabled'] as $field) {
                if (array_key_exists($field, $data)) {
                    $merged[$field] = $data[$field];
                }
            }
            $this->store->put($merged);

            return true;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $key
     *
     * @return array<string,mixed>
     */
    private static function row(array $key, string $hash): array
    {
        return [
            'id' => $key['id'],
            'hash' => $hash,
            'prefix' => $key['prefix'] ?? '',
            'subject_type' => $key['subject_type'] ?? '',
            'subject_id' => $key['subject_id'] ?? '',
            'name' => $key['name'] ?? null,
            'scopes' => $key['scopes'] ?? null,
            'allowed_ips' => $key['allowed_ips'] ?? null,
            'expires_at' => $key['expires_at'] ?? null,
            'revoked_at' => $key['revoked_at'] ?? null,
            'organization_id' => $key['organization_id'] ?? null,
            'revoked_reason' => $key['revoked_reason'] ?? null,
            // `enabled` false = refused like an unknown key. A key-pair row has no secret hash (`hash` is a placeholder
            // nothing matches); its assertions verify against `public_key_pem`.
            'enabled' => $key['enabled'] ?? null,
            'credential' => $key['credential'] ?? 'secret',
            'public_key_pem' => $key['public_key'] ?? null,
            'audience' => $key['audience'] ?? null,
            'max_token_lifetime_seconds' => $key['max_token_lifetime_seconds'] ?? null,
        ];
    }

    /** @param array<string,mixed> $key */
    private static function rowHash(array $key): string
    {
        return is_string($key['secret'] ?? null) && $key['secret'] !== '' ? self::hashSecret($key['secret']) : 'key_pair:' . $key['id'];
    }

    /** @return array{0:array<string,mixed>|null,1:bool} a refusal when the mirror is too stale to answer, and whether the answer is served stale */
    private function freshness(int $now): array
    {
        if ($this->maxStalenessMs === null) {
            return [null, false];
        }
        $last = $this->store->getLastSyncAt();
        $behind = $last === null ? PHP_INT_MAX : $now - $last - $this->maxStalenessMs;
        if ($behind <= 0) {
            return [null, false];
        }
        if ($last === null || $behind > $this->serveStaleForMs) {
            return [['valid' => false, 'code' => 'stale', 'lastSyncAt' => $last], false];
        }

        return [null, true];
    }

    /**
     * @param list<string>              $held
     * @param array<string,list<string>> $implications
     * @param list<string>              $allowed
     *
     * @return list<string>
     */
    private static function expand(array $held, array $implications, array $allowed): array
    {
        $vocab = $allowed;
        foreach ($implications as $from => $tos) {
            $vocab[] = $from;
            foreach ($tos as $to) {
                if ($to !== '*') {
                    $vocab[] = $to;
                }
            }
        }
        $out = array_fill_keys($held, true);
        $queue = $held;
        while ($queue !== []) {
            foreach ($implications[array_pop($queue)] ?? [] as $to) {
                foreach ($to === '*' ? $vocab : [$to] as $t) {
                    if (!isset($out[$t])) {
                        $out[$t] = true;
                        $queue[] = $t;
                    }
                }
            }
        }
        $list = array_map('strval', array_keys($out));
        sort($list);

        return $list;
    }

    /**
     * @param list<string> $scopes
     * @param list<string> $permissions
     *
     * @return list<string>
     */
    private static function intersect(array $scopes, array $permissions): array
    {
        $out = array_filter($scopes, static function (string $s) use ($permissions): bool {
            foreach ($permissions as $p) {
                if ($p === $s || $p === '*' || (str_ends_with($p, ':*') && str_starts_with($s, substr($p, 0, -1)))) {
                    return true;
                }
            }

            return false;
        });
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }
}
