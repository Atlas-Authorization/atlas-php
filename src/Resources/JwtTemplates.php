<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/jwt_templates` — named custom-claim JWT templates. */
final class JwtTemplates extends Resource
{
    /** @return array<string,mixed> a ListPage of templates */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/jwt_templates');
    }

    /** Fetched by template name. @return array<string,mixed> */
    public function get(string $name): array
    {
        return $this->http->request('GET', '/v1/jwt_templates/' . rawurlencode($name));
    }

    /**
     * @param array{name:string,claims:array<string,string>,settings?:array{lifetime_seconds?:int|null,audience?:string|null,algorithm?:'RS256'|'HS256',signing_secret?:string}} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->request('POST', '/v1/jwt_templates', body: $body);
    }

    /**
     * Update the claims and/or the mint settings; the name is the key.
     *
     * @param array<string,string> $claims pass `[]` to leave the claims alone when updating only `$settings`
     * @param array{lifetime_seconds?:int|null,audience?:string|null,algorithm?:'RS256'|'HS256',signing_secret?:string}|null $settings
     *        lifetime_seconds is 10-86400 (default: the instance session-token lifetime); null clears a value; signing_secret
     *        (HS256, 16+ characters) is write-only
     *
     * @return array<string,mixed>
     */
    public function update(string $name, array $claims, ?array $settings = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/jwt_templates/' . rawurlencode($name),
            body: ($claims === [] && $settings !== null ? [] : ['claims' => $claims]) + ($settings !== null ? ['settings' => $settings] : []),
        );
    }

    /** @return array<string,mixed> */
    public function delete(string $name): array
    {
        return $this->http->request('DELETE', '/v1/jwt_templates/' . rawurlencode($name));
    }
}
