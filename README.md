# Atlas PHP SDK

The official PHP backend SDK for [Atlas](https://atlas.dev) — the secret-key
Backend API client plus local session-token/JWT verification. It mirrors the
TypeScript SDK (`@atlas/backend`) namespace-for-namespace, so the same 45
resource namespaces are available with the same method names.

- **PSR-18 / PSR-17 transport.** Runs on Guzzle out of the box, or any PSR-18
  client you inject. No network is required to unit-test code that uses it.
- **Local JWT verification.** `SessionVerifier` verifies session tokens against
  cached JWKS with no call to Atlas on the hot path — the same design as the
  TypeScript verifier.

Requires PHP 8.1+.

## Install

```bash
composer require atlas-auth/atlas-php
```

## The Backend API client

Construct `Atlas\Client` with your instance secret key. Guzzle is
auto-discovered, so the zero-config path needs nothing else:

```php
use Atlas\Client;

$atlas = new Client('sk_live_…');

$user = $atlas->users->get('user_123');

$page = $atlas->users->list(['limit' => 50]);
foreach ($page['data'] as $u) { /* … */ }
```

Every resource method returns the decoded JSON as an associative array. Writes
take an associative body and an optional idempotency key:

```php
$org = $atlas->organizations->create(['name' => 'Acme', 'slug' => 'acme'], idempotencyKey: 'req-1');
$atlas->sessions->revoke('sess_123');
```

### Pagination

```php
use Atlas\Pagination;

foreach (Pagination::iterate([$atlas->users, 'list']) as $user) {
    // walks every cursor page
}
```

### Error handling

Any non-2xx response throws the most specific `Atlas\Exception\*` subclass, all
extending `AtlasApiException`:

```php
use Atlas\Exception\NotFoundException;
use Atlas\Exception\AtlasApiException;

try {
    $atlas->users->get('user_missing');
} catch (NotFoundException $e) {
    // 404
} catch (AtlasApiException $e) {
    $e->getStatus();          // int HTTP status
    $e->getCode();            // stable machine code, e.g. "LAST_ADMIN"
    $e->hasCode('LAST_ADMIN');
    $e->getErrors();          // full §9.1 error envelope
}
```

`TransportException` is thrown when no response is received at all;
`ConfigException` on a bad configuration (e.g. an empty key).

### The 45 namespaces

`users`, `sessions`, `organizations`, `roles`, `permissions`, `oauthClients`,
`resourceServers`, `ssoConnections`, `scimTokens`, `domains`, `waitlist`,
`allowlist`, `blocklist`, `attackProtection`, `actorTokens`, `invitations`,
`webhooks`, `signInTokens`, `auditLogs`, `jwtTemplates`, `apiKeys`,
`oauthProviders`, `ssoOnboarding`, `scimProvisioning`, `fga`, `rateLimitPolicy`,
`riskBasedMfa`, `botSignals`, `networkAcls`, `managedWaf`, `logStreams`,
`branding`, `emailTemplates`, `smsTemplates`, `localizations`, `actions`,
`billing`, `messaging`, `importExport`, `dataSubjectRequests`, `radiusClients`,
`ltiPlatforms`, `instance`, `instanceSecurity`, `tokens`.

## Verifying session tokens

`Atlas\Verify\SessionVerifier` verifies a session JWT locally: RS256 against
cached JWKS, `exp`/`nbf` with a 5-second clock skew, the issuer, and an optional
`azp` allowlist. It never calls Atlas on the hot path — the revocation window is
bounded by the 60-second token lifetime instead.

```php
use Atlas\Verify\SessionVerifier;

$verifier = new SessionVerifier(
    jwksUrl: 'https://your-instance.atlas.dev/.well-known/jwks.json',
    issuer:  'https://your-instance.atlas.dev',
    authorizedParties: ['https://app.example.com'], // optional azp allowlist
);

// Verify whatever the request carries (Authorization header or __session cookie).
$result = $verifier->authenticateRequest(getallheaders());

if (!$result->ok) {
    http_response_code(401);
    exit;
}

$userId = $result->claims['sub'];

// Authorization helpers, bound to the verified claims:
if ($result->has(['permission' => 'billing:read'])) { /* … */ }
$result->protect(['role' => 'admin']); // throws Atlas\Verify\ForbiddenException on failure
```

`authenticateRequest()` accepts a PSR-7 request or a plain, case-insensitive
`array<string,string>` of headers. `verify(string $token)` is the direct form.

### The online slow path

For the handful of operations where a 60-second revocation window is
unacceptable (deleting an account, moving money), `verifyOnline()` asks Atlas
whether the session is still live. It costs a round trip and **fails closed** — an
outage returns `invalid`, never a stale local pass — so it is not the default.

```php
$verifier = new SessionVerifier(
    jwksUrl: '…',
    issuer:  '…',
    secretKey:   'sk_live_…',                 // required for verifyOnline
    bapiBaseUrl: 'https://api.atlas.dev',     // required for verifyOnline
);

$result = $verifier->verifyOnline($token);
```

### JWKS caching

Keys are cached in-process. A token carrying an unknown `kid` triggers at most
one JWKS refetch per minute, so a flood of random `kid` values cannot be turned
into a flood of outbound requests. Key rotation is picked up automatically with
no deploy.

## Laravel

Register the client and verifier as singletons in a service provider so they are
resolved from the container and reuse the in-process JWKS cache across requests:

```php
namespace App\Providers;

use Atlas\Client;
use Atlas\Verify\SessionVerifier;
use Illuminate\Support\ServiceProvider;

class AtlasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, static fn () => new Client(config('services.atlas.secret_key')));

        $this->app->singleton(SessionVerifier::class, static fn () => new SessionVerifier(
            jwksUrl: config('services.atlas.jwks_url'),
            issuer:  config('services.atlas.issuer'),
        ));
    }
}
```

Then type-hint `Atlas\Client` or `Atlas\Verify\SessionVerifier` anywhere the
container resolves — a controller, a job, or route middleware that calls
`authenticateRequest($request)` to gate a route. Add the provider to
`config/app.php` (or let package discovery pick it up), and put your keys in
`config/services.php` under an `atlas` entry.

Because the SDK is built on PSR-18/PSR-17, you can inject Laravel's HTTP client
or any other conforming implementation instead of Guzzle by passing it to the
constructor.

## Testing

The SDK ships a PHPUnit suite that runs entirely against a mocked PSR-18 client
— no network. Run it with:

```bash
composer install
composer test
```

## License

MIT — see [LICENSE](LICENSE).
