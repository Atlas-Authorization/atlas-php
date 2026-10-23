# Atlas PHP SDK

The official PHP backend SDK for [Atlas](https://atlasauth.net) — the secret-key
Backend API client plus local session-token/JWT verification. It covers every
Backend API operation and the platform-key API, with namespaces and method names
that mirror the TypeScript SDK.

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
    $e->getErrors();          // the full error envelope
}
```

`TransportException` is thrown when no response is received at all;
`ConfigException` on a bad configuration (e.g. an empty key).

`Atlas\ErrorCode` names the codes most worth branching on — `UNKNOWN_FIELD`,
`EMAIL_DELIVERY_NOT_CONFIGURED`, `EMAIL_PROVIDER_REQUIRED`, `EMAIL_PROVIDER_IN_USE`,
`PLAN_IN_USE`, `WRONG_KEY_SURFACE`, `INVALID_KEY` and more:

```php
use Atlas\ErrorCode;

if ($e->hasCode(ErrorCode::EMAIL_DELIVERY_NOT_CONFIGURED)) { /* tell an admin */ }
```

### Strict request bodies

By default a write that includes a field the endpoint does not accept still
succeeds; the field is ignored and named in the `Atlas-Unknown-Fields` response
header. Turn on strict bodies to have such a write refused instead (422
`UNKNOWN_FIELD`, with `param` naming the field), and/or get told about ignored
fields:

```php
use Atlas\UnknownFields;

$atlas = new Client(
    'sk_live_…',
    strict: true,   // sends Atlas-Strict: true on every write
    onUnknownFields: function (UnknownFields $w) use ($logger): void {
        $logger->warning('Atlas ignored ' . implode(', ', $w->fields) . " on {$w->method} {$w->path}");
    },
);

// Override for one call:
$atlas->withStrict(false)->users->update('user_123', ['legacy_field' => 'x']);
```

`withStrict(false)` sends `Atlas-Strict: false`, which opts a request out of an
instance-wide strict default; `withStrict(null)` sends no header.

### Check your setup

```php
$report = $atlas->instance->preflight();
if (!$report['ok']) {
    foreach ($report['findings'] as $f) {
        echo "{$f['severity']} {$f['code']}: {$f['message']}\n  fix: {$f['fix']}\n";
    }
}
```

### Sign-in page design

```php
// Upload an image (PNG, JPEG, GIF or WebP; the type is detected from the bytes)
$asset = $atlas->design->uploadAsset(file_get_contents('hero.jpg'), 'background');

$atlas->design->update([
    'preset' => 'glass',
    'palette' => ['primary' => '#4f46e5'],
    'background' => ['image' => ['assetId' => $asset['asset_id'], 'fit' => 'cover']],
]);
$atlas->design->preview(['preset' => 'midnight']);   // compile without saving
$atlas->organizations->updateDesign('org_123', ['palette' => ['primary' => '#0f766e']]);
$atlas->design->catalog();                           // presets, fonts, CSS variables
```

The design array is fully typed (`AtlasDesign`) for static analysers; see
`Atlas\Resources\Design`.

### Cookie consent

```php
use Atlas\Resources\Instance;

$atlas->instance->setCookieConsent([
    'script_url' => 'https://cmp.example.com/loader.js',
    'site_id' => 'abc123',
    // Optional "cookie settings" link on the hosted pages (null or omitted = no link).
    'settings_link' => ['label' => 'Cookie settings', 'preset' => Instance::COOKIE_SETTINGS_LINK_PRESET_COOKIEBOT],
]);
$consent = $atlas->instance->getCookieConsent();
$consent['settings_link']['rendered_attributes']; // your attributes plus the preset's trigger
$atlas->instance->deleteCookieConsent();
```

A settings link needs a `preset` (`cookiemunch`, `cookiebot`, `onetrust` or
`custom`) or at least one entry in `attributes` (`data-*`, `aria-*`, `id`,
`class`, or an https / `#fragment` `href`; at most 10).

### Messaging providers

```php
$atlas->messaging->deleteChannel('sms');
$atlas->messaging->deleteChannel('email', force: true);
```

