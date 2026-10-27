<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1` - 7 operations.
 */
final class ServiceConnectors extends Resource
{
    /**
     * `GET /v1/service_connector_catalog` - The services you can connect: for each kind the credential
     * fields, the config fields and the operations with their inputs. Messaging (Twilio, SendGrid, Slack),
     * CRM (HubSpot, Salesforce), analytics (Segment), fraud (Fingerprint, Arkose Labs, Forter, Sardine),
     * identity verification (Persona, Onfido, Veriff, Stripe Identity) and a generic HTTPS connector.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function catalog(array $params = []): array
    {
        return $this->http->request('GET', '/v1/service_connector_catalog', query: $params);
    }

    /**
     * `GET /v1/service_connectors` - List the instance's service connectors. Credentials are never
     * returned.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/service_connectors', query: $params);
    }

    /**
     * `POST /v1/service_connectors` - Connect a service. `credentials` are checked against the kind's
     * fields and stored in the vault (never returned again); `config` holds the non-secret settings. 409
     * when the name is taken.
     *
     * @param array{name?:string,kind?:string,config?:array<string,mixed>,credentials?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/service_connectors',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `GET /v1/service_connectors/:id` - Read one service connector.
     *
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/service_connectors/' . rawurlencode($id));
    }

    /**
     * `PATCH /v1/service_connectors/:id` - Rename, enable or disable a connector, merge new `config`
     * values, or replace individual `credentials` (written as a new vault version; the others are kept).
     *
     * @param array{name?:string,enabled?:bool,config?:array<string,mixed>,credentials?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/service_connectors/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/service_connectors/:id` - Delete a connector and its stored credentials.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/service_connectors/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/service_connectors/:id/invoke` - Run one operation of the connector (send a message, read
     * a fraud signal, start an identity check). `input` is checked against the operation's fields. A
     * refusal or failure from the service is 502 CONNECTOR_UPSTREAM_ERROR with its own message. Each call
     * is audited without its input or output.
     *
     * @param array{operation?:string,input?:array<string,mixed>} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function invoke(string $id, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/service_connectors/' . rawurlencode($id) . '/invoke',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
