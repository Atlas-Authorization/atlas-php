# Atlas PHP SDK

The official PHP backend SDK for [Atlas](https://atlasauth.net) — the secret-key
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
    $e->getErrorCode();       // stable machine code, e.g. "LAST_ADMIN"
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
    jwksUrl: 'https://auth.yourdomain.com/.well-known/jwks.json',
    issuer:  'https://auth.yourdomain.com',
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
    bapiBaseUrl: 'https://api.atlasauth.net',     // required for verifyOnline
);

$result = $verifier->verifyOnline($token);
```

### JWKS caching

Keys are cached in-process. A token carrying an unknown `kid` triggers at most
one JWKS refetch per minute, so a flood of random `kid` values cannot be turned
into a flood of outbound requests. Key rotation is picked up automatically with
no deploy.

## Laravel

The SDK ships an optional, auto-discovered integration under `Atlas\Laravel`
(install `illuminate/support` + `illuminate/http` — the base SDK requires
neither). `Atlas\Laravel\AtlasServiceProvider` binds a container singleton
`SessionVerifier` — so the in-process JWKS cache is reused across requests — and
registers two route-middleware aliases. Publish the config and set your keys in
`.env`:

```bash
php artisan vendor:publish --tag=atlas-config
# .env
ATLAS_JWKS_URL=https://auth.yourdomain.com/.well-known/jwks.json
ATLAS_ISSUER=https://auth.yourdomain.com
```

Then gate routes. `atlas.auth` verifies the `Authorization: Bearer` header (or
the `__session` cookie), 401s when absent/invalid, and on success attaches the
verified result to `$request->attributes->get('atlas')` and `$request->user()`.
`atlas.permission` reads the org role / permissions straight off the token:

```php
Route::get('/billing', BillingController::class)
    ->middleware(['atlas.auth', 'atlas.permission:billing:read']);
// a role: prefix requires an org role, e.g. 'atlas.permission:role:admin'

// In the controller:
$claims = $request->user()->claims;        // sub, sid, org_role, …
$request->user()->protect(['role' => 'admin']); // throws ForbiddenException → 403
```

Because the SDK is built on PSR-18/PSR-17, you can inject Laravel's HTTP client
or any other conforming implementation instead of Guzzle by passing it to the
constructor.

## Symfony

An optional Symfony integration lives under `Atlas\Symfony` (install
`symfony/security-http` + `symfony/http-kernel`). Register the bundle and point a
firewall at the authenticator:

```php
// config/bundles.php
Atlas\Symfony\AtlasBundle::class => ['all' => true],
```

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            custom_authenticators:
                - Atlas\Symfony\AtlasAuthenticator
```

`AtlasAuthenticator` claims any request carrying a Bearer token or `__session`
cookie, verifies it locally, and returns a `SelfValidatingPassport` whose user is
an `Atlas\Symfony\AtlasUser` — identifier `sub`, roles `ROLE_USER` plus
`ROLE_<ORG_ROLE>`, full claims via `claims()`. A missing/invalid token is a 401.
Configure it with the `ATLAS_*` environment variables, or under an `atlas` key.

## Testing

The SDK ships a PHPUnit suite that runs entirely against a mocked PSR-18 client
— no network. Run it with:

```bash
composer install
composer test
```

## License

MIT — see [LICENSE](LICENSE).
