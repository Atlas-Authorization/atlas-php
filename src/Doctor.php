<?php

declare(strict_types=1);

namespace Atlas;

/**
 * A self-check for an integration: is the key well formed and accepted, is the clock close enough to Atlas's (tokens and webhooks are
 * time-bound), can this server reach the API and the JWKS, and does the service status report trouble. Never throws.
 *
 *     $report = $atlas->doctor('https://clerk.example.com/.well-known/jwks.json');
 *     echo $report;                       // [ok] secret key: sk_live_..., ...
 *     exit($report->ok ? 0 : 1);
 */
final class Doctor
{
    /** Clock difference (seconds) from which a warning, and from which a failure, is reported. */
    public const SKEW_WARN_SECONDS = 5;
    public const SKEW_FAIL_SECONDS = 60;

    public static function run(Http $http, ?string $jwksUrl = null, ?int $now = null): DoctorReport
    {
        $checks = [];
        $add = static function (string $name, string $status, string $detail) use (&$checks): void {
            $checks[] = ['name' => $name, 'status' => $status, 'detail' => $detail];
        };

        $base = $http->baseUrl();
        $add('base url', str_starts_with($base, 'https://') ? 'ok' : 'warn', str_starts_with($base, 'https://') ? $base : "$base is not https");

        $whoami = $http->probe('/v1/whoami');
        if ($whoami['status'] === 0) {
            $add('api reachability', 'fail', 'could not reach ' . $base . ': ' . ($whoami['error'] ?? 'no response'));
        } else {
            $add('api reachability', 'ok', $whoami['ms'] . ' ms');
            if ($whoami['status'] === 200) {
                $who = $whoami['body'] ?? [];
                $add('secret key', 'ok', 'accepted' . (isset($who['instance_id']) ? ' for ' . $who['instance_id'] : (isset($who['id']) ? ' for ' . $who['id'] : '')));
            } elseif ($whoami['status'] === 401 || $whoami['status'] === 403) {
                $add('secret key', 'fail', 'refused (' . $whoami['status'] . '): the key is wrong, revoked or lacks a scope');
            } else {
                $add('secret key', 'warn', 'could not be checked (' . $whoami['status'] . ')');
            }
            $skew = self::skew($whoami['date'], $now);
            if ($skew === null) {
                $add('clock skew', 'warn', 'the API sent no Date header');
            } else {
                $add('clock skew', abs($skew) >= self::SKEW_FAIL_SECONDS ? 'fail' : (abs($skew) >= self::SKEW_WARN_SECONDS ? 'warn' : 'ok'), ($skew >= 0 ? '+' : '') . $skew . ' s versus Atlas');
            }
        }

        $status = $http->probe('/v1/status', false);
        if ($status['status'] !== 200 || $status['body'] === null) {
            $add('service status', 'warn', $status['status'] === 0 ? 'could not be read' : 'answered ' . $status['status']);
        } elseif (Resilience::reportsOutage($status['body'])) {
            $add('service status', 'fail', 'Atlas reports an outage');
        } else {
            $add('service status', 'ok', 'no outage reported');
        }

        if ($jwksUrl !== null) {
            $jwks = $http->probe($jwksUrl, false);
            $keys = is_array($jwks['body']['keys'] ?? null) ? count($jwks['body']['keys']) : 0;
            if ($jwks['status'] === 200 && $keys > 0) {
                $add('jwks', 'ok', "$keys key(s) in " . $jwks['ms'] . ' ms');
            } else {
                $add('jwks', 'fail', $jwks['status'] === 0 ? 'unreachable: ' . ($jwks['error'] ?? '') : ($jwks['status'] === 200 ? 'no keys in the document' : 'answered ' . $jwks['status']));
            }
        }

        return new DoctorReport(count(array_filter($checks, static fn (array $c): bool => $c['status'] === 'fail')) === 0, $checks);
    }

    /** Seconds this server's clock is ahead of the `Date` header (negative = behind), or null. */
    private static function skew(?string $date, ?int $now): ?int
    {
        $at = $date === null ? false : strtotime($date);

        return $at === false ? null : ($now ?? time()) - $at;
    }
}
