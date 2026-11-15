<?php

declare(strict_types=1);

namespace Atlas\Telemetry;

/**
 * Optional OpenTelemetry spans and metrics for every Backend API request. Opt in with `new Client(..., telemetry: true)`. It does nothing,
 * and loads nothing, unless `open-telemetry/api` is installed; install it with the SDK of your choice and your tracer and meter providers
 * (registered globally) receive:
 *
 * - a client span `atlas <METHOD> <path>` with `http.request.method`, `url.path`, `http.response.status_code`, `atlas.retry.count` and
 *   `error.type`, an exception event and error status on failure;
 * - the `http.client.request.duration` histogram (seconds) with method, status and a low-cardinality route (`/v1/users/{id}`).
 */
final class OpenTelemetry
{
    public const SCOPE = 'atlasauth/atlas-php';

    public static function available(): bool
    {
        return class_exists('OpenTelemetry\\API\\Globals');
    }

    /** Collapse identifiers in a path so it is a safe metric label: `/v1/users/user_123/ban` becomes `/v1/users/{id}/ban`. */
    public static function route(string $path): string
    {
        $parts = array_map(static fn (string $s): string => preg_match('/^(?:[A-Za-z]+_[A-Za-z0-9]{6,}|[0-9a-fA-F-]{16,}|\d+|[A-Za-z0-9]{20,})$/', $s) === 1 ? '{id}' : $s, explode('/', $path));

        return implode('/', $parts);
    }

    /**
     * Start a client span; returns a handle for {@see finish()}, or null when OpenTelemetry is not installed.
     *
     * @return array{span:object,scope:object|null,start:float,method:string,route:string}|null
     */
    public static function start(string $method, string $path, ?string $version = null): ?array
    {
        if (!self::available()) {
            return null;
        }
        try {
            $globals = 'OpenTelemetry\\API\\Globals';
            $tracer = $globals::tracerProvider()->getTracer(self::SCOPE, $version);
            $span = $tracer->spanBuilder('atlas ' . $method . ' ' . self::route($path))
                ->setSpanKind(constant('OpenTelemetry\\API\\Trace\\SpanKind::KIND_CLIENT'))
                ->startSpan();
            $span->setAttribute('http.request.method', $method);
            $span->setAttribute('url.path', $path);

            return ['span' => $span, 'scope' => $span->activate(), 'start' => microtime(true), 'method' => $method, 'route' => self::route($path)];
        } catch (\Throwable) {
            return null;
        }
    }

    /** End the span and record the duration. Never throws. @param array{span:object,scope:object|null,start:float,method:string,route:string}|null $handle */
    public static function finish(?array $handle, ?int $status, int $retries, ?\Throwable $error = null): void
    {
        if ($handle === null) {
            return;
        }
        try {
            $span = $handle['span'];
            if ($status !== null) {
                $span->setAttribute('http.response.status_code', $status);
            }
            $span->setAttribute('atlas.retry.count', $retries);
            if ($error !== null) {
                $span->setAttribute('error.type', $error::class);
                $span->recordException($error);
                $span->setStatus(constant('OpenTelemetry\\API\\Trace\\StatusCode::STATUS_ERROR'), $error->getMessage());
            }
            $handle['scope']?->detach();
            $span->end();
            $globals = 'OpenTelemetry\\API\\Globals';
            $globals::meterProvider()->getMeter(self::SCOPE)->createHistogram('http.client.request.duration', 's', 'Duration of Atlas API requests')
                ->record(microtime(true) - $handle['start'], ['http.request.method' => $handle['method'], 'http.route' => $handle['route'], 'http.response.status_code' => $status ?? 0]);
        } catch (\Throwable) {
            // Telemetry must never turn a request into a failure.
        }
    }
}
