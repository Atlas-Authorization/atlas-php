# Changelog

Versions are published from git tags.

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
