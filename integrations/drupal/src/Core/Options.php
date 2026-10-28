<?php

declare(strict_types=1);

namespace Drupal\atlas_auth\Core;

/**
 * The module's configuration, as a plain value object.
 *
 * This deliberately carries NO Drupal coupling: it is built from an
 * associative array (the options the admin saved, read elsewhere via the
 * config API) so the OIDC logic and the user-mapping logic can be unit
 * tested without a Drupal runtime. The settings form owns the storage.
 *
 * @phpstan-type OptionsArray array{
 *     instance_domain?: string,
 *     client_id?: string,
 *     client_secret?: string,
 *     allowed_roles?: list<string>,
 *     default_role?: string,
 *     auto_provision?: bool,
 *     scopes?: list<string>
 * }
 */
final class Options
{
    /** OIDC scopes requested when none are configured. `openid` is mandatory. */
    public const DEFAULT_SCOPES = ['openid', 'email', 'profile'];

    /** The role a freshly provisioned user is granted: none beyond "authenticated". */
    public const DEFAULT_ROLE = '';

    /**
     * @param list<string> $allowed_roles roles permitted to sign in (empty = any)
     * @param list<string> $scopes        OIDC scopes requested at authorize
     */
    public function __construct(
        public readonly string $instance_domain,
        public readonly string $client_id,
        public readonly string $client_secret,
        public readonly array $allowed_roles,
        public readonly string $default_role,
        public readonly bool $auto_provision,
        public readonly array $scopes,
    ) {
    }

    /**
     * Build from the stored options array, coercing every field to a safe type
     * and filling defaults. Never throws — a half-configured module simply
     * reports {@see isConfigured()} as false.
     *
     * @param array<string,mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        $scopes = self::stringList($raw['scopes'] ?? []);
        if (!in_array('openid', $scopes, true)) {
            // `openid` is what makes this OIDC rather than bare OAuth; an id_token
            // is not guaranteed without it, and the whole verify path needs one.
            array_unshift($scopes, 'openid');
        }

        return new self(
            instance_domain: self::str($raw['instance_domain'] ?? ''),
            client_id: self::str($raw['client_id'] ?? ''),
            client_secret: self::str($raw['client_secret'] ?? ''),
            allowed_roles: self::stringList($raw['allowed_roles'] ?? []),
            default_role: self::str($raw['default_role'] ?? self::DEFAULT_ROLE),
            auto_provision: (bool) ($raw['auto_provision'] ?? false),
            scopes: $scopes === ['openid'] ? self::DEFAULT_SCOPES : $scopes,
        );
    }

    /**
     * The minimum needed to run the OIDC login flow: a domain to build endpoints
     * from, and a client id/secret to exchange the code with.
     */
    public function isConfigured(): bool
    {
        return $this->instance_domain !== ''
            && $this->client_id !== ''
            && $this->client_secret !== '';
    }

    /**
     * True when this role is allowed to sign in. An empty allowlist means every
     * role is permitted — the allowlist is opt-in gating, not a default-deny.
     */
    public function roleIsAllowed(string $role): bool
    {
        return $this->allowed_roles === [] || in_array($role, $this->allowed_roles, true);
    }

    private static function str(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            // Accept a comma/space/newline separated string from a textarea field.
            $value = preg_split('/[\s,]+/', trim($value)) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($v): string => is_string($v) ? trim($v) : '', $value),
            static fn (string $v): bool => $v !== '',
        ));
    }
}
