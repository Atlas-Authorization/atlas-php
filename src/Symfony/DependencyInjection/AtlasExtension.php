<?php

declare(strict_types=1);

namespace Atlas\Symfony\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * DI extension for {@see \Atlas\Symfony\AtlasBundle}.
 *
 * It resolves the verifier's configuration — from bundle config under the `atlas`
 * key, falling back to the `ATLAS_*` environment variables — into container
 * parameters, then loads `Resources/config/services.yaml`, which binds the
 * {@see \Atlas\Verify\SessionVerifier} and {@see \Atlas\Symfony\AtlasAuthenticator}
 * against those parameters.
 */
final class AtlasExtension extends Extension
{
    public function getAlias(): string
    {
        return 'atlas';
    }

    /**
     * @param array<int,array<string,mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $merged = [];
        foreach ($configs as $config) {
            $merged = array_merge($merged, $config);
        }

        $container->setParameter('atlas.jwks_url', self::str($merged['jwks_url'] ?? getenv('ATLAS_JWKS_URL')));
        $container->setParameter('atlas.issuer', self::str($merged['issuer'] ?? getenv('ATLAS_ISSUER')));
        $container->setParameter('atlas.secret_key', self::nullableStr($merged['secret_key'] ?? getenv('ATLAS_SECRET_KEY')));
        $container->setParameter(
            'atlas.bapi_base_url',
            self::nullableStr($merged['bapi_base_url'] ?? getenv('ATLAS_BAPI_URL') ?: 'https://api.atlasauth.net'),
        );
        $container->setParameter('atlas.authorized_parties', self::parties($merged['authorized_parties'] ?? getenv('ATLAS_AUTHORIZED_PARTIES')));

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    private static function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function nullableStr(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    private static function parties(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, static fn ($v): bool => is_string($v) && $v !== ''));
        }
        if (is_string($value) && $value !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $v): bool => $v !== ''));
        }

        return [];
    }
}
