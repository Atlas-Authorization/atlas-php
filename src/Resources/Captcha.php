<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/captcha` — verify a CAPTCHA token server-side with the instance's configured provider.
 */
final class Captcha extends Resource
{
    /**
     * `POST /v1/captcha/verify` — verify a CAPTCHA token against the instance's configured provider. Fails
     * closed.
     *
     * @param array<string,mixed> $body
     *
     * @return array<string,mixed>
     */
    public function verify(array $body): array
    {
        return $this->http->request('POST', '/v1/captcha/verify', body: $body);
    }
}
