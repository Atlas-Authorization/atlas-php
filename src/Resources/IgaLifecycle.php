<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/iga` - 8 operations.
 */
final class IgaLifecycle extends Resource
{
    /**
     * `GET /v1/iga/birthright_rules` - List birthright rules.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function listBirthrightRules(array $params = []): array
    {
        return $this->http->request('GET', '/v1/iga/birthright_rules', query: $params);
    }

    /**
     * `POST /v1/iga/birthright_rules` - Create a birthright rule: everyone matching `match` (a group,
     * organization and role, app role or verified email domain; all conditions must hold) holds the listed
     * entitlements. When someone stops matching, the grants the rule gave are revoked unless
     * `revoke_on_unmatch` is false.
     *
     * @param array{name:string,match:array{group?:string,organization_id?:string,org_role?:string,app_role?:string,email_domain?:string},entitlement_ids:list<string>,revoke_on_unmatch?:bool,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function createBirthrightRule(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/birthright_rules',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PATCH /v1/iga/birthright_rules/:id` - Change a birthright rule.
     *
     * @param array{name?:string,match?:array{group?:string,organization_id?:string,org_role?:string,app_role?:string,email_domain?:string},entitlement_ids?:list<string>,revoke_on_unmatch?:bool,enabled?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function updateBirthrightRule(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/iga/birthright_rules/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/iga/birthright_rules/:id` - Delete a birthright rule (grants it already gave stay until
     * revoked).
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function deleteBirthrightRule(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/iga/birthright_rules/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/iga/users/:id/reconcile` - Re-evaluate one user's birthright access now (joiner or mover).
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function reconcile(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/users/' . rawurlencode($id) . '/reconcile',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/iga/users/:id/offboard` - Offboard a leaver: revoke every grant, cancel pending requests
     * and delegations, and with `close_account` schedule the account closure through the data-rights path
     * (so downstream erasure webhooks and any DSAR orchestrator run as for any deletion). `grace_seconds`
     * sets the cancellation window.
     *
     * @param array{close_account?:bool,grace_seconds?:int,reason?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function offboard(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/users/' . rawurlencode($id) . '/offboard',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/iga/settings` - Joiner/mover/leaver settings.
     *
     * @return array<string,mixed>
     */
    public function settings(): array
    {
        return $this->http->request('GET', '/v1/iga/settings');
    }

    /**
     * `PUT /v1/iga/settings` - Turn the joiner/mover/leaver automation on or off and choose what a
     * deactivated account triggers: `revoke` (end the grants) or `revoke_and_close` (also schedule the
     * closure after `leaver_close_grace_seconds`).
     *
     * @param array{jml_enabled?:bool,leaver_action?:'revoke'|'revoke_and_close',leaver_close_grace_seconds?:int|null} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function updateSettings(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/iga/settings',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
