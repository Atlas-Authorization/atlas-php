<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/users/:id/notes` - 4 operations.
 */
final class UsersNotes extends Resource
{
    /**
     * `GET /v1/users/:id/notes` - The notes on a user: pinned notes first, then newest first.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(string $id, array $params = []): array
    {
        return $this->http->request('GET', '/v1/users/' . rawurlencode($id) . '/notes', query: $params);
    }

    /**
     * `POST /v1/users/:id/notes` - Add a note to a user (up to 5000 characters; at most 500 notes per
     * user). The audit log records that a note was added, never its text. → 201.
     *
     * @param array{body:string,pinned?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function create(string $id, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/users/' . rawurlencode($id) . '/notes',
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `PATCH /v1/users/:id/notes/:noteId` - Edit a note: its text, its pin, or both.
     *
     * @param array{body?:string,pinned?:bool} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function update(string $id, string $noteId, array $body, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PATCH',
            '/v1/users/' . rawurlencode($id) . '/notes/' . rawurlencode($noteId),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/users/:id/notes/:noteId` - Delete a note from a user. The audit log keeps that it was
     * deleted.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function delete(string $id, string $noteId, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/users/' . rawurlencode($id) . '/notes/' . rawurlencode($noteId),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
