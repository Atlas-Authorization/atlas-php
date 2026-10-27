<?php

declare(strict_types=1);

namespace Atlas\Resources;

use Atlas\Http;

/**
 * `/v1/analytics` - 7 operations.
 */
final class Analytics extends Resource
{
    public readonly AnalyticsReports $reports;
    public readonly AnalyticsAlertRules $alertRules;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->reports = new AnalyticsReports($http);
        $this->alertRules = new AnalyticsAlertRules($http);
    }

    /**
     * `GET /v1/analytics/funnel` - Where sign-in or sign-up attempts end, per method: started, completed,
     * abandoned, and the step each unfinished attempt stopped at, with the completion rate. Each attempt
     * is counted once, at its final state.
     *
     * @param array{kind?:'sign_in'|'sign_up',from?:int|string,to?:int|string,days?:int,granularity?:'day'|'week'|'month',organization_id?:string,format?:'json'|'csv'|'parquet',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function funnel(array $params = []): array
    {
        return $this->http->request('GET', '/v1/analytics/funnel', query: $params);
    }

    /**
     * `GET /v1/analytics/journey_funnel` - What happened at each step of a sign-in journey: how often it
     * held a sign-in (a required action or form), was completed, ran at the sign-in (an Action, claim or
     * redirect) or refused it, with the completion rate of its holds. Steps follow the active version; a
     * sub-flow's steps are prefixed with the sub-flow step id. No organization slice.
     *
     * @param array{journey_id?:string,from?:int|string,to?:int|string,days?:int,granularity?:'day'|'week'|'month',organization_id?:string,format?:'json'|'csv'|'parquet',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function journeyFunnel(array $params = []): array
    {
        return $this->http->request('GET', '/v1/analytics/journey_funnel', query: $params);
    }

    /**
     * `GET /v1/analytics/failures` - Failed first- and second-factor attempts by reason and method, most
     * frequent first. No organization slice (a failed attempt belongs to no organization).
     *
     * @param array{from?:int|string,to?:int|string,days?:int,granularity?:'day'|'week'|'month',organization_id?:string,format?:'json'|'csv'|'parquet',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function failures(array $params = []): array
    {
        return $this->http->request('GET', '/v1/analytics/failures', query: $params);
    }

    /**
     * `GET /v1/analytics/breakdown` - Sign-ins split by one dimension: method, device type, browser,
     * operating system or country (country needs a GeoIP database on the deployment; otherwise it reports
     * `unknown`).
     *
     * @param array{dimension?:'method'|'device'|'browser'|'os'|'country',from?:int|string,to?:int|string,days?:int,granularity?:'day'|'week'|'month',organization_id?:string,format?:'json'|'csv'|'parquet',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function breakdown(array $params = []): array
    {
        return $this->http->request('GET', '/v1/analytics/breakdown', query: $params);
    }

    /**
     * `GET /v1/analytics/active_users` - Distinct active users and sign-ins per bucket, each bucket split
     * into new users (signed up in that bucket) and returning users, plus trailing 1/7/30-day active-user
     * counts (dau, wau, mau) ending at `to`.
     *
     * @param array{from?:int|string,to?:int|string,days?:int,granularity?:'day'|'week'|'month',organization_id?:string,format?:'json'|'csv'|'parquet',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function activeUsers(array $params = []): array
    {
        return $this->http->request('GET', '/v1/analytics/active_users', query: $params);
    }

    /**
     * `GET /v1/analytics/passkey_adoption` - How many users hold a passkey (adoption rate), and the
     * passkey share of sign-ins per bucket.
     *
     * @param array{from?:int|string,to?:int|string,days?:int,granularity?:'day'|'week'|'month',organization_id?:string,format?:'json'|'csv'|'parquet',limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function passkeyAdoption(array $params = []): array
    {
        return $this->http->request('GET', '/v1/analytics/passkey_adoption', query: $params);
    }

    /**
     * `GET /v1/analytics/api_usage` - API usage per subject against its own baseline: secret keys calling
     * the Backend API, end-user API keys verified through /v1/api_keys/verify, and organizations (Backend
     * API calls on the organization plus verifications of its keys). For each: requests and the failure
     * rate in the window, the average hourly rate over the 7 days before, and the ratio between them,
     * highest ratio first (at most 100). Counts are written in batches and can lag by up to a minute.
     *
     * @param array{subject_type?:'api_key'|'end_user_api_key'|'organization',subject_id?:string,window_minutes?:int,limit?:int,starting_after?:string} $params
     *
     * @return array<string,mixed>
     */
    public function apiUsage(array $params = []): array
    {
        return $this->http->request('GET', '/v1/analytics/api_usage', query: $params);
    }
}
