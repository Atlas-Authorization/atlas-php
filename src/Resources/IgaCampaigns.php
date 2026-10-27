<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/iga` - 10 operations.
 */
final class IgaCampaigns extends Resource
{
    /**
     * `GET /v1/iga/campaigns` - List certification (access review) campaigns.
     *
     * @param array{status?:'draft'|'active'|'closed',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/iga/campaigns', query: $params);
    }

    /**
     * `POST /v1/iga/campaigns` - Create a draft campaign scoped to everything, specific entitlements, or
     * the entitlements for an app, role, group or organization, with reviewers (the person's manager, the
     * entitlement owner, or named users), a due date and reminder days. With `auto_revoke` the access
     * reviewers mark for revocation is removed when the campaign closes.
     *
     * @param array{name:string,description?:string,scope_type?:'all'|'entitlement'|'app'|'role'|'group'|'org',scope_ids?:list<string>,reviewer_type?:'manager'|'owner'|'named',reviewer_ids?:list<string>,due_at?:int,reminder_days?:list<int>,auto_revoke?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/campaigns',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/iga/campaigns/:id` - Read a campaign with its progress.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/iga/campaigns/' . rawurlencode($id));
    }

    /**
     * `DELETE /v1/iga/campaigns/:id` - Delete a draft or closed campaign.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/iga/campaigns/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/iga/campaigns/:id/launch` - Launch a draft campaign: it adopts access that exists in Atlas
     * but was never granted through governance, freezes the review list and notifies reviewers.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function launch(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/campaigns/' . rawurlencode($id) . '/launch',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/iga/campaigns/:id/close` - Close a campaign and apply its revoke decisions (with
     * `auto_revoke`). Items nobody reviewed are left in place and reported as pending.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function close(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/campaigns/' . rawurlencode($id) . '/close',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/iga/campaigns/:id/items` - The review items of a campaign.
     *
     * @param array{decision?:'pending'|'certified'|'revoked',reviewer_user_id?:string,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function items(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/iga/campaigns/' . rawurlencode($id) . '/items', query: $params);
    }

    /**
     * `GET /v1/iga/campaigns/:id/progress` - Progress: totals, percent complete, whether it is overdue,
     * and per-reviewer counts.
     *
     * @return array<string,mixed>
     */
    public function progress(string $id): array
    {
        return $this->http->request('GET', '/v1/iga/campaigns/' . rawurlencode($id) . '/progress');
    }

    /**
     * `GET /v1/iga/campaigns/:id/evidence` - The audit evidence for a campaign as JSON, or CSV with
     * `format=csv`. People are identified by id only (an erased user is flagged), free-text comments are
     * never included, and `masked_emails=true` adds an address reduced to its first letter and domain.
     *
     * @param array{format?:'json'|'csv',masked_emails?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function evidence(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/iga/campaigns/' . rawurlencode($id) . '/evidence', query: $params);
    }

    /**
     * `POST /v1/iga/campaign_items/:id/decide` - Certify or revoke one review item. With
     * `reviewer_user_id` the decision is the reviewer's (they must be allowed to review it, and may not
     * review their own access); without it it is an administrator decision.
     *
     * @param array{decision:'certify'|'revoke',comment?:string,reviewer_user_id?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function decide(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/iga/campaign_items/' . rawurlencode($id) . '/decide',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
