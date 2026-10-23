# Changelog

Versions are published from git tags.

## 0.15.0 — 2026-10-24

### Added

- **Migrate from Better Auth: sealed-bundle imports.** `$client->betterAuthImports`: `getPublicKey`, `rotatePublicKey`, `create`, `list`, `get`, `uploadPart` (a `text/plain` part), `finalize`, `dryRun`, `apply` (resumes a failed import), `delete`, `listJwks`, `deleteJwk`. Helpers: `upload($bundle, $options, $partBytes, $onPart)` creates the import, uploads the bundle in line-aligned parts of at most 8 MiB (`BetterAuthImports::splitBundleParts`, `PART_BYTES`) and finalizes it; `waitFor($id, $intervalSeconds, $timeoutSeconds, $onProgress)` polls until the import leaves `analyzing` / `queued` / `applying`.
- **Better Auth session bridge.** `$client->betterAuthBridge`: `get` / `update` / `delete` (migration window, cookie prefix, live fallback; the old app's secret is write-only) and `exchange($token, $clientIp, $userAgent, $remember)` on the secret-key Backend API route, forwarding the end user's IP and user agent (`X-Atlas-Client-IP`, `X-Atlas-Client-User-Agent`). `Atlas\BetterAuth\BetterAuth::exchange()` classifies the outcome (`ExchangeResult`: `session`, `needs_mfa`, `refused` with the `meta.bridge_outcome`; never throws for a refusal or an outage). `Atlas\BetterAuth\SessionBridge` turns a request's cookies into a `BridgeDecision` (Atlas cookies to set, Better Auth cookies to clear only on a final refusal, `continuationUrl()` for a second factor); `BetterAuth::cookieNames()`, `readSessionCookie()`, `BridgeCookie::toHeader()`. PSR-15: `Atlas\Psr15\BetterAuthBridgeMiddleware` (GET/HEAD only; 303 to the same URL signed in, or to your sign-in page when a second factor is owed), also accepted by `AtlasAuthenticationMiddleware` as `$betterAuthBridge`. `Http::request()` takes per-call `$headers` (never the `Authorization` header).
- **Better Auth bridge for Laravel and Symfony.** `Atlas\Laravel\BetterAuthBridge` (route alias `atlas.better_auth_bridge`, config key `atlas.better_auth_bridge`) and `Atlas\Symfony\BetterAuthBridgeSubscriber` (`kernel.request`, enabled under `atlas.better_auth_bridge`), behaving like the PSR-15 middleware through the new shared `SessionBridge::plan()` / `BridgePlan`; the client IP comes from `$request->ip()` / `getClientIp()` (trusted proxies apply).
- **Better Auth JWTs during the cutover.** `new SessionVerifier(..., legacyIssuers: [new LegacyIssuer($issuer, $until, jwks: | jwksUrl:, audience:, algorithms:, resolveSubject:)])`: a token from that issuer, inside its window, is verified against that issuer's keys only (EdDSA/Ed25519, ES256, ES512, RS256, PS256 through ext-openssl and ext-sodium; `exp` required) and its `sub` is mapped to the Atlas user id through `external_id` (Backend API lookup with `secretKey` / `bapiBaseUrl`, cached 5 minutes per process, at most 10 000 entries; failures are not cached). The claims carry `external_id` and `legacy`.

## 0.14.0 — 2026-10-24

### Added

- **DPoP (RFC 9449) on the access-token verifier.** A DPoP-bound token (`cnf.jkt`) is accepted only with the DPoP scheme and a valid proof (never as Bearer): `typ` `dpop+jwt`, `Dpop::ALGS` (ES256/384/512, RS256/384/512, PS256/384/512, EdDSA via ext-sodium), public embedded JWK, signature, `htm`, normalised `htu` (`Dpop::normalizeHtu()`; `publicOrigin` for path-only URLs), `iat` window, `ath`, `jti` replay via a `JtiStore` (`InMemoryJtiStore` by default; pass a shared one under PHP-FPM), RFC 7638 `Dpop::jwkThumbprint()` match. `requireDpop` refuses unbound tokens; `dpopNonces` (`HmacDpopNonceSource`, stateless across processes) demands a server nonce (`use_dpop_nonce`, `AccessTokenResult::$dpopNonce` for the `DPoP-Nonce` header). `verify($authorization, $scopes, $method, $url, $dpop)` and `verifyRequest(RequestInterface)`; challenges use the `DPoP` scheme with `algs`. `AtlasAccessTokenMiddleware` checks the proof against the PSR-7 request and sets `DPoP-Nonce`. Metadata advertises `dpop_signing_alg_values_supported` and takes `dpopBoundAccessTokensRequired`.
- **Billing: several subscriptions per subject, add-ons, Chargebee / Autumn / Commet.** `billing->getSubscription`, `syncPlanEntitlements`, `getSettings` / `updateSettings` (limit merge `max` / `sum`), `checkFeature`; plans take `kind` (`base` / `addon`), `limits`, `seat_feature_id`; usage takes `feature` and `subscription_id`; provider config keys for `chargebee`, `autumn`, `commet` documented on `setProvider`. `Constants::BILLING_PROVIDERS`.
- **Custom OAuth / OIDC providers.** `customOAuthProviders`: `list`, `get`, `create`, `update`, `delete`, `discover`, `previewProfileMapping` (ad hoc or by id), `listSigningKeys` / `rotateSigningKey` / `retireSigningKey`. OIDC SSO connections gain `previewProfileMapping` and the same signing-key methods.
- **Actions gallery.** `actionGallery` (`list`, `get`, `install`, `installs`, `update`) and `actionTemplates` (`list`, `create`, `get`, `update`, `delete`, `addVersion`, `submitVersion`, `deleteVersion`, `publish`, `unlist`).
- **Session custom data.** `sessions->setCustomData` (replace; null clears) and `updateCustomData` (merge; null removes a key). `Constants::WEBHOOK_EVENT_SESSION_UPDATED`.
- **Sign-in settings.** `instanceSecurity->setWalletRpcSecret($chainId, $url)`; rate-limit `rules` pass through `rateLimitPolicy->update`; `instance->setAccountLinkingTrust`, `instance->setCaptchaConfig`; `Constants::CAPTCHA_PROVIDERS` (incl. `captchafox`, `vercel_botid`), `Constants::LINKING_TRUST_LEVELS`.
- **Vercel BotID attestations.** `BotId::createAttestation($secret, $isBot, ...)` signs a verdict in Atlas's `atlas_botid.v1` format.
- **Enterprise SSO.** `ssoConnections->getSamlEncryptionKey`, `createSamlEncryptionKey` (create or rotate), `retirePreviousSamlEncryptionKey`, `deleteSamlEncryptionKey`; the SAML Single Logout and encrypted-assertion fields pass through create/update. Attack protection `dormant_accounts`; `Constants::WEBHOOK_EVENT_USER_REACTIVATED`, `Constants::EMAIL_TEMPLATE_ACCOUNT_REACTIVATED`.
- **Atlas as OAuth provider.** `instanceSecurity->getSigningKeyProvider` / `setSigningKeyProvider` / `removeSigningKeyProvider` (AWS KMS, Google Cloud KMS, Azure Key Vault); OAuth client `access_token_format` / `token_lifetimes` and resource-server `access_token_format`, refresh lifetimes and `introspection_client_ids` pass through.
- **Opaque access tokens.** `new AccessTokenVerifier(..., introspection: ['clientId' => …, 'clientSecret' => …])` (or an `Introspector`) validates non-JWT access tokens at Atlas's RFC 7662 endpoint, cached by token hash (TTL, negative TTL, never past `exp`, bounded; per process); `introspectJwts` checks JWTs live too.
- **Device sign-in approval.** `new DeviceAuthorizations($frontendApi, $sessionToken)`: `lookup`, `approve`, `deny` with the user's session token; `DeviceAuthorizationException` (`needsReverification()`, `isInvalidCode()`); `formatUserCode`, `userCodeFromUrl`.

### Changed

- Without `introspection`, a token that is not a JWT is refused with "configure `introspection` to accept opaque tokens" instead of "malformed".

## 0.13.0 — 2026-10-23

### Added

- **App roles.** `$client->appRoles`: `list`, `create`, `get`, `update`, `delete`, `listUsers`, `getForUser`, `setForUser` (replace, or add/remove), `removeFromUser`. `VerifyResult::hasAppRole()` / `hasAppPermission()` and the `appRole` / `appPermission` conditions of `has()` / `protect()` read the `app_roles` / `app_permissions` claims (no active organization needed).
- **Organization teams and custom roles.** `organizations->teams` (list, create, get, update, delete, members list/add/remove) and `organizations->roles` (the roles an org can assign; create/update/delete its own, `$reassignTo`). `organizations->getBySlug()`, `organizations->checkSlug()`, `organizations->invitations->resend()`.
- **User search and timed bans.** `users->list()` documents the search filters (`query`, `email_address`, `username`, `phone_number`, `external_id`, `banned`, `email_verified`, `organization_id`, `created_after` / `created_before`, `order_by`, `offset`, `include_total_count`); `users->ban($id, ['expires_in_seconds' => ...])`.
- **API key controls.** Create and update bodies take `enabled`, `rate_limit`, `remaining`, `prefix`, `expires_in_seconds`; `apiKeys->verify($secret, $requiredScopes)` and a refusal of a live key carries `code` (`insufficient_scope`, `rate_limited`, `usage_exhausted`).
- **Billing providers (bring your own key).** `billing->listProviders()`, `getProvider()`, `setProvider()`, `testProvider()`, `deleteProvider($force)`; `billing->changeSubscription()`, `cancelSubscription($immediately)`, `restoreSubscription()`. Plans take `provider`, `provider_price_id`, `annual_price_id`, `annual_amount`.
- **OAuth server administration.** `instanceSecurity->listSigningKeys()` / `rotateSigningKey()` (`alg` RS256 or ES256); `$client->oauthInitialAccessTokens` (list, create, revoke). OAuth clients take `disabled`, `require_pkce`, `subject_type`, `sector_identifier_uri`, `secret_expires_in_seconds`; resource servers take `signing_alg` and `publish_resource_metadata`.
- **Access-token verification for your API.** `Atlas\Verify\AccessTokenVerifier` checks an Atlas-issued OAuth access token locally (issuer, expiry, `token_use`, audience, scopes) and returns the RFC 6750 / RFC 9728 `WWW-Authenticate` challenge on refusal; `Atlas\Psr15\AtlasAccessTokenMiddleware`; `buildWwwAuthenticate()`, `protectedResourceMetadata()`, `protectedResourceMetadataPath()`. DPoP-bound tokens are refused.
- `scimTokens->update()`, `userHooks->listBeforeUserCreateCalls()`, `referrals->getCall()`.

### Changed

- Session and logout-token verification accept **ES256** as well as RS256 (`Atlas\Verify\SigningKeys::ALGS`), and nothing else; each published key is bound to the one algorithm its type allows.

## 0.12.0

### Added

- Referral settings carry browser capture: `browser_sources` (the referral programs whose script or cookie the browser reads; null on update resets to the provider's default), `browser_sources_default`, and `consent_provider` (`auto`, one consent manager, or `app`).

## 0.11.0

### Added

- **Two-way referral sync.** Referral settings carry two-way sync: `inbound` (`url`, `secret_set`, `endpoint_id`, `last_event`) on reads, write-only `inbound_secret` and `inbound_endpoint_id` on updates (null clears), and provider capability `inbound`.

## 0.10.0

- **Referral attribution v2.** `referrals->retryFailed(array $filter = [], ?string $idempotencyKey = null)` (`POST /v1/instance/referrals/calls/retry_failed`; `kind`, `error_code`; returns `requeued`), `referrals->simulate(?string $code = null)` (`POST /v1/instance/referrals/simulate`; `call_id`, `customer_ref`, `result`, `response`, `error`), `referrals->backfill(array $body, ?string $idempotencyKey = null)` and `referrals->getBackfill(string $id)` (`POST /v1/instance/referrals/backfill`, `GET /v1/instance/referrals/backfill/:id`; body `since`, `codes`, `code_metadata_path`; job `users_scanned`, `users_skipped`, `calls_queued`, `truncated`, `next_since`, `calls`), and `referrals->getForUser(string $userId)` (`GET /v1/users/:id/referral`).
- **Settings.** The referral settings gain `tracking_token`, `code_lookup_url`, `signup_code_field`, `forward_lifecycle`, `mirror_to_public_metadata` and `include_referral_claims` (read and write). `provider` can be `referally`. Provider capabilities gain `alias`, `user` and `ping`. `connection` gains `last_ping` and `alert` (`signature_rejected` or `connection_paused`). `referrals->test()` returns `method` (`ping` or `probe`), `ping` and `warnings`.
- **Call log.** `listCalls` takes `error_code` and `starting_after`; `limit` goes up to 1000. Kinds add `alias`, `user` and `ping`; calls carry `latency_ms`, `error_code`, `response`, `highlights`, `request_id` and `batch_id`.
- **Users.** New `users->setExternalId(string $id, ?string $externalId)` (`PATCH /v1/users/:id`; null clears; with referrals on, the change is forwarded to the platform as an alias). The user `referral` gains `partner_external_ref`, `referral_status` and `notes`. New event `user.referral_resolved` (`data` is the full user).
- **before_user_create hook.** New `userHooks` namespace: `getBeforeUserCreate()`, `updateBeforeUserCreate(array $body, ?string $idempotencyKey = null)` and `testBeforeUserCreate()` (`/v1/instance/hooks/before_user_create`). The hook call is signed like a webhook with type `user.before_create`; verify it with `Webhook::verify`.
- **Preflight** can report `referrals_secret_invalid`, `referrals_program_inactive`, `referrals_unreachable`, `referrals_clock_skew` and `referrals_external_id_unset`.
- **Changed on the server.** `referral['status']` is now where attribution stands (`pending`, `attributed`, `none`, `failed`, `held`, `flagged`); the platform's own status moved to `referral_status`. `user.created` carries `status: "pending"` while the platform has not answered.
- Compatible: new methods only.

## 0.9.0

- **Referral attribution settings.** New `referrals` namespace: `get()`, `update(array $body, ?string $idempotencyKey = null)` (`GET`/`PATCH /v1/instance/referrals`; `null` clears; nested `security` with `challenge_referred_signups`, `on_high_risk`, `hold_until_email_verified`, `max_referred_signups_per_ip_hour`; `signing_secret` and `api_key` are write-only), `test()` (`ok`, `http_status`, `latency_ms`, `error`), `listCalls(array $params = [])` (`limit`, `status`, `kind`, `user_id`) and `retryCall(string $id, ?string $idempotencyKey = null)`.
- **Users.** User objects carry `referral` (`partner_id`, `referrer_external_id`, `referrer_user_id`, `matched_via`, `status`, `risk_level`, `flagged`, or null), `referral_code` and `referral_link`. `users->create` accepts `referral` (`code`, `click_id`, `anon_id`) and `referral_code`; `users->update` accepts `referral_code` (null clears). New `users->flagReferral(string $id, ?string $reason = null, ?string $idempotencyKey = null)`.
- **Webhooks.** New event `user.referral_attributed` (the referral platform answered after `user.created`).
- **Error codes.** `ErrorCode::REFERRALS_NOT_CONFIGURED` (422) and `ErrorCode::REFERRAL_CODE_TAKEN` (409). Preflight can report `referrals_enabled_unverified` and `referrals_without_bot_protection`.
- Compatible: new methods only.

## 0.8.0

- **Trusted reverse proxy secret.** `instanceSecurity->getProxySecret()`, `createProxySecret(?array $body = null)` (`['overlap_seconds' => int]` on a rotation: default 86400, `0` stops the old secret now, at most 604800; the response carries `secret`, shown once, and `rotated`) and `deleteProxySecret()`. The view carries `set`, `created_at`, `previous_expires_at`, `secret_header` and `client_ip_header`.
- **Webhook secret rotation with an overlap.** `webhooks->endpoints->rotateSecret($id, ?array $body = null)` takes `['overlap_seconds' => int]` (default 0, at most 604800). The response carries `overlap_seconds` and `previous_secret_expires_at`; endpoint objects carry `previous_secret_expires_at` while an overlap is open.
- **Per-instance platform rotation with an overlap.** `platform->apps->rotateInstanceSecret($id, $instanceId, array|string|null $bodyOrIdempotencyKey = null, ?string $idempotencyKey = null)` takes `['overlap_seconds' => int]` (default 259200, `0` revokes the prior keys now); the 0.7 call with the idempotency key as the third argument still works. The response carries `app_id`, `instance_id`, `overlap_seconds` and `previous_keys_expire_at`. Without a body the server now keeps the prior keys for 72 hours; pass `['overlap_seconds' => 0]` for the old behaviour.
- **Sign in with Atlas apps.** `platform->atlasIdApps->rotateSecret($id, ?array $body = null)` (default 24 hours) and `rotateWebhookSecret($id, ?array $body = null)` (default 0) take `['overlap_seconds' => int]`. The `AtlasIdApp` type gains `previous_secret_expires_at`, `secret_issued_at`, `webhook_previous_secret_expires_at` and `overlap_seconds`.

## 0.7.0

- **OAuth client secret rotation with overlap.** `oauthClients->rotateSecret($id, ?array $body = null, ?string $idempotencyKey = null)` takes `['overlap_seconds' => int]` (default 86400, `0` revokes the old secret now, at most 604800). The response carries `client_secret`, `overlap_seconds` and `previous_secret_expires_at`; client objects carry `previous_secret_expires_at`.
- **Development email sender.** `messaging->getDevelopmentEmail()`, `enableDevelopmentEmail($body)` (`provider_id`, or `transport` + `config`) and `disableDevelopmentEmail()`.
- **Managed email on your own provider account.** `messaging->createManagedEmail()` takes `transport` (`resend` | `brevo`) with `config.api_key`, or `provider_id`. Responses carry `backend`, `provider_id` and `feedback_webhook`; `limits` is now `null` and `reputation` is `your_provider_account`; delete returns `provider_removed`.
- **Provider mode.** The email provider body type accepts `mode` (`standard` | `development`); the `MessagingProvider` type gains `id` and `mode`.
- **Allowed origins with an integration.** `instance->update()` and the platform app, instance and accountless-app bodies accept `allowed_origins` entries `['origin' => …, 'integration' => 'redirect' | 'embedded' | 'auto' | null]`. Instance and platform app objects carry `allowed_origin_integrations`, `issuer`, `discovery_url` and `jwks_uri` (and `frontend_api_host` on platform apps).
- Responses also carry `integration` and `checks[].applies` on login readiness, `verification` (`status`, `strategy`) on email addresses, and `audiences` on branding.
- **Error codes.** `ErrorCode` adds `READ_ONLY_FIELD`, `DEVELOPMENT_EMAIL_NOT_ALLOWED`, `DEVELOPMENT_EMAIL_UNAVAILABLE`, `DEVELOPMENT_EMAIL_LIMIT_REACHED` and `DEVELOPMENT_EMAIL_TEMPLATE_NOT_ALLOWED`.
- **Webhooks, several signatures.** `Webhook::verify()` accepts an `atlas-signature` header with several space-separated `v1,<signature>` entries and verifies when any one matches (each compared in constant time). A single signature works as before.

## 0.6.0

- **Back-channel logout, retryable failures.** `InvalidLogoutTokenException` gains `public readonly bool $retryable`, true for `no_keys`: the issuer's keys could not be fetched (a network error, a JWKS outage, or a just-rotated key whose refetch failed). Answer `503` with `Retry-After` then, so Atlas retries the delivery, and `400` otherwise. The exception's code is now `503` for `no_keys` (still `400` for every other reason). A token whose `kid` is unknown after a failed JWKS refetch is now `no_keys` (was `invalid_signature`).
- **Webhook verification.** `Atlas\Webhook\Webhook::verify($body, $headers, $secret, $nowMs = null)` returns a
  `WebhookVerification` (`verified`, `reason`, `event`, `deliveryId`, `timestamp`). `deliveryId` is the signed
  `atlas-id` header, the same on every retry of one event: deduplicate on it. Accepts `getallheaders()` or
  `$_SERVER`-style (`HTTP_ATLAS_ID`) headers.
- **Domain moves.** `$atlas->instance->moveOrigin(['from' => …, 'to' => …, 'dry_run' => true])` rewrites every URL on one origin to another; `$platform->atlasIdApps->moveOrigin($id, $body)` does it for one app.
- **Connections and integrations.** `instance->connections()`, and `instance->get/set/deleteChatlyIntegration()` and `…ForumulateIntegration()`.
- **Logout fan-out.** New `logoutNotifyEndpoints` resource: `list`, `create`, `get`, `update`, `delete`, `listDeliveries`.
- **Managed email.** `messaging->getManagedEmail()`, `createManagedEmail($body)`, `verifyManagedEmail()`, `deleteManagedEmail($force)`.
- **Back-channel logout logs.** `oauthClients->listBackchannelDeliveries()` / `testBackchannelLogout()` and the same on `platform->atlasIdApps`.
- **Sign in with Atlas apps.** `platform->atlasIdApps->preflight()`, `findUsers($id, $email)`, `revokeGrant($id, $grantId)`, `rotateWebhookSecret($id)`; the `AtlasIdApp` shape gains `webhook_url`, `webhook_events`, `webhook_active`, `webhook_secret`.
- **Publisher.** `platform->atlasIdPublisher->get / set / setSkipConsent / delete / verify`, and `platform->configChangelog($params)`.
- The `CookieConsent` shape gains `culture` and `declared_cookies`; `PreflightReport` gains `sdk_versions_seen`.

## 0.5.1

- **Captcha is bring-your-own-keys.** `ErrorCode::CAPTCHA_KEYS_REQUIRED` (422) and `ErrorCode::CAPTCHA_IN_USE` (409, `meta.flows`).
- `instanceSecurity->setCaptchaSecret($secret, $provider)` stores the secret for a named provider before captcha is turned on.
- `instanceSecurity->deleteCaptchaSecret($provider, force: true)` removes a secret a flow uses and turns those flows off (`flows_turned_off`).

## 0.5.0

All changes are backward compatible: new fields are optional or nullable and the new parameter defaults to the old behaviour.

### Added

- OAuth clients: `skip_logout_confirmation` (bool) typed on `oauthClients->create()` and accepted by `oauthClients->update()`; client arrays carry `skip_logout_confirmation` (false by default). First-party clients only (a third-party client is refused with 422 `VALIDATION_FAILED`, param `skip_logout_confirmation`). When true, RP-initiated logout with the client's `client_id` and an exactly registered `post_logout_redirect_uri` ends the session and redirects straight back with `state`, without the confirmation page.
- Cookie consent: `instance->setCookieConsent()` accepts an optional `settings_link` (`CookieSettingsLinkInput`: `label`, `preset`, `attributes`; `null` or omitted = no link). The cookie consent array carries `settings_link` (`CookieSettingsLink`: `label`, `preset`, `attributes`, `rendered_attributes`, or `null`).
- `Instance::COOKIE_SETTINGS_LINK_PRESET_COOKIEMUNCH`, `COOKIE_SETTINGS_LINK_PRESET_COOKIEBOT`, `COOKIE_SETTINGS_LINK_PRESET_ONETRUST`, `COOKIE_SETTINGS_LINK_PRESET_CUSTOM` and `Instance::COOKIE_SETTINGS_LINK_PRESETS`. `preset` stays a string, so newer presets decode unchanged.
- `messaging->deleteChannel($channel, $force = false)` — `force: true` sends `?force=true` to remove the email provider even while email-dependent features are on (otherwise refused with 409 `EMAIL_PROVIDER_IN_USE`, `meta.features` listing them).
- `ErrorCode::EMAIL_PROVIDER_IN_USE`.
- Sessions: session arrays (`sessions->get()`, `sessions->list()`, `sessions->touch()` and `users->listSessions()` items) are documented to carry `idle_expires_at` (epoch ms, same value as `abandon_at`), `last_active_organization_id` and `impersonated_by` (string or null).

## 0.4.0

### Added

- `sessions->touch($id)` — `POST /v1/sessions/{id}/touch`, backend session keep-alive. Returns the session plus `extended` (bool). Session arrays now carry `idle_expires_at` (epoch ms).
- API key spend caps: `apiKeys->create()` and `apiKeys->update()` are typed for `limits` (list of `{unit, amount, period: 'request'|'day'|'month'|'total'}`, or `null` to clear). API key arrays carry `limits` and `usage` (`[{unit, period, amount, used, remaining, window_start, resets_at}]`).
- `apiKeys->recordUsage($id, $amount, $unit, $idempotencyKey = null)` — `POST /v1/api_keys/{id}/usage`, returns an `api_key_usage_debit` (`key_id`, `unit`, `amount`, `allowed`, `code`, `exceeded`, `usage`, `counters`, `idempotent_replay`).
- `apiKeys->getUsage($id)` — `GET /v1/api_keys/{id}/usage`, returns an `api_key_usage` (`key_id`, `limits`, `usage`, `counters`).
- `apiKeys->verify()` valid results carry `name`, `scopes`, `constraints`, `limits` and `usage`.
- `notify` (bool) typed on `users->create()` and `importExport->importUsers()`.
- Webhook events `api_key.created`, `api_key.updated` and `api_key.limit_reached`; the `api_key` payload carries `name`, `claims`, `scopes`, `constraints`, `limits`, `max_token_lifetime_seconds`, `expires_at`, `created_at` and `updated_at`.
- `ErrorCode::LIMIT_EXCEEDED`, `SESSION_REVOKED`, `SESSION_IDLE_EXPIRED`, `SESSION_EXPIRED`.

## 0.3.0

### Added

- Full Backend API and platform API coverage: every operation in the public API reference now has a method.
- `platform` namespace (platform key): `apps` (provision, archive, transfer, claim, rotate secrets, instances), `atlasIdApps` (Sign in with Atlas apps, incl. post-logout redirect URIs, back- and front-channel logout URIs, branding with copy and design, secret rotation, design preview) and `designAssets` (image uploads).
- `design` namespace: read, replace, patch and preview the sign-in page design, upload/list/delete design images, and the design catalog. The design array is fully typed for static analysers. Organization designs via `organizations->getDesign()` / `replaceDesign()` / `updateDesign()`.
- Raw-bytes uploads: `Http::request()` accepts `rawBody` + `contentType`; `design->uploadAsset()` and `platform->designAssets->upload()` send image bytes with their content type (detected from the bytes when not given).
- `instance->preflight()` (typed findings: severity, code, message, fix, subject) and cookie-consent settings (`getCookieConsent`, `setCookieConsent`, `deleteCookieConsent`).
- Strict request bodies: `new Client($key, strict: true)` sends `Atlas-Strict: true` on every write; `withStrict(false)` overrides it per call; `onUnknownFields:` reports fields a write sent that the endpoint ignored (`Atlas\UnknownFields`).
- `Atlas\Verify\BackchannelLogout` verifies OpenID Connect back-channel logout tokens, with optional replay protection through `JtiStore` (`InMemoryJtiStore` included).
- `Atlas\ErrorCode` constants (`UNKNOWN_FIELD`, `EMAIL_DELIVERY_NOT_CONFIGURED`, `EMAIL_PROVIDER_REQUIRED`, `PLAN_IN_USE`, `WRONG_KEY_SURFACE`, `INVALID_KEY`, ...).
- `oauthProviders->list()` accepts `provider` and `configured` filters; `upsert()` is typed for `display_name`, `icon_url`, `mode` and wallet `settings` (null clears a setting).
- Webhook endpoints: `update`, `revealSecret`, `rotateSecret`, `test`; `webhooks->redeliver()`; `webhooks->deliveries()` accepts `limit`.
- `entitlementOverrides`, a typed organization policy (`Organizations::POLICY_FIELDS`, with pass-through for newer keys).
- New namespaces: `auditEvents`, `captcha`, `configChangelog`, `connectors`, `directory`, `diagnostics`, `emailAddresses`, `events`, `legacyAuthConnection`, `machines`, `mcp`, `mcpConnector`, `notifications`, `openFga`, `orgLdapConnections`, `organizationSettingsSchema`, `pca`, `redirectUrls`, `sessionExchangeApps`, `testing`, `tickets`, `usage`; and many new methods on `users`, `organizations`, `billing`, `dataSubjectRequests`, `invitations`, `waitlist`, `sessions`, `signInTokens`, `apiKeys` and `oauthClients`.

## 0.1.x

- Backend API client, session-token verification, Laravel, Symfony and PSR-15 integrations, typed messaging provider config.