Removing the email provider is refused with a `ConflictException`
(`EMAIL_PROVIDER_IN_USE`, with `meta.features` listing the email-dependent
features that are on) while nothing else would deliver email; `force: true`
removes it anyway.

### OAuth clients

```php
$atlas->oauthClients->update($clientId, ['skip_logout_confirmation' => true]);
```

For a first-party client, `skip_logout_confirmation` makes RP-initiated logout
(`/oauth2/logout` with this client's `client_id` and an exactly registered
`post_logout_redirect_uri`) end the session and redirect straight back with
`state`, without the confirmation page. A third-party client is refused (422).

Rotating a secret keeps the previous one working for `overlap_seconds` (default 24 hours, at
most 7 days), so you can deploy the new secret without downtime. Pass `0` to revoke the old one
now, for example after a leak:

```php
$rotated = $atlas->oauthClients->rotateSecret($clientId, ['overlap_seconds' => 3600]);
$rotated['client_secret'];              // shown once
$rotated['previous_secret_expires_at']; // epoch ms, or null when the old secret already stopped
```

### Platform API: Sign in with Atlas apps

Construct the client with a **platform key** (`psk_…`) to manage apps for your account:

```php
$platform = (new Client(getenv('ATLAS_PLATFORM_KEY')))->platform;

$app = $platform->atlasIdApps->create([
    'name' => 'Acme',
    'redirect_uris' => ['https://acme.com/auth/callback'],
    'post_logout_redirect_uris' => ['https://acme.com/'],
    'backchannel_logout_uri' => 'https://acme.com/auth/backchannel-logout',
    'branding' => ['theme' => 'auto', 'accent_color' => '#4f46e5', 'copy' => ['sign_in_title' => 'Sign in to {app}']],
]);
$app['client_secret'];                               // shown once (confidential apps)

$platform->atlasIdApps->rotateSecret($app['id']);
$logo = $platform->designAssets->upload(file_get_contents('logo.png'), 'logo');
$platform->atlasIdApps->update($app['id'], ['branding' => ['design' => ['logo' => ['assetId' => $logo['asset_id']]]]]);
$platform->apps->list();                             // apps (instances) your platform provisions
```

### Domain moves, logout fan-out and managed email

```php
// Move every URL from the old origin to the new one. dry_run first shows the diff.
$preview = $atlas->instance->moveOrigin(['from' => 'https://old.example.com', 'to' => 'https://example.com', 'dry_run' => true]);
foreach ($preview['changes'] as $c) {
    echo "{$c['object']} {$c['field']}\n";
}

// Send the same logout_token your OIDC apps get to another service.
$atlas->logoutNotifyEndpoints->create(['url' => 'https://api.example.com/atlas/logout']);

// Set your domain up on your own Resend or Brevo account, then publish the returned DNS records.
$managed = $atlas->messaging->createManagedEmail([
    'from_address' => 'Acme <no-reply@example.com>',
    'transport' => 'resend',
    'config' => ['api_key' => getenv('RESEND_API_KEY')],
]);

// A development instance can send sign-in emails through your provider without a verified domain.
$atlas->messaging->enableDevelopmentEmail(['provider_id' => 'mp_123']);
$atlas->messaging->disableDevelopmentEmail();
```

`Webhook::verify` accepts an `atlas-signature` header that carries several signatures separated
by spaces (`v1,<sig> v1,<sig>`). While an endpoint secret rotates, Atlas may sign each delivery
with the old and the new secret; the delivery verifies when any one signature matches.

A rotation stops the old secret at once by default. Pass `overlap_seconds` (at most 7 days) to
keep it signing each delivery next to the new one while you deploy; choose an overlap only once
every receiver accepts several signatures:

```php
$rotated = $atlas->webhooks->endpoints->rotateSecret($endpointId, ['overlap_seconds' => 3600]);
$rotated['previous_secret_expires_at']; // epoch ms, or null when the old secret already stopped
```

### Trusted reverse proxy

If a reverse proxy serves the Frontend API from your own origin, give it a proxy secret. It sends
the secret in `X-Atlas-Proxy-Secret` and the browser's IP in `X-Atlas-Client-IP`; Atlas then uses
that IP for rate limits, lockout, risk signals and audit logs.

