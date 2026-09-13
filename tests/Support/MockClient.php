<?php

declare(strict_types=1);

namespace Atlas\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A hand-rolled PSR-18 client for the test suite: it returns queued responses in
 * order and records every request it was handed, so a test can assert the exact
 * method, path, headers and body the SDK produced — with no network at all.
 *
 * This is the whole point of building the transport on PSR-18: the SDK runs
 * unchanged against this mock.
 */
final class MockClient implements ClientInterface
{
    /** @var list<ResponseInterface|callable(RequestInterface):ResponseInterface> */
    private array $queue = [];

    /** @var list<RequestInterface> every request the SDK sent, in order */
    public array $requests = [];

    /**
     * Enqueue a JSON (or raw) response.
     *
     * @param array<string,mixed>|string|null $body        array → JSON-encoded; string → sent verbatim; null → empty
     * @param array<string,string>            $headers
     */
    public function push(int $status, array|string|null $body = null, array $headers = []): void
    {
        $text = is_array($body) ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : ($body ?? '');
        $this->queue[] = new Response($status, $headers, $text);
    }

    /** Enqueue a transport-level failure (no response received). */
    public function pushException(?string $message = null): void
    {
        $msg = $message ?? 'simulated connection failure';
        $this->queue[] = static function (RequestInterface $request) use ($msg): ResponseInterface {
            throw new MockNetworkException($msg, $request);
        };
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new \RuntimeException('MockClient received an unexpected request: ' . $request->getUri());
        }

        $next = array_shift($this->queue);

        return $next instanceof ResponseInterface ? $next : $next($request);
    }

    /** The most recent request, for assertions. */
    public function lastRequest(): RequestInterface
    {
        if ($this->requests === []) {
            throw new \RuntimeException('No request was sent.');
        }

        return $this->requests[array_key_last($this->requests)];
    }

    public function requestCount(): int
    {
        return count($this->requests);
    }
}
