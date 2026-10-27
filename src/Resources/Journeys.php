<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/flows` - 8 operations.
 */
final class Journeys extends Resource
{
    /**
     * `GET /v1/flows` - The sign-in journeys.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/flows', query: $params);
    }

    /**
     * `POST /v1/flows` - Create a sign-in journey (version 1, active unless activate is false). Steps run
     * top to bottom once a sign-in has passed its factors: refusals (deny, require_method, require_mfa),
     * required actions the user must finish first, Action calls that may refuse, session claims and the
     * redirect after sign-in. Action steps must name workflow_step Actions. → 201.
     *
     * @param array{name:string,description?:string|null,is_default?:bool,applications?:list<string>,enabled?:bool,definition:array{steps:list<mixed>},activate?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/flows', body: $body, query: $params, idempotencyKey: $idempotencyKey);
    }

    /**
     * `GET /v1/flows/:id` - One journey with its active definition (else the latest).
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/flows/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/flows/:id` - Change a journey. A definition becomes the next version (immutable), active
     * unless activate is false.
     *
     * @param array{name?:string,description?:string|null,is_default?:bool,applications?:list<string>,enabled?:bool,definition?:array{steps:list<mixed>},activate?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/flows/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/flows/:id` - Delete a journey and its versions.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/flows/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/flows/:id/versions` - Every version of a journey, newest first.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function versions(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/flows/' . rawurlencode($id) . '/versions', query: $params);
    }

    /**
     * `POST /v1/flows/:id/activate` - Make a version the active one (roll forward or back).
     *
     * @param array{version:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function activate(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/flows/' . rawurlencode($id) . '/activate',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/flows/:id/simulate` - What-if: run a version (default the active one) for a user and a
     * described sign-in (method, mfa_verified, ip, application_id) without changing anything. Returns the
     * outcome (allowed, held or refused), the effects and a per-step trace.
     *
     * @param array{user_id:string,method?:string,mfa_verified?:bool,ip?:string,application_id?:string,version?:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function simulate(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/flows/' . rawurlencode($id) . '/simulate',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
