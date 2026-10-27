<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/workflows` - 8 operations.
 */
final class Workflows extends Resource
{
    /**
     * `GET /v1/workflows` - List lifecycle workflows: a trigger (cron, user created, inactive for N days,
     * membership age, trial end, webhook event) plus ordered steps, run by the worker.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/workflows', query: $params);
    }

    /**
     * `POST /v1/workflows` - Create a lifecycle workflow. Steps are notify, disable, delete, invite,
     * add_to_org, remove_from_org, add_to_team, remove_from_team, add_to_group, remove_from_group (an
     * instance group by id or slug) and call_action (an Action with the `workflow_step` trigger, which may
     * be an `http` Action). Each subject runs once per workflow; the first failing step stops the run.
     *
     * @param array{name?:string,description?:string|null,enabled?:bool,trigger?:array{type:'cron'|'user_created'|'inactive'|'membership_age'|'trial_end'|'webhook_event',cron?:string,after_minutes?:int|float,days?:int|float,days_before?:int|float,organization_id?:string,event_type?:string},steps?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/workflows',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/workflows/:id` - Read one lifecycle workflow.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/workflows/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/workflows/:id` - Update a workflow's name, enabled flag, trigger or steps. Changing the
     * trigger resets its cron slot and event cursor.
     *
     * @param array{name?:string,description?:string|null,enabled?:bool,trigger?:array{type:'cron'|'user_created'|'inactive'|'membership_age'|'trial_end'|'webhook_event',cron?:string,after_minutes?:int|float,days?:int|float,days_before?:int|float,organization_id?:string,event_type?:string},steps?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/workflows/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/workflows/:id` - Delete a workflow and its run history.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/workflows/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/workflows/:id/dry_run` - Check what a workflow would do for a user (`user_id`; none for
     * cron) without changing anything. Every step is validated against live data and reported as
     * `would_run` or `failed`.
     *
     * @param array{user_id?:string,organization_id?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function dryRun(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/workflows/' . rawurlencode($id) . '/dry_run',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/workflows/:id/run` - Run a workflow now for one user (`user_id`; none for cron), outside
     * its trigger. Returns the finished run.
     *
     * @param array{user_id?:string,organization_id?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function run(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/workflows/' . rawurlencode($id) . '/run',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/workflows/:id/runs` - Run history, newest first, dry runs included (`dry_run`).
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function runs(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/workflows/' . rawurlencode($id) . '/runs', query: $params);
    }
}
