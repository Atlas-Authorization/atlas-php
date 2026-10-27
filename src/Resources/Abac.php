<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1` - 17 operations.
 */
final class Abac extends Resource
{
    public readonly AbacTemplates $templates;
    public readonly AbacLinks $links;
    public readonly AbacSchema $schema;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->templates = new AbacTemplates($http);
        $this->links = new AbacLinks($http);
        $this->schema = new AbacSchema($http);
    }

    /**
     * `GET /v1/abac_policies` - List the ABAC policies (current version of each).
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/abac_policies', query: $params);
    }

    /**
     * `POST /v1/abac_policies` - Create an ABAC policy from `source` written in the Cedar-subset policy
     * language (permit/forbid with when/unless over principal, action, resource and context attributes;
     * `fga.check(...)` consults the policy’s FGA store). Source that does not parse is refused with the
     * character position; with an entity schema set it is also type-checked and every problem is reported.
     * Optional `tests` are saved with version 1.
     *
     * @param array{name?:string,description?:string|null,enabled?:bool,fga_store_id?:string|null,source?:string,tests?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/abac_policies',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/abac_policies/:id` - Read one ABAC policy with its current source and tests.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/abac_policies/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/abac_policies/:id` - Update an ABAC policy. Sending `source` appends a new version and
     * makes it current; `current_version` (without `source`) rolls back to an earlier version; name,
     * description, enabled and fga_store_id change in place.
     *
     * @param array{name?:string,description?:string|null,enabled?:bool,fga_store_id?:string|null,source?:string,tests?:list<array<string,mixed>>,current_version?:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/abac_policies/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/abac_policies/:id` - Delete an ABAC policy and all its versions.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/abac_policies/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/abac_policies/:id/versions` - List every saved version of an ABAC policy, newest first.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function versions(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/abac_policies/' . rawurlencode($id) . '/versions', query: $params);
    }

    /**
     * `POST /v1/abac_policies/:id/test` - Run test cases against a policy version (default: the current
     * one), or against a draft `source` and `tests` sent in the body, without saving anything. Each test
     * is a request plus the expected decision; the policy is evaluated on its own, not with the rest of
     * the set. The response also carries `coverage`: which rules and `when`/`unless` clauses the tests
     * exercised (a clause no test saw both true and false is listed as a gap), with line numbers.
     *
     * @param array{version?:int,source?:string,tests?:list<array<string,mixed>>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function test(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/abac_policies/' . rawurlencode($id) . '/test',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/abac/evaluate` - Policy decision point: ask whether `principal` may perform `action` on
     * `resource` given `context` (and optional extra `entities` for hierarchy). All enabled policies are
     * evaluated together with Cedar semantics: a matching forbid wins, otherwise a matching permit allows,
     * otherwise deny. Each decision is written to the event feed (type authz.decision), so log streams
     * carry it.
     *
     * @param array{principal?:array<string,mixed>,action?:string,resource?:array<string,mixed>,context?:array<string,mixed>,entities?:list<array<string,mixed>>,policy_ids?:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function evaluate(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/abac/evaluate',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/abac/partial_evaluate` - Partial evaluation (data filtering): ask what is left of the
     * decision when an entity is unknown. With a known principal, an action and a resource TYPE, the
     * response is a residual `filter` over the resource attributes (or constant `true`/`false`) that is
     * equivalent to evaluating the policies on each resource. `unknowns: ["principal"]` answers "who may
     * do this on that resource". Relationship conditions (`fga.check`) are resolved to id lists from the
     * policy’s FGA store unless `resolve_relations` is false. `output` asks for a ready-made parameterised
     * SQL WHERE (`sql`: dialect and a field-to-column map) and/or a Prisma where object (`prisma`: a field
     * map). Policies that build sets from unknown attributes cannot be expressed as a filter and are
     * refused with a message naming the policy.
     *
     * @param array{principal:array{type:string,id?:string,attributes?:array<string,mixed>,parents?:list<mixed>},action:string,resource:array{type:string,id?:string,attributes?:array<string,mixed>,parents?:list<mixed>},context?:array<string,mixed>,entities?:list<array<string,mixed>>,policy_ids?:list<string>,unknowns?:list<'principal'|'resource'>,resolve_relations?:bool,output?:array{sql?:array<string,mixed>,prisma?:array<string,mixed>}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function partialEvaluate(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/abac/partial_evaluate',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/abac/accessible_resources` - List the resources of a type a principal may perform an
     * action on, paged (`limit`, `starting_after`; the response carries `has_more` and `next_cursor`).
     * Candidates are the Atlas resources of that type (their metadata are the attributes) or the
     * `candidates` you send. The policies are reduced to one filter and applied to every candidate;
     * `method` says whether that worked or each candidate was evaluated in full.
     *
     * @param array{principal:array{type:string,id?:string,attributes?:array<string,mixed>,parents?:list<mixed>},action:string,resource_type:string,context?:array<string,mixed>,entities?:list<array<string,mixed>>,candidates?:list<array<string,mixed>>,policy_ids?:list<string>,limit?:int,starting_after?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function accessibleResources(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/abac/accessible_resources',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/abac_policies/:id/diff` - Compare two versions of a policy: a line diff of the source and,
     * unless `semantic=false`, the requests whose decision changes. The semantic part enumerates a bounded
     * set of requests built from the attributes, groups and actions the policies test (from the entity
     * schema when one is set), and lists those that come out differently, with newly-allowed and
     * newly-denied counts per action. `exhaustive` says whether every request in the space was compared or
     * the space was sampled.
     *
     * @param array{from?:int,to?:int,semantic?:bool,max_requests?:int,max_changes?:int} $params
     *
     * @return array<string,mixed>
     */
    public function diff(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/abac_policies/' . rawurlencode($id) . '/diff', query: $params);
    }

    /**
     * `POST /v1/abac/diff` - The same comparison for two policy sources you send (a draft against the
     * saved version, or two templates), without saving anything.
     *
     * @param array{before:string,after:string,semantic?:bool,max_requests?:int,max_changes?:int} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function diffSources(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/abac/diff',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/authzen/access/v1/evaluation` - AuthZEN Access Evaluation: `{subject, resource, action,
     * context}` → `{decision: boolean}`. Entity types map to PascalCase policy types.
     *
     * @param array{subject?:array<string,mixed>,resource?:array<string,mixed>,action?:array<string,mixed>,context?:array<string,mixed>,evaluations?:list<array<string,mixed>>,options?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function authzenEvaluation(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/authzen/access/v1/evaluation',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/authzen/access/v1/evaluations` - AuthZEN Access Evaluations (batch, up to 100): shared
     * defaults plus an `evaluations` array, with `options.evaluations_semantic` of execute_all (default),
     * deny_on_first_deny or permit_on_first_permit.
     *
     * @param array{subject?:array<string,mixed>,resource?:array<string,mixed>,action?:array<string,mixed>,context?:array<string,mixed>,evaluations?:list<array<string,mixed>>,options?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function authzenEvaluations(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/authzen/access/v1/evaluations',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/authzen/access/v1/search/subject` - AuthZEN Subject Search: which subjects of
     * `subject.type` may perform `action` on `resource` (give the resource a `type` and `id`). Answers
     * from the same ABAC policies as an evaluation, reduced once by partial evaluation and applied to the
     * candidates Atlas knows: your users (type `user`, their public metadata as attributes) and the users
     * in the FGA stores the policies consult. `page.limit` and `page.token` page the result; the response
     * carries `page.next_token` (empty on the last page), `count` and `total`.
     *
     * @param array{subject:array{type:string,id?:string,properties?:array<string,mixed>},action:array{name:string,properties?:array<string,mixed>},resource:array{type:string,id:string,properties?:array<string,mixed>},context?:array<string,mixed>,page?:array{limit?:int,token?:string}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function authzenSearchSubject(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/authzen/access/v1/search/subject',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/authzen/access/v1/search/resource` - AuthZEN Resource Search: which resources of
     * `resource.type` the `subject` may perform `action` on. Candidates are your synced Atlas resources of
     * that type (their metadata are the attributes) and the objects of that type in the FGA stores the
     * policies consult. Paged with `page.limit` and `page.token`.
     *
     * @param array{subject:array{type:string,id:string,properties?:array<string,mixed>},action:array{name:string,properties?:array<string,mixed>},resource:array{type:string,id?:string,properties?:array<string,mixed>},context?:array<string,mixed>,page?:array{limit?:int,token?:string}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function authzenSearchResource(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/authzen/access/v1/search/resource',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/authzen/access/v1/search/action` - AuthZEN Action Search: which actions the `subject` may
     * perform on the `resource`. The actions considered are the ones the policies name (and the entity
     * schema declares), each evaluated in full; the result lists the permitted action names.
     *
     * @param array{subject:array{type:string,id:string,properties?:array<string,mixed>},resource:array{type:string,id:string,properties?:array<string,mixed>},context?:array<string,mixed>,page?:array{limit?:int,token?:string}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function authzenSearchAction(array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/authzen/access/v1/search/action',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
