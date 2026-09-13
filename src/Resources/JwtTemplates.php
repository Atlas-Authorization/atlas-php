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
     * @param array{name:string,claims:array<string,string>} $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body): array
    {
        return $this->http->request('POST', '/v1/jwt_templates', body: $body);
    }

    /**
     * Only the claims are updatable; the name is the key.
     *
     * @param array<string,string> $claims
     *
     * @return array<string,mixed>
     */
    public function update(string $name, array $claims): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/jwt_templates/' . rawurlencode($name),
            body: ['claims' => $claims],
        );
    }

    /** @return array<string,mixed> */
    public function delete(string $name): array
    {
        return $this->http->request('DELETE', '/v1/jwt_templates/' . rawurlencode($name));
    }
}
