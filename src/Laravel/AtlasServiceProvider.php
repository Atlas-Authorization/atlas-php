<?php

declare(strict_types=1);

namespace Atlas\Laravel;

use Atlas\BetterAuth\BetterAuth;
use Atlas\BetterAuth\SessionBridge;
use Atlas\Client;
use Atlas\Http;
use Atlas\Verify\SessionVerifier;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for the Atlas PHP SDK.
 *
 * Package auto-discovery registers this provider (see `extra.laravel.providers`
 * in composer.json), so a host app gets a container-bound {@see SessionVerifier}
 * and the `atlas.auth` / `atlas.permission` route middleware with no manual
 * wiring. Only the config and the bindings live here — the verification itself is
 * the framework-free {@see SessionVerifier}, untouched.
 */
final class AtlasServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__ . '/../../config/atlas.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'atlas');

        // One verifier per process so the in-process JWKS cache is reused across
        // requests in a long-lived worker (Octane, FrankenPHP, queue daemons).
        $this->app->singleton(SessionVerifier::class, static function (Application $app): SessionVerifier {
            /** @var array<string,mixed> $config */
            $config = $app['config']->get('atlas', []);

            return new SessionVerifier(
                jwksUrl: (string) ($config['jwks_url'] ?? ''),
                issuer: (string) ($config['issuer'] ?? ''),
                authorizedParties: array_values((array) ($config['authorized_parties'] ?? [])),
                secretKey: $config['secret_key'] ?? null,
                bapiBaseUrl: $config['bapi_base_url'] ?? null,
            );
        });

        $this->app->singleton(SessionBridge::class, static function (Application $app): SessionBridge {
            /** @var array<string,mixed> $config */
            $config = $app['config']->get('atlas', []);
            /** @var array<string,mixed> $bridge */
            $bridge = (array) ($config['better_auth_bridge'] ?? []);
            $name = $bridge['cookie_name'] ?? null;
            $domain = $bridge['cookie_domain'] ?? null;

            return new SessionBridge(
                new Client((string) ($config['secret_key'] ?? ''), (string) ($config['bapi_base_url'] ?: Http::DEFAULT_API_URL)),
                cookiePrefix: (string) ($bridge['cookie_prefix'] ?? '') !== '' ? (string) $bridge['cookie_prefix'] : BetterAuth::COOKIE_PREFIX,
                cookieName: is_string($name) && $name !== '' ? $name : null,
                betterAuthCookieDomain: is_string($domain) && $domain !== '' ? $domain : null,
                secure: filter_var($bridge['secure'] ?? true, FILTER_VALIDATE_BOOLEAN),
            );
        });

        $this->app->singleton(BetterAuthBridge::class, static function (Application $app): BetterAuthBridge {
            $url = $app['config']->get('atlas.better_auth_bridge.sign_in_url');

            return new BetterAuthBridge($app->make(SessionBridge::class), is_string($url) && $url !== '' ? $url : null);
        });
    }

    public function boot(Router $router): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::CONFIG_PATH => $this->app->configPath('atlas.php')], 'atlas-config');
        }

        $router->aliasMiddleware('atlas.auth', AtlasAuthenticate::class);
        $router->aliasMiddleware('atlas.permission', RequirePermission::class);
        $router->aliasMiddleware('atlas.better_auth_bridge', BetterAuthBridge::class);
    }
}
