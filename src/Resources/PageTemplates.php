<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/instance/page_templates` - 6 operations.
 */
final class PageTemplates extends Resource
{
    /**
     * `GET /v1/instance/page_templates` - The hosted-page templates of the instance: for each page
     * (sign-in, sign-up, user-profile) the live template and the draft waiting to be previewed or
     * promoted.
     *
     * @param array{limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function list(array $params = []): array
    {
        return $this->http->request('GET', '/v1/instance/page_templates', query: $params);
    }

    /**
     * `GET /v1/instance/page_templates/:page` - Read one page's live and draft template.
     *
     * @return array<string,mixed>
     */
    public function get(string $page): array
    {
        return $this->http->request('GET', '/v1/instance/page_templates/' . rawurlencode($page));
    }

    /**
     * `PUT /v1/instance/page_templates/:page` - Save a page template as the page's draft. A template is
     * data: components (heading, text, image, link, button_link, list, divider, spacer, columns) in four
     * regions (header, above_form, below_form, footer). It is validated on save: no raw HTML, CSS or
     * script, https and mailto links only. The live page is unchanged until you promote.
     *
     * @param array{definition?:array{version?:int|float,regions?:array<string,mixed>}} $body
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function saveDraft(string $page, array $body = [], array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'PUT',
            '/v1/instance/page_templates/' . rawurlencode($page),
            body: $body,
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `DELETE /v1/instance/page_templates/:page` - Drop the draft or take the live template down
     * (`status`).
     *
     * @param array{dry_run?:'true'|'false',status?:'draft'|'live'} $params
     *
     * @return array<string,mixed>
     */
    public function discard(string $page, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'DELETE',
            '/v1/instance/page_templates/' . rawurlencode($page),
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/instance/page_templates/:page/preview` - A signed link to the hosted page with the draft
     * template applied (valid for one hour, noindex, with a preview banner). 409 when the page has no
     * template.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function preview(string $page, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/instance/page_templates/' . rawurlencode($page) . '/preview',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * `POST /v1/instance/page_templates/:page/promote` - Make the draft the live template.
     *
     * @param array{dry_run?:'true'|'false'} $params
     *
     * @return array<string,mixed>
     */
    public function promote(string $page, array $params = [], ?string $idempotencyKey = null): array
    {
        return $this->http->request(
            'POST',
            '/v1/instance/page_templates/' . rawurlencode($page) . '/promote',
            query: $params,
            idempotencyKey: $idempotencyKey,
        );
    }
}
