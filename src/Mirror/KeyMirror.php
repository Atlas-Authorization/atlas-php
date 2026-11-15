<?php

declare(strict_types=1);

namespace Atlas\Mirror;

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
        $this->store->put(self::row($created, self::hashSecret((string) $created['secret'])));

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
        $stale = false;
        if ($this->maxStalenessMs !== null) {
            $last = $this->store->getLastSyncAt();
            $behind = $last === null ? PHP_INT_MAX : $now - $last - $this->maxStalenessMs;
            if ($behind > 0) {
                if ($last === null || $behind > $this->serveStaleForMs) {
                    return ['valid' => false, 'code' => 'stale', 'lastSyncAt' => $last];
                }
                $stale = true;
            }
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
        if ($key === null || ($key['revoked_at'] ?? null) !== null || (($key['expires_at'] ?? null) !== null && $key['expires_at'] <= $now)) {
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
            foreach (['name', 'scopes', 'allowed_ips', 'expires_at'] as $field) {
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
        ];
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