```php
$created = $atlas->instanceSecurity->createProxySecret();
$created['secret'];                                   // shown once; store it in your proxy's environment
$atlas->instanceSecurity->getProxySecret();           // set, created_at, previous_expires_at (never the secret)
$atlas->instanceSecurity->createProxySecret(['overlap_seconds' => 3600]); // rotate; the old one works 1 hour
$atlas->instanceSecurity->deleteProxySecret();
```

Platform rotations take an overlap too: `$platform->apps->rotateInstanceSecret($appId, $instanceId, ['overlap_seconds' => 0])`
(default 72 hours), `$platform->atlasIdApps->rotateSecret($id, ['overlap_seconds' => 3600])` (default 24 hours) and
`$platform->atlasIdApps->rotateWebhookSecret($id, ['overlap_seconds' => 3600])` (default 0).

With a platform key, `$platform->atlasIdApps->findUsers($id, $email)` answers a privacy request for one app,
`revokeGrant($id, $grantId)` revokes one person's approval, and `$platform->atlasIdPublisher` claims and verifies
the publisher name shown on consent screens.

### Referral attribution

With referral attribution on, Atlas reports every interactive sign-up to your referral platform before
`user.created` is sent. Users carry `referral` (where attribution stands and, once attributed, who referred them;
null when never reported), `referral_code` and `referral_link`. `referral['status']` is `pending`, `attributed`,
`none`, `failed`, `held` or `flagged`; the platform's own status is `referral['referral_status']`.

```php
$atlas->referrals->update([
    'enabled' => true,
    'provider' => 'webhook', // the Atlas referral protocol; or 'referally' or another built-in adapter
    'endpoint_url' => 'https://referrals.example.com/atlas',
    'signing_secret' => getenv('REFERRAL_SIGNING_SECRET'),
    'security' => ['on_high_risk' => 'hold'],
]);
$atlas->referrals->test();                                    // ['ok' => true, 'http_status' => 404, ...]
$failed = $atlas->referrals->listCalls(['status' => 'failed', 'limit' => 20]);
$atlas->referrals->retryCall($failed['data'][0]['id']);

$atlas->users->update('user_123', ['referral_code' => 'ada-1']); // 409 REFERRAL_CODE_TAKEN if held
$atlas->users->flagReferral('user_123', 'self-referral');

$atlas->referrals->simulate('ACME10');                           // a made-up user; nothing is stored
$atlas->referrals->retryFailed(['error_code' => 'signature_rejected']); // after pasting the new secret
$atlas->referrals->listCalls(['limit' => 1000, 'starting_after' => 'rfc_123', 'error_code' => 'timeout']);
$job = $atlas->referrals->backfill(['since' => '2026-01-01', 'code_metadata_path' => 'public_metadata.referral_code']);
$atlas->referrals->getBackfill($job['id']);                      // running, then completed
$atlas->referrals->getForUser('user_123');                       // the stored result, no call to the platform
$atlas->users->setExternalId('user_123', 'cust_42');             // forwarded to the platform as an alias
```

Enabling without what the provider needs is `422 REFERRALS_NOT_CONFIGURED`. When the platform has not answered by the
time `user.created` is sent, `referral['status']` is `pending` and the `user.referral_resolved` webhook follows with the
final user (`attributed`, `none` or `failed`). `user.referral_attributed` still fires for a late attribution.

Two-way sync: a provider with the `inbound` capability sends referral changes made after sign-up back to Atlas. The
settings report `inbound` (`url` to give the platform, `secret_set`, `endpoint_id`, and `last_event` with `at`, `type`,
`outcome`). Send `inbound_secret` (write-only, 16-512 characters) to store the platform's event secret and
`inbound_endpoint_id` for its subscription id; `null` clears either.

