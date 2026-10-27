<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/users/search` - 2 operations.
 */
final class UsersSearch extends Resource
{
    public readonly UsersSearchViews $views;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->views = new UsersSearchViews($http);
    }

    /**
     * `POST /v1/users/search` - Search users with a boolean filter tree (and/or/not) over profile columns,
     * traits and metadata keys, organization and org role, MFA enrolled/strategy, sign-in strategy, last
     * sign-in country or IP, and date ranges. Sort with order_by; page with starting_after
     * (creation/update order) or offset (last_sign_in_at order). include_total_count adds total_count.
     * Containment tests on public metadata and traits are answered by an index. Same user objects as GET
     * /v1/users.
     *
     * @param array{filter?:array{and?:list<mixed>,or?:list<mixed>,not?:array<string,mixed>,field?:string,op?:string,value?:string|int|float|bool|list<mixed>},query?:string,order_by?:string,include_deleted?:bool,limit?:int,starting_after?:string,offset?:int,include_total_count?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function query(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/search',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/users/search/export` - Export every user a search matches (up to 10,000;
     * x-export-truncated says when more matched) as CSV (default) or NDJSON. fields picks columns: id,
     * primary_email, username, first_name, last_name, external_id, created_at, last_sign_in_at, banned,
     * mfa_enrolled, plus traits.<key> / public_metadata.<path>. CSV cells that start with = + - @ are
     * prefixed with an apostrophe so a spreadsheet never runs them as formulas.
     *
     * @param array{filter?:array{and?:list<mixed>,or?:list<mixed>,not?:array<string,mixed>,field?:string,op?:string,value?:string|int|float|bool|list<mixed>},query?:string,order_by?:string,include_deleted?:bool,format?:'csv'|'ndjson',fields?:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function export(array $body, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'POST',
            '/v1/users/search/export',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
