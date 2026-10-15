# Changelog

Versions are published from git tags.

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
