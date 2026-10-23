<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** Action templates this instance publishes to the gallery (staff review them). */
final class ActionTemplates extends Resource
{
    /** @return array<string,mixed> a ListPage */
    public function list(): array
    {
        return $this->http->request('GET', '/v1/action_templates');
    }

    /**
     * `slug`, `name`, `author_name` and `trigger` are required; the first version may come
     * in the same call (`version`, `code`, `secrets`, `changelog`, `submit`).
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function create(array $body, ?string $idempotencyKey = null): array
    {
        return $this->http->request('POST', '/v1/action_templates', body: $body, idempotencyKey: $idempotencyKey);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        return $this->http->request('GET', '/v1/action_templates/' . rawurlencode($id));
    }

    /**
     * @param array<string,mixed> $body the listing fields
     *
     * @return array<string,mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->http->request('PATCH', '/v1/action_templates/' . rawurlencode($id), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $id): array
    {
        return $this->http->request('DELETE', '/v1/action_templates/' . rawurlencode($id));
    }

    /**
     * @param array{version:string,code:string,secrets?:list<array{name:string,description:string,required:bool}>,changelog?:string,submit?:bool} $body
     *
     * @return array<string,mixed>
     */
    public function addVersion(string $id, array $body): array
    {
        return $this->http->request('POST', '/v1/action_templates/' . rawurlencode($id) . '/versions', body: $body);
    }

    /** @return array<string,mixed> */
    public function submitVersion(string $id, string $version): array
    {
        return $this->http->request('POST', '/v1/action_templates/' . rawurlencode($id) . '/versions/' . rawurlencode($version) . '/submit');
    }

    /** @return array<string,mixed> */
    public function deleteVersion(string $id, string $version): array
    {
        return $this->http->request('DELETE', '/v1/action_templates/' . rawurlencode($id) . '/versions/' . rawurlencode($version));
    }

    /** @return array<string,mixed> List an approved template again after unlisting it. */
    public function publish(string $id): array
    {
        return $this->http->request('POST', '/v1/action_templates/' . rawurlencode($id) . '/publish');
    }

    /** @return array<string,mixed> Hide a template from the gallery (installs keep working). */
    public function unlist(string $id): array
    {
        return $this->http->request('POST', '/v1/action_templates/' . rawurlencode($id) . '/unlist');
    }
}
