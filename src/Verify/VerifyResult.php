<?php

declare(strict_types=1);

namespace Atlas\Verify;

/**
 * The result of a verification — the PHP twin of the TypeScript discriminated
 * union `VerifyResult`. On success it carries the decoded {@see $claims} plus the
 * ergonomic authorization helpers {@see has()} / {@see protect()}, the
 * server-side equivalent of Clerk's `auth()`. On failure it carries a single
 * coarse {@see $reason}; telling a caller which specific check failed would help
 * a forger more than a developer.
 *
 * Failure reasons: `malformed` (not a three-part JWT / no token on the request),
 * `no_keys` (JWKS empty or unreachable), `invalid` (signature, issuer, expiry,
 * or the token-confusion guard), `unauthorized_party` (azp not in the allowlist).
 */
final class VerifyResult
{
    /**
     * @param array<string,mixed>|null $claims
     */
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $reason,
        public readonly ?array $claims,
    ) {
    }

    /** @param array<string,mixed> $claims */
    public static function ok(array $claims): self
    {
        return new self(true, null, $claims);
    }

    public static function fail(string $reason): self
    {
        return new self(false, $reason, null);
    }

    /**
     * True when the verified claims satisfy the condition. An empty condition
     * means "signed in" — any successfully verified token passes.
     *
     * Supported keys: `role`, `permission`, `anyPermission` (list), and
     * `allPermissions` (list). Reads `org_role` / `org_permissions` straight off
     * the token — never a network call — so a permission change takes effect
     * within one token lifetime, the same bound as revocation.
     *
     * @param array<string,mixed> $condition
     */
    public function has(array $condition = []): bool
    {
        if (!$this->ok || $this->claims === null) {
            return false;
        }

        return self::evaluate($this->claims, $condition) === null;
    }

    /**
     * Assert the condition; returns the claims on success, throws
     * {@see ForbiddenException} otherwise. The server-side gate for a route.
     *
     * @param array<string,mixed> $condition
     *
     * @return array<string,mixed>
     *
     * @throws ForbiddenException
     */
    public function protect(array $condition = []): array
    {
        if (!$this->ok || $this->claims === null) {
            throw new ForbiddenException('not_signed_in', $condition);
        }
        $reason = self::evaluate($this->claims, $condition);
        if ($reason !== null) {
            throw new ForbiddenException($reason, $condition);
        }

        return $this->claims;
    }

    /**
     * Evaluate a condition, returning `null` when it is satisfied or a coarse
     * failure reason otherwise.
     *
     * @param array<string,mixed> $claims
     * @param array<string,mixed> $condition
     */
    private static function evaluate(array $claims, array $condition): ?string
    {
        if ($condition === []) {
            return null;
        }

        $permissions = self::stringList($claims['org_permissions'] ?? []);
        $role = isset($claims['org_role']) && is_string($claims['org_role']) ? $claims['org_role'] : null;

        if (isset($condition['role']) && $condition['role'] !== $role) {
            return 'missing_role';
        }
        if (isset($condition['permission']) && !in_array($condition['permission'], $permissions, true)) {
            return 'missing_permission';
        }
        if (isset($condition['anyPermission']) && is_array($condition['anyPermission'])) {
            $any = false;
            foreach ($condition['anyPermission'] as $p) {
                if (in_array($p, $permissions, true)) {
                    $any = true;
                    break;
                }
            }
            if (!$any) {
                return 'missing_permission';
            }
        }
        if (isset($condition['allPermissions']) && is_array($condition['allPermissions'])) {
            foreach ($condition['allPermissions'] as $p) {
                if (!in_array($p, $permissions, true)) {
                    return 'missing_permission';
                }
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     *
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
