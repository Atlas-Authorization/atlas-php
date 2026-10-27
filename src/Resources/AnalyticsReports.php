<?php

declare(strict_types=1);

namespace Atlas\Resources;

/**
 * `/v1/analytics/reports` - 3 operations.
 */
final class AnalyticsReports extends Resource
{
    /**
     * `GET /v1/analytics/reports/inactive_users` - Compliance report: users who have not signed in for
     * `inactive_days` (default 90), oldest id first, cursor-paginated (an export returns up to 50,000
     * rows).
     *
     * @param array{inactive_days?:int,limit?:int,starting_after?:string,from?:int|string,to?:int|string,days?:int,granularity?:'day'|'week'|'month',organization_id?:string,format?:'json'|'csv'|'parquet'} $params
     *
     * @return array<string,mixed>
     */
    public function inactiveUsers(array $params = []): array
    {
        return $this->http->request('GET', '/v1/analytics/reports/inactive_users', query: $params);
    }

    /**
     * `GET /v1/analytics/reports/mfa_coverage` - Compliance report: how many users are protected by a
     * second factor, a passkey, either, or neither.
     *
     * @param array{from?:int|string,to?:int|string,days?:int,granularity?:'day'|'week'|'month',organization_id?:string,format?:'json'|'csv'|'parquet',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function mfaCoverage(array $params = []): array
    {
        return $this->http->request('GET', '/v1/analytics/reports/mfa_coverage', query: $params);
    }

    /**
     * `GET /v1/analytics/reports/access_by_app` - Compliance report: for each application (OAuth client),
     * how many users have access, how many grants are active and how many users signed in during the
     * window.
     *
     * @param array{from?:int|string,to?:int|string,days?:int,granularity?:'day'|'week'|'month',organization_id?:string,format?:'json'|'csv'|'parquet',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function accessByApp(array $params = []): array
    {
        return $this->http->request('GET', '/v1/analytics/reports/access_by_app', query: $params);
    }
}