Browser capture: `browser_sources` lists the referral programs whose script or cookie the browser reads (`null` goes
back to the provider's default, reported by `browser_sources_default`), and `consent_provider` picks the consent
manager that decides (`auto`, one manager, or `app`).

To give each user your own id before the first referral call, turn on the `before_user_create` hook. Atlas then sends
your endpoint a signed `POST` (verify it with `Webhook::verify`) of type `user.before_create` for every self-service
sign-up, and stamps the `external_id` and metadata you answer:

```php
$atlas->userHooks->updateBeforeUserCreate([
    'enabled' => true,
    'url' => 'https://app.example.com/hooks/before-create',
    'signing_secret' => getenv('BEFORE_CREATE_SECRET'),
]);
$atlas->userHooks->testBeforeUserCreate(); // ['ok' => true, 'answer' => [...]]
```

### API key spend caps

Give an end-user API key caps in your own units, then meter what your API consumed against them. A refused debit records nothing and is not an exception, so you enforce it inside your own transaction:

```php
use Atlas\ErrorCode;

$key = $atlas->apiKeys->create([
    'subject_type' => 'user', 'subject_id' => 'user_123', 'name' => 'CI',
    'limits' => [
        ['unit' => 'requests', 'amount' => 10000, 'period' => 'day'],   // day/month are UTC calendar windows
        ['unit' => 'usd_cents', 'amount' => 500, 'period' => 'request'], // caps a single debit; "total" never resets
    ],
]);

$debit = $atlas->apiKeys->recordUsage($key['id'], 1, 'requests', idempotencyKey: $requestId);
if (!$debit['allowed']) {                       // $debit['code'] === ErrorCode::LIMIT_EXCEEDED
    return new Response(429, body: 'quota exceeded');
}

$atlas->apiKeys->getUsage($key['id']);           // caps with used/remaining/resets_at + raw counters
$atlas->apiKeys->update($key['id'], ['limits' => null]); // remove every cap
```

Reusing an idempotency key replays the first outcome (`idempotent_replay: true`) without counting again; reusing it with a different unit or amount throws a `ConflictException` with `IDEMPOTENCY_CONFLICT`. `apiKeys->verify()` also returns the key's `name`, `scopes`, `constraints`, `limits` and `usage`. Subscribe to the `api_key.created`, `api_key.updated` and `api_key.limit_reached` webhooks to mirror keys and react to a filled cap.

### Session keep-alive

If your app loads Atlas on only some pages, keep the session's inactivity window moving from your backend:

```php
$session = $atlas->sessions->touch($sessionId);
$session['extended'];        // false when a touch within the last minute already moved it
$session['idle_expires_at']; // epoch ms the session ends from inactivity
```

It never extends past the absolute expiry and never revives an ended session (`ConflictException` with `SESSION_REVOKED`, `SESSION_IDLE_EXPIRED` or `SESSION_EXPIRED`). Calling it on every request is safe.

`users->create()` and `importExport->importUsers()` accept `'notify' => false` (the default), which guarantees the user is sent no email, SMS or push as a consequence of the create.

### All namespaces

`users`, `sessions`, `organizations` (with `memberships`, `invitations`,
`domains`, `groupRoles`, `auditStreams`, `endUsers`, `readerDomain`, plus
settings, design and branding), `roles`, `permissions`, `oauthClients`,
`resourceServers`, `ssoConnections`, `scimTokens`, `domains`, `waitlist`,
`allowlist`, `blocklist`, `attackProtection`, `actorTokens`, `invitations`,
`webhooks`, `signInTokens`, `auditLogs`, `auditEvents`, `jwtTemplates`,
`apiKeys`, `oauthProviders`, `ssoOnboarding`, `scimProvisioning`, `fga`,
`openFga`, `rateLimitPolicy`, `riskBasedMfa`, `botSignals`, `networkAcls`,
`managedWaf`, `logStreams`, `branding`, `design`, `emailTemplates`,
`smsTemplates`, `localizations`, `actions`, `billing`, `messaging`,
`importExport`, `dataSubjectRequests`, `radiusClients`, `ltiPlatforms`,
`instance` (incl. `preflight` and cookie consent), `instanceSecurity`, `tokens`,
`emailAddresses`, `organizationSettingsSchema`, `entitlementOverrides`,
`machines` (incl. enrolment tokens), `connectors`, `directory`, `diagnostics`,
`configChangelog`, `events`, `usage`, `legacyAuthConnection`, `mcp`,
`mcpConnector`, `notifications` (with `categories` and `templates`),
`orgLdapConnections`, `pca`, `redirectUrls`, `sessionExchangeApps`, `tickets`,
`captcha`, `testing` (development-instance helpers), and `platform` (`apps`,
`atlasIdApps`, `designAssets`; needs a platform key).

Anything not wrapped can be called directly with `$atlas->http->request('GET', '/v1/…')`.

## Policy acceptance

Terms of Service, privacy policies and any other policy your users accept at sign-up (and again
at sign-in when a new version takes effect). A version goes draft, published (with an
`effective_at`), retired; each acceptance is kept as evidence.

```php
$atlas->policies->create(['key' => 'tos', 'title' => 'Terms of Service', 'applies_to' => 'sign_in_and_sign_up']);
$atlas->policies->versions->create('tos', ['version' => '2026-10-24', 'content_markdown' => "# Terms\n..."]);
$atlas->policies->versions->publish('tos', '2026-10-24', ['effective_at' => '2026-11-01T00:00:00Z']);
$atlas->policies->listAcceptances('tos', ['version' => '2026-10-24', 'limit' => 50]);
$csv = $atlas->policies->exportAcceptancesCsv('tos');
$atlas->policies->forUser('user_123');                          // standing on every policy, incl. needs_acceptance
$atlas->policies->settings->update(['session_claim' => true]);  // put `pol` on session tokens
```

With the session claim on, `$result->policies()` / `$result->acceptedPolicyVersion('tos')` on a
`VerifyResult` read the `pol` claim. The `user.policy_accepted` and `policy.version_published`
webhook events have the constants `Constants::WEBHOOK_EVENT_USER_POLICY_ACCEPTED` and
`Constants::WEBHOOK_EVENT_POLICY_VERSION_PUBLISHED`. `users->recordConsent()` takes the
acceptance evidence (`policy_version_id`, `content_sha256`, `display`, `scrolled_to_end`,
`view_ms`, `locale`).

### Serving policies from your own system (policy source)

Point Atlas at a CMS or service with `$atlas->policySource->update(['enabled' => true,
'endpoint_url' => ..., 'signing_secret' => ...])` (the secret is write-only), then `test()` /
`sync()`. In your service, verify Atlas's signed `GET {endpoint}/policies[?key=...]` and answer a
feed:

```php
use Atlas\PolicySource\PolicySourceProtocol;

$v = PolicySourceProtocol::verifyFeedRequest($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], getallheaders(), $secret);
if (!$v->verified) { http_response_code(401); return; }
header('Content-Type: application/json');
echo json_encode(['object' => 'policy_list', 'policies' => buildPolicies($v->key)]); // one policy when $v->key is set

// When a policy changes, tell Atlas (it re-fetches; nothing in the event is trusted as content):
$signed = PolicySourceProtocol::signEvent(['type' => 'policy.published', 'key' => 'tos', 'version' => '2026-10-24'], $secret);
// POST $signed->body to the webhook_url with Content-Type: application/json and the $signed->headers
```

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

## Back-channel logout

When a user signs out, Atlas POSTs a signed `logout_token` to your app's
`backchannel_logout_uri` (Sign in with Atlas apps and session-exchange apps).
Verify it, end the matching local sessions, and answer 200:

```php
use Atlas\Verify\BackchannelLogout;
use Atlas\Verify\InvalidLogoutTokenException;

$logout = new BackchannelLogout(
    issuer: 'https://atlasauth.net',                      // your instance's frontend API origin for session-exchange apps
    jwksUrl: 'https://atlasauth.net/.well-known/jwks.json',
    clientId: getenv('ATLAS_CLIENT_ID'),                  // the token's audience
    jtiStore: $myJtiStore,                                // optional: refuse replays (see below)
);

// POST /auth/backchannel-logout — plain PHP; adapt to your framework's request/response.
header('Cache-Control: no-store');
try {
    $claims = $logout->verify($_POST['logout_token'] ?? '');
    if ($claims->sid !== null) {
        $sessions->deleteByAtlasSessionId($claims->sid);  // one session
    } else {
        $sessions->deleteByAtlasUserId($claims->sub);     // every session of the user
    }
    http_response_code(200);
} catch (InvalidLogoutTokenException $e) {
    http_response_code(400);
}
```

The verifier checks the RS256 signature, `iss`, `aud`, `iat`, `exp`, `jti`, the
back-channel logout `events` claim, that `sid` or `sub` is present, that there
is no `nonce`, and the `logout+jwt` type. To refuse replays, implement
`Atlas\Verify\JtiStore` (one method, `add(string $jti, int $ttlSeconds): bool`,
returning false for an id it has seen) over a store all your PHP processes
share — Redis `SET key 1 NX EX ttl` or a unique database column.
`InMemoryJtiStore` is included for long-running workers and tests.

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

### Better Auth bridge (Laravel, Symfony)

During a migration from Better Auth, both integrations ship the same bridge as the PSR-15
`BetterAuthBridgeMiddleware`, built on `SessionBridge`: GET / HEAD only; a request with a Better Auth
session cookie and no Atlas session is exchanged once and answered `303` back to the same URL with
`__session` / `__atlas_rt` set; if a second factor is owed it redirects to your sign-in URL with
`__atlas_attempt` / `__atlas_status`; Better Auth cookies are cleared only on a final refusal (a closed
bridge, a 429 or an outage keeps them). The end user's IP is the framework's own client IP, so your
trusted-proxy setup applies.

Laravel: set `ATLAS_SECRET_KEY` and (optionally) `ATLAS_BETTER_AUTH_SIGN_IN_URL`,
`ATLAS_BETTER_AUTH_COOKIE_PREFIX`, `ATLAS_BETTER_AUTH_COOKIE_NAME`, `ATLAS_BETTER_AUTH_COOKIE_DOMAIN`
(the `atlas.better_auth_bridge` config key), then put the `atlas.better_auth_bridge` alias ahead of
`atlas.auth`, or add `Atlas\Laravel\BetterAuthBridge` as global middleware (before cookie encryption is fine:
it reads the raw `Cookie` header):

```php
Route::middleware(['atlas.better_auth_bridge', 'atlas.auth'])->group(/* ... */);
```

Symfony: enable it under the `atlas` key; it registers `Atlas\Symfony\BetterAuthBridgeSubscriber` on
`kernel.request` (priority 64, ahead of the firewall):

```yaml
# config/packages/atlas.yaml
atlas:
    secret_key: '%env(ATLAS_SECRET_KEY)%'
    better_auth_bridge:
        enabled: true
        sign_in_url: /sign-in      # optional
        # cookie_prefix, cookie_name, cookie_domain, secure: false (plain-http dev only)
```

## PSR-15 middleware (Slim, Mezzio, any pipeline)

For a framework without a bespoke integration — or none at all — the SDK ships a
standard PSR-15 middleware under `Atlas\Psr15` (install `psr/http-server-middleware`).
It wraps the same framework-free `SessionVerifier`, so there is no second copy of
the JWT/JWKS crypto. Verification is local — no network on the hot path.

```php
use Atlas\Psr15\AtlasAuthenticationMiddleware;
use Atlas\Verify\SessionVerifier;

$verifier = new SessionVerifier(
    jwksUrl: getenv('ATLAS_JWKS_URL'),
    issuer: getenv('ATLAS_ISSUER'),
);

// Slim 4: add it ahead of the routes it should protect.
$app->add(new AtlasAuthenticationMiddleware($verifier));
```

It reads an `Authorization: Bearer <jwt>` header or the `__session` cookie off the
PSR-7 request (header first). On success it attaches the verified result as the
`atlas` request attribute and calls the next handler; a missing/invalid token
short-circuits with a `401` JSON body and the handler never runs.

```php
$result = $request->getAttribute('atlas'); // Atlas\Verify\VerifyResult
$result->protect(['permission' => 'billing:write']); // throws ForbiddenException on fail
$userId = $result->claims['sub'];
```

The attribute name is the fourth constructor argument (default `atlas`); the 401
response is built with auto-discovered Guzzle PSR-17 factories, or pass your own
`ResponseFactoryInterface` / `StreamFactoryInterface` as the second and third
arguments.

## Testing

The SDK ships a PHPUnit suite that runs entirely against a mocked PSR-18 client
— no network. Run it with:

```bash
composer install
composer test
```

## License

MIT — see [LICENSE](LICENSE).
