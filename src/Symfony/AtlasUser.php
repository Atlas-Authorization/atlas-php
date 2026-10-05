<?php

declare(strict_types=1);

namespace Atlas\Symfony;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The authenticated Atlas subject, as Symfony's security layer sees it.
 *
 * It is a thin, read-only view over the verified session claims: the identifier
 * is the token `sub`, and the roles are Symfony-shaped (`ROLE_USER`, plus
 * `ROLE_<ORG_ROLE>` when the token carries one) so `#[IsGranted]` and
 * `is_granted()` work without any extra wiring. The full claim set stays
 * available via {@see claims()} for anything finer-grained.
 */
final class AtlasUser implements UserInterface
{
    /**
     * @param array<string,mixed> $claims the verified session claims
     */
    public function __construct(
        private readonly string $identifier,
        private readonly array $claims = [],
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        $orgRole = $this->claims['org_role'] ?? null;
        if (is_string($orgRole) && $orgRole !== '') {
            $roles[] = 'ROLE_' . strtoupper($orgRole);
        }

        return array_values(array_unique($roles));
    }

    /**
     * The verified session claims (`sub`, `sid`, `org_role`, `org_permissions`, …).
     *
     * @return array<string,mixed>
     */
    public function claims(): array
    {
        return $this->claims;
    }

    /**
     * No secret is ever held in memory — verification is stateless against JWKS —
     * so there is nothing to erase.
     */
    public function eraseCredentials(): void
    {
    }
}
