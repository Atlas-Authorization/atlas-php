<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/privacy*` — the data-rights executor surface (an orchestrator opens cases, looks subjects up, tracks and cancels
 * requests; scopes `privacy:execute`, `privacy:grants:revoke`), and `/v1/instance/privacy_orchestrator` (who Atlas opens cases at).
 * Verify what Atlas sends you with {@see \Atlas\Privacy\PrivacyExecutor}.
 */
final class Privacy extends Resource
{
    /**
     * The executor's capabilities and request vocabulary.
     *
     * @return array<string,mixed>
     */
    public function discover(): array
    {
        return $this->http->request('GET', '/v1/privacy');
    }

    /**
     * One orchestrator preset (defaults for a known provider).
     *
     * @return array<string,mixed>
     */
    public function preset(string $id): array
    {
        return $this->http->request('GET', '/v1/privacy/presets/' . rawurlencode($id));
    }

    /**
     * Find the subject: `{emails?: [..], atlas?: {iss?, sub?, ext?, principal?}}` -> `status` `found`/`no_match`, `subjectId`, `matchedBy`.
     *
     * @return array<string,mixed>
     */
    public function lookup(array $body): array
    {
        return $this->http->request('POST', '/v1/privacy/lookup', body: $body);
    }

    /**
     * Create a request: `{subjectId, type: access|deletion|closure|restriction|rectification|erase, caseId, executeAfter?, reason?}` (also `export`/`erasure` spellings). Send an Idempotency-Key.
     *
     * @return array<string,mixed>
     */
    public function createRequest(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/privacy/requests', body: $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * One request (`status`, `steps`, `retention`, `eta`, `trace`, `result`).
     *
     * @return array<string,mixed>
     */
    public function getRequest(string $id): array
    {
        return $this->http->request('GET', '/v1/privacy/requests/' . rawurlencode($id));
    }

    /**
     * Cancel a request that has not run yet.
     *
     * @return array<string,mixed>
     */
    public function cancelRequest(string $id, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/privacy/requests/' . rawurlencode($id) . '/cancel', idempotencyKey: $idempotencyKey);
    }

    /**
     * The orchestrator Atlas opens deletion cases at (secrets are write-only: `api_key_set`, `signing_secret_set`). Also reads `partner_link_id`, `site_id` and `cancel_deletion_on_sign_in`.
     *
     * @return array<string,mixed>
     */
    public function getOrchestrator(): array
    {
        return $this->http->request('GET', '/v1/instance/privacy_orchestrator');
    }

    /**
     * Set it: `{provider?, name?, enabled?, case_url?, cancel_url?, auth_header?, auth_scheme?: bearer|raw, api_key?, use_partner_link?, partner_link_id? (implies use_partner_link), site_id?, open_closure_cases?, cancel_deletion_on_sign_in?, sign_requests?, completion_webhook_url?}`; send `null` for `partner_link_id` / `site_id` to clear them. A new `signing_secret` / `completion_webhook_secret` is shown once.
     *
     * @return array<string,mixed>
     */
    public function setOrchestrator(array $body): array
    {
        return $this->http->request('PUT', '/v1/instance/privacy_orchestrator', body: $body);
    }

    /**
     * Remove the orchestrator.
     *
     * @return array<string,mixed>
     */
    public function deleteOrchestrator(): array
    {
        return $this->http->request('DELETE', '/v1/instance/privacy_orchestrator');
    }
}
