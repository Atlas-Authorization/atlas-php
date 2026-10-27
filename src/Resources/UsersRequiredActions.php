<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1` - 4 operations.
 */
final class UsersRequiredActions extends Resource
{
    /**
     * `POST /v1/users/:id/required_actions` - Make a user owe an action at their next sign-in, whatever
     * the method: update_password, verify_email, accept_policy, configure_mfa, update_profile, or custom
     * (sent to an https url; the app closes it with DELETE). The sign-in attempt holds at
     * needs_required_action until it is done (configure_mfa alone routes through MFA enrollment;
     * accept_policy is closed once the policy gate passes; verify_email and configure_mfa clear themselves
     * when already true). Re-requesting an open action is a 200 with the same row. Emits
     * user.required_action_requested. → 201.
     *
     * @param array{action:'update_password'|'verify_email'|'accept_policy'|'configure_mfa'|'update_profile'|'identity_verification'|'custom',url?:string,reason?:string} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function request(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/required_actions',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/users/:id/required_actions` - The actions a user currently owes, oldest first.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/required_actions', query: $params);
    }

    /**
     * `DELETE /v1/users/:id/required_actions/:rid` - Withdraw an owed action. This is also how an
     * application closes a custom action once the user has finished its step. Emits
     * user.required_action_cleared. 404 when it is not open.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function clear(string $id, string $rid, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/users/' . rawurlencode($id) . '/required_actions/' . rawurlencode($rid),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/required_actions/bulk` - Owe the same action for many users at once: every member of one
     * organization (organization_id) or an explicit user_ids list, at most 5000. Users outside the
     * instance are skipped, and an already-open action counts as already_open. Emits
     * user.required_action_requested per newly opened action.
     *
     * @param array{action:'update_password'|'verify_email'|'accept_policy'|'configure_mfa'|'update_profile'|'identity_verification'|'custom',url?:string,reason?:string,organization_id?:string,user_ids?:list<string>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function bulk(array $body, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'POST',
            '/v1/required_actions/bulk',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
