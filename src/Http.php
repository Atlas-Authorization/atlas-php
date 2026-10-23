<?php

declare(strict_types=1);

namespace Atlas;

use Atlas\Exception\AtlasApiException;
use Atlas\Exception\ConfigException;
use Atlas\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The shared HTTP core every resource namespace calls. One place decides how a
 * BAPI request is authenticated, serialized, and how a failure becomes an
 * {@see AtlasApiException} — so a namespace method is a one-liner naming a
 * method, a path, and its shapes.
 *
 * Built on PSR-18 (an injectable {@see ClientInterface}) plus PSR-17 factories,
 * so it runs against Guzzle, Symfony HttpClient, or any conforming stack, and
 * needs no network in a test. The secret key is only ever sent as a Bearer
 * token — never logged, never placed in a URL.
 */
final class Http
{
    /** The default BAPI origin, overridable per instance. */
    public const DEFAULT_API_URL = 'https://api.atlasauth.net';

    /** HTTP methods that carry a write; `Atlas-Strict` is only sent on these. */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private readonly string $baseUrl;

    /** @var (\Closure(UnknownFields):void)|null */
    private readonly ?\Closure $onUnknownFields;

    /**
     * @param bool|null                    $strict          client-wide strict bodies: true/false send `Atlas-Strict`, null sends nothing
     * @param callable(UnknownFields):void|null $onUnknownFields called when a write reports ignored body fields
     */
    public function __construct(
        private readonly string $secretKey,
        string $baseUrl,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?bool $strict = null,
        ?callable $onUnknownFields = null,
    ) {
        if ($secretKey === '') {
            throw new ConfigException('Atlas\\Client requires a non-empty secret key.');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->onUnknownFields = $onUnknownFields === null ? null : \Closure::fromCallable($onUnknownFields);
    }

    /** The client-wide strict-body setting (null = no header sent). */
    public function strict(): ?bool
    {
        return $this->strict;
    }

    /** A copy of this transport with a different client-wide strict setting. */
    public function withStrict(?bool $strict): self
    {
        return new self(
            $this->secretKey,
            $this->baseUrl,
            $this->client,
            $this->requestFactory,
            $this->streamFactory,
            $strict,
            $this->onUnknownFields,
        );
    }

    /**
     * Perform a request and decode the response.
     *
     * @param array<string,mixed>|null $query   dropped keys: null values; arrays are spread
     * @param mixed                    $body     JSON-encoded when non-null; `[]` becomes `{}`
     * @param string|null              $idempotencyKey optional Idempotency-Key header
     * @param bool                     $raw     return the raw response body text instead of decoded JSON
     * @param string|null              $rawBody send these bytes as the body instead of JSON (uploads); needs $contentType
     * @param string|null              $contentType the content type of $rawBody
     * @param bool|null                $strict  override the client-wide strict setting for this request
     *                                          (true → `Atlas-Strict: true`, false → `Atlas-Strict: false`, null → inherit)
     * @param array<string,string>|null $headers extra request headers (e.g. the end user's forwarded IP); they can never
     *                                          replace the Bearer secret
     *
     * @return mixed decoded JSON (associative array), raw string, or null for an empty/204 body
     *
     * @throws AtlasApiException on a non-2xx response
     * @throws TransportException when no response is received
     */
    public function request(
        string $method,
        string $path,
        ?array $query = null,
        mixed $body = null,
        ?string $idempotencyKey = null,
        bool $raw = false,
        ?string $rawBody = null,
        ?string $contentType = null,
        ?bool $strict = null,
        ?array $headers = null,
    ): mixed {
        $method = strtoupper($method);
        $url = $this->baseUrl . ($path[0] === '/' ? $path : '/' . $path) . self::serializeQuery($query);

        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Authorization', 'Bearer ' . $this->secretKey)
            ->withHeader('Accept', 'application/json');

        if ($rawBody !== null) {
            if ($contentType === null || $contentType === '') {
                throw new \InvalidArgumentException('A raw request body needs a content type.');
            }
            $request = $request
                ->withHeader('Content-Type', $contentType)
                ->withBody($this->streamFactory->createStream($rawBody));
        } elseif ($body !== null) {
            $json = $body === [] ? '{}' : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($json));
        }

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }

        foreach ($headers ?? [] as $name => $value) {
            if (strcasecmp((string) $name, 'authorization') === 0 || $value === '') {
                continue;
            }
            $request = $request->withHeader((string) $name, $value);
        }

        $effectiveStrict = $strict ?? $this->strict;
        if ($effectiveStrict !== null && in_array($method, self::WRITE_METHODS, true)) {
            $request = $request->withHeader('Atlas-Strict', $effectiveStrict ? 'true' : 'false');
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException(
                'Atlas API request failed before a response was received: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        $status = $response->getStatusCode();
        $text = (string) $response->getBody();

        if ($status < 200 || $status >= 300) {
            throw self::toApiError($status, $text);
        }

        $this->reportUnknownFields($response->getHeaderLine('Atlas-Unknown-Fields'), $method, $path);

        if ($status === 204 || $text === '') {
            return null;
        }

        if ($raw) {
            return $text;
        }

        return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Serialize a query mapping to `?a=1&b=2`, dropping null values and
     * spreading arrays. Booleans render as `true`/`false`.
     *
     * @param array<string,mixed>|null $query
     */
    public static function serializeQuery(?array $query): string
    {
        if ($query === null || $query === []) {
            return '';
        }

        $parts = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            $values = is_array($value) ? $value : [$value];
            foreach ($values as $item) {
                if ($item === null) {
                    continue;
                }
                if (is_bool($item)) {
                    $item = $item ? 'true' : 'false';
                }
                $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $item);
            }
        }

        return $parts === [] ? '' : '?' . implode('&', $parts);
    }

    /**
     * Hand `Atlas-Unknown-Fields` (body fields the endpoint ignored) to the
     * configured callback. Never throws: a broken callback must not turn a
     * successful write into an exception.
     */
    private function reportUnknownFields(string $header, string $method, string $path): void
    {
        if ($this->onUnknownFields === null || trim($header) === '') {
            return;
        }
        $fields = array_values(array_filter(array_map('trim', explode(',', $header)), static fn (string $f): bool => $f !== ''));
        try {
            ($this->onUnknownFields)(new UnknownFields($fields, $method, $path));
        } catch (\Throwable) {
            // Swallowed on purpose (see above).
        }
    }

    /**
     * Parse a non-2xx body into the error envelope, or synthesize one.
     */
    private static function toApiError(int $status, string $text): AtlasApiException
    {
        $errors = [];
        $message = null;

        if ($text !== '') {
            $parsed = json_decode($text, true);
            if (is_array($parsed) && isset($parsed['errors']) && is_array($parsed['errors']) && $parsed['errors'] !== []) {
                foreach ($parsed['errors'] as $item) {
                    if (is_array($item)) {
                        $errors[] = ErrorItem::fromArray($item);
                    }
                }
            } else {
                // Non-envelope body (an upstream proxy, plain text): keep it as the message.
                $message = substr($text, 0, 500);
            }
        }

        if ($errors === []) {
            $errors = [new ErrorItem(
                'UNKNOWN',
                $message ?? "Atlas API request failed with status {$status}",
            )];
        }

        return AtlasApiException::fromResponse($status, $errors);
    }
}
