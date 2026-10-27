<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/app_catalog` - 5 operations.
 */
final class AppCatalog extends Resource
{
    /**
     * `GET /v1/app_catalog` - List the app presets: about 100 SAML apps (Slack, GitHub, Snowflake, Notion,
     * HubSpot and more) and about 45 OpenID Connect apps (Grafana, GitLab, Argo CD, Vault, Cloudflare Zero
     * Trust and more), each with its protocol, category, the parameters it needs, whether its values were
     * confirmed in the vendor's docs (verified) and that page (source_url).
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function presets(array $params = []): array
    {
        return $this->http->request('GET', '/v1/app_catalog', query: $params);
    }

    /**
     * `GET /v1/app_catalog/installed` - List the installed apps (SAML and OpenID Connect) with their
     * email-domain allow-list and assignment.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function installed(array $params = []): array
    {
        return $this->http->request('GET', '/v1/app_catalog/installed', query: $params);
    }

    /**
     * `POST /v1/app_catalog/:id/install` - Install a catalog app from its preset and params, with an
     * optional name, installed_id, allowed_email_domains and assignment. A SAML preset becomes a SAML
     * service provider; an OIDC preset registers a confidential OAuth client with the preset's redirect
     * URIs and returns client_id, client_secret (shown once), redirect_uri and issuer to paste into the
     * app. 409 if the installed_id exists. → 201.
     *
     * @param array{params?:array<string,mixed>,installed_id?:string,name?:string,allowed_email_domains?:list<string>,assignment?:array{mode?:'everyone'|'assigned',user_ids?:list<mixed>,groups?:list<mixed>,organizations?:list<mixed>,roles?:list<mixed>}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function install(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/app_catalog/' . rawurlencode($id) . '/install',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PATCH /v1/app_catalog/installed/:id` - Change an installed app's name, allowed_email_domains or
     * assignment. Assignment applies to the launcher and to single sign-on: someone not assigned is
     * refused with 403.
     *
     * @param array{name?:string,allowed_email_domains?:list<string>,assignment?:array{mode?:'everyone'|'assigned',user_ids?:list<mixed>,groups?:list<mixed>,organizations?:list<mixed>,roles?:list<mixed>}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/app_catalog/installed/' . rawurlencode($id),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/app_catalog/installed/:id` - Uninstall an app: it disappears from the launcher and Atlas
     * stops signing people in to it (an OIDC app's OAuth client and its grants are deleted).
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>|null
     */
    public function uninstall(string $id, array $params = [], ?string $idempotencyKey = null): ?array
    {
        return $this->http->request(
            'DELETE',
            '/v1/app_catalog/installed/' . rawurlencode($id),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
