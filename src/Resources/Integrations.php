<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * Identity integrations: presets and custom integrations at `/v1/instance/integrations`, plus the receiver-side routes (verify errors, user card, public instance metadata).
 * `Sessions::getToken($id, ['for' => $integration])` mints an integration token for a session.
 */
final class Integrations extends Resource
{
    /**
     * Every integration with its health.
     *
     * @return array<string,mixed>
     */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/instance/integrations');
    }

    /**
     * One integration.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/instance/integrations/' . rawurlencode($id));
    }

    /**
     * Connect or replace an integration. `['dry_run' => true]` validates and saves nothing.
     * `per_call_audience` (`{param, template, allowed[], any_matching}`) lets the integration mint for more than one
     * audience (`template` has `{param}` once; a preset's is fixed, leave it out); `null` turns it off, omitting it keeps
     * the current one. The read answer carries it, plus `default` (true for a preset's).
     *
     * @return array<string,mixed>
     */
    public function set(string $id, array $body, array $query = []): array
    {
        return $this->http->request('PUT', '/v1/instance/integrations/' . rawurlencode($id), body: $body, query: $query);
    }

    /**
     * Disconnect an integration.
     *
     * @return array<string,mixed>
     */
    public function remove(string $id): array
    {
        return $this->http->request('DELETE', '/v1/instance/integrations/' . rawurlencode($id));
    }

    /**
     * Event and back-channel deliveries, newest first (`limit`, `trace`, `kind`).
     *
     * @return array<string,mixed>
     */
    public function deliveries(string $id, array $query = []): array
    {
        return $this->http->request('GET', '/v1/instance/integrations/' . rawurlencode($id) . '/deliveries', query: $query);
    }

    /**
     * Reveal the event receiver's signing secret (audited).
     *
     * @return array<string,mixed>
     */
    public function revealEventsSecret(string $id): array
    {
        return $this->http->request('POST', '/v1/instance/integrations/' . rawurlencode($id) . '/events_secret');
    }

    /**
     * An integration token for a Sign in with Atlas user from their access token.
     *
     * @return array<string,mixed>
     */
    public function atlasIdToken(string $id, string $accessToken): array
    {
        return $this->http->request('POST', '/v1/instance/integrations/' . rawurlencode($id) . '/atlas_id_tokens', body: ['access_token' => $accessToken]);
    }

    /**
     * Report that a receiver could not verify a token: `{integration, reason, ...}`.
     *
     * @return array<string,mixed>
     */
    public function reportVerifyError(array $body): array
    {
        return $this->http->request('POST', '/v1/integrations/verify_errors', body: $body);
    }

    /**
     * The user card for an integration: `['sub' => .., 'iss' => ..]`.
     *
     * @return array<string,mixed>
     */
    public function card(string $id, array $query = []): array
    {
        return $this->http->request('GET', '/v1/integrations/' . rawurlencode($id) . '/card', query: $query);
    }

    /**
     * Revoke the user's sessions from the card.
     *
     * @return array<string,mixed>
     */
    public function revokeCardSessions(string $id, array $body = []): array
    {
        return $this->http->request('POST', '/v1/integrations/' . rawurlencode($id) . '/card/actions/revoke_sessions', body: $body);
    }

    /**
     * Public metadata of an instance (issuer, JWKS), no key needed.
     *
     * @return array<string,mixed>
     */
    public function instanceMetadata(string $id): array
    {
        return $this->http->request('GET', '/v1/public/instances/' . rawurlencode($id));
    }

    /**
     * `POST /v1/integrations/:integration/card/actions/:action` - Run a card action. revoke_sessions signs
     * the user out of every device (their sessions end, back-channel logouts go out) and is audited with
     * the agent the integration names. Partner link with sessions:write, or the secret key.
     *
     * @param array{sub:string,iss?:string,agent?:array{id?:string,name?:string,email?:string}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function runCardAction(string $integration, string $action, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/integrations/' . rawurlencode($integration) . '/card/actions/' . rawurlencode($action),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
