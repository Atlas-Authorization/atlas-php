<?php

declare(strict_types=1);

namespace Drupal\atlas_auth\Core;

/**
 * The verified identity extracted from an Atlas id_token — the result of a
 * successful {@see AtlasOidc::handleCallback()}.
 *
 * A plain value object with no Drupal coupling. {@see \Drupal\atlas_auth\Controller\LoginController} maps it
 * onto a user (by stored `atlas_sub` meta, then by email, then by
 * provisioning).
 */
final class Identity
{
    /**
     * @param string               $subject       the Atlas user id (`sub`), stable across email changes
     * @param string|null          $email         the verified email, if the token carried one
     * @param bool                 $emailVerified the OIDC `email_verified` claim
     * @param array<string,mixed>  $claims        every claim on the id_token, for consumers that need more
     */
    public function __construct(
        public readonly string $subject,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly array $claims,
    ) {
    }

    /**
     * A human-friendly display name from the standard OIDC profile claims, or
     * null when the token carried none.
     */
    public function displayName(): ?string
    {
        foreach (['name', 'preferred_username', 'given_name'] as $claim) {
            $value = $this->claims[$claim] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
