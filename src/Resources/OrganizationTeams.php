<?php

declare(strict_types=1);

namespace Atlas\Resources;

/** `/v1/organizations/:id/teams` — teams inside an organization. */
final class OrganizationTeams extends Resource
{
    private static function base(string $orgId): string
    {
        return '/v1/organizations/' . rawurlencode($orgId) . '/teams';
    }

    /**
     * @param array{user_id?:string} $params `user_id` narrows to the teams that user is in
     *
     * @return array<string,mixed>
     */
    public function list(string $orgId, array $params = []): array
    {
        return $this->http->request('GET', self::base($orgId), query: $params === [] ? null : $params);
    }

    /**
     * @param array{name:string,description?:string|null} $body
     *
     * @return array<string,mixed>
     */
    public function create(string $orgId, array $body): array
    {
        return $this->http->request('POST', self::base($orgId), body: $body);
    }

    /** @return array<string,mixed> */
    public function get(string $orgId, string $teamId): array
    {
        return $this->http->request('GET', self::base($orgId) . '/' . rawurlencode($teamId));
    }

    /**
     * @param array{name?:string,description?:string|null} $body
     *
     * @return array<string,mixed>
     */
    public function update(string $orgId, string $teamId, array $body): array
    {
        return $this->http->request('PATCH', self::base($orgId) . '/' . rawurlencode($teamId), body: $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $orgId, string $teamId): array
    {
        return $this->http->request('DELETE', self::base($orgId) . '/' . rawurlencode($teamId));
    }

    /** @return array<string,mixed> */
    public function listMembers(string $orgId, string $teamId): array
    {
        return $this->http->request('GET', self::base($orgId) . '/' . rawurlencode($teamId) . '/members');
    }

    /** @return array<string,mixed> */
    public function addMember(string $orgId, string $teamId, string $userId): array
    {
        return $this->http->request('POST', self::base($orgId) . '/' . rawurlencode($teamId) . '/members', body: ['user_id' => $userId]);
    }

    /** @return array<string,mixed> */
    public function removeMember(string $orgId, string $teamId, string $userId): array
    {
        return $this->http->request(
            'DELETE',
            self::base($orgId) . '/' . rawurlencode($teamId) . '/members/' . rawurlencode($userId),
        );
    }
}
