<?php

declare(strict_types=1);

namespace Atlas\Symfony;

use Atlas\Symfony\DependencyInjection\AtlasExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * The Symfony bundle for the Atlas PHP SDK. Register it in `config/bundles.php`:
 *
 *   Atlas\Symfony\AtlasBundle::class => ['all' => true],
 *
 * It wires the {@see \Atlas\Verify\SessionVerifier} and {@see AtlasAuthenticator}
 * into the container (see {@see AtlasExtension} and `Resources/config/services.yaml`);
 * reference `Atlas\Symfony\AtlasAuthenticator` as a `custom_authenticator` on a
 * firewall in `security.yaml` to protect it.
 */
final class AtlasBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= new AtlasExtension();
    }
}
