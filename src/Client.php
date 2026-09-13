<?php

declare(strict_types=1);

namespace Atlas;

use Atlas\Exception\ConfigException;
use Atlas\Resources\Actions;
use Atlas\Resources\ActorTokens;
use Atlas\Resources\ApiKeys;
use Atlas\Resources\AttackProtection;
use Atlas\Resources\AuditLogs;
use Atlas\Resources\Billing;
use Atlas\Resources\BotSignals;
use Atlas\Resources\Branding;
use Atlas\Resources\DataSubjectRequests;
use Atlas\Resources\Domains;
use Atlas\Resources\EmailTemplates;
use Atlas\Resources\Fga;
use Atlas\Resources\ImportExport;
use Atlas\Resources\Instance;
use Atlas\Resources\InstanceSecurity;
use Atlas\Resources\Invitations;
use Atlas\Resources\JwtTemplates;
use Atlas\Resources\Localizations;
use Atlas\Resources\LogStreams;
use Atlas\Resources\LtiPlatforms;
use Atlas\Resources\ManagedWaf;
use Atlas\Resources\Messaging;
use Atlas\Resources\NetworkAcls;
use Atlas\Resources\OAuthClients;
use Atlas\Resources\OAuthProviders;
use Atlas\Resources\Organizations;
use Atlas\Resources\Permissions;
use Atlas\Resources\RadiusClients;
use Atlas\Resources\RateLimitPolicy;
use Atlas\Resources\ResourceServers;
use Atlas\Resources\Restriction;
use Atlas\Resources\RiskBasedMfa;
use Atlas\Resources\Roles;
use Atlas\Resources\ScimProvisioning;
use Atlas\Resources\ScimTokens;
use Atlas\Resources\Sessions;
use Atlas\Resources\SignInTokens;
use Atlas\Resources\SmsTemplates;
use Atlas\Resources\SsoConnections;
use Atlas\Resources\SsoOnboarding;
use Atlas\Resources\Tokens;
use Atlas\Resources\Users;
use Atlas\Resources\Waitlist;
use Atlas\Resources\Webhooks;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The typed management client for the Atlas Backend API — the secret-key
 * surface, the peer of `@atlas/backend`'s `createAtlasClient` and
 * `@clerk/backend`'s `createClerkClient`.
 *
 * Construct it with a secret key; everything else has a default:
 *
 *     $atlas = new \Atlas\Client('sk_live_…');
 *     $user  = $atlas->users->get('user_123');
 *
 * Each namespace is a `readonly` property holding a resource object, so this
 * class is a table of contents you can read top to bottom to see the whole
 * surface — 45 namespaces, mirroring the TypeScript SDK one-for-one.
 *
 * The transport is PSR-18 + PSR-17. When no client and factories are supplied,
 * Guzzle (a hard dependency) is auto-discovered, so the zero-config path needs
 * only a key. Any conforming stack can be injected instead — which is exactly
 * how the test suite runs with no network.
 */
final class Client
{
    public readonly Users $users;
    public readonly Sessions $sessions;
    public readonly Organizations $organizations;
    public readonly Roles $roles;
    public readonly Permissions $permissions;
    public readonly OAuthClients $oauthClients;
    public readonly ResourceServers $resourceServers;
    public readonly SsoConnections $ssoConnections;
    public readonly ScimTokens $scimTokens;
    public readonly Domains $domains;
    public readonly Waitlist $waitlist;
    public readonly Restriction $allowlist;
    public readonly Restriction $blocklist;
    public readonly AttackProtection $attackProtection;
    public readonly ActorTokens $actorTokens;
    public readonly Invitations $invitations;
    public readonly Webhooks $webhooks;
    public readonly SignInTokens $signInTokens;
    public readonly AuditLogs $auditLogs;
    public readonly JwtTemplates $jwtTemplates;
    public readonly ApiKeys $apiKeys;
    public readonly OAuthProviders $oauthProviders;
    public readonly SsoOnboarding $ssoOnboarding;
    public readonly ScimProvisioning $scimProvisioning;
    public readonly Fga $fga;
    public readonly RateLimitPolicy $rateLimitPolicy;
    public readonly RiskBasedMfa $riskBasedMfa;
    public readonly BotSignals $botSignals;
    public readonly NetworkAcls $networkAcls;
    public readonly ManagedWaf $managedWaf;
    public readonly LogStreams $logStreams;
    public readonly Branding $branding;
    public readonly EmailTemplates $emailTemplates;
    public readonly SmsTemplates $smsTemplates;
    public readonly Localizations $localizations;
    public readonly Actions $actions;
    public readonly Billing $billing;
    public readonly Messaging $messaging;
    public readonly ImportExport $importExport;
    public readonly DataSubjectRequests $dataSubjectRequests;
    public readonly RadiusClients $radiusClients;
    public readonly LtiPlatforms $ltiPlatforms;
    public readonly Instance $instance;
    public readonly InstanceSecurity $instanceSecurity;
    public readonly Tokens $tokens;

    /** The shared, config-bound transport — exposed for advanced callers. */
    public readonly Http $http;

    /**
     * @param string                        $secretKey      the instance secret key (`sk_…`); sent only as a Bearer token
     * @param string                        $baseUrl        the BAPI origin; defaults to {@see Http::DEFAULT_API_URL}
     * @param ClientInterface|null           $httpClient     a PSR-18 client; Guzzle is auto-discovered when null
     * @param RequestFactoryInterface|null   $requestFactory a PSR-17 request factory; Guzzle is auto-discovered when null
     * @param StreamFactoryInterface|null    $streamFactory  a PSR-17 stream factory; Guzzle is auto-discovered when null
     *
     * @throws ConfigException on an empty key, or when nothing is injected and Guzzle is absent
     */
    public function __construct(
        string $secretKey,
        string $baseUrl = Http::DEFAULT_API_URL,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        [$client, $reqFactory, $strFactory] = self::discover($httpClient, $requestFactory, $streamFactory);

        $http = new Http($secretKey, $baseUrl, $client, $reqFactory, $strFactory);
        $this->http = $http;

        $this->users = new Users($http);
        $this->sessions = new Sessions($http);
        $this->organizations = new Organizations($http);
        $this->roles = new Roles($http);
        $this->permissions = new Permissions($http);
        $this->oauthClients = new OAuthClients($http);
        $this->resourceServers = new ResourceServers($http);
        $this->ssoConnections = new SsoConnections($http);
        $this->scimTokens = new ScimTokens($http);
        $this->domains = new Domains($http);
        $this->waitlist = new Waitlist($http);
        $this->allowlist = new Restriction($http, '/v1/allowlist_identifiers');
        $this->blocklist = new Restriction($http, '/v1/blocklist_identifiers');
        $this->attackProtection = new AttackProtection($http);
        $this->actorTokens = new ActorTokens($http);
        $this->invitations = new Invitations($http);
        $this->webhooks = new Webhooks($http);
        $this->signInTokens = new SignInTokens($http);
        $this->auditLogs = new AuditLogs($http);
        $this->jwtTemplates = new JwtTemplates($http);
        $this->apiKeys = new ApiKeys($http);
        $this->oauthProviders = new OAuthProviders($http);
        $this->ssoOnboarding = new SsoOnboarding($http);
        $this->scimProvisioning = new ScimProvisioning($http);
        $this->fga = new Fga($http);
        $this->rateLimitPolicy = new RateLimitPolicy($http);
        $this->riskBasedMfa = new RiskBasedMfa($http);
        $this->botSignals = new BotSignals($http);
        $this->networkAcls = new NetworkAcls($http);
        $this->managedWaf = new ManagedWaf($http);
        $this->logStreams = new LogStreams($http);
        $this->branding = new Branding($http);
        $this->emailTemplates = new EmailTemplates($http);
        $this->smsTemplates = new SmsTemplates($http);
        $this->localizations = new Localizations($http);
        $this->actions = new Actions($http);
        $this->billing = new Billing($http);
        $this->messaging = new Messaging($http);
        $this->importExport = new ImportExport($http);
        $this->dataSubjectRequests = new DataSubjectRequests($http);
        $this->radiusClients = new RadiusClients($http);
        $this->ltiPlatforms = new LtiPlatforms($http);
        $this->instance = new Instance($http);
        $this->instanceSecurity = new InstanceSecurity($http);
        $this->tokens = new Tokens($http);
    }

    /**
     * Fill any missing transport piece from Guzzle. Injecting even one part
     * (e.g. a mock client in a test) leaves the others to auto-discovery, so a
     * caller overrides only what they care about.
     *
     * @return array{0:ClientInterface,1:RequestFactoryInterface,2:StreamFactoryInterface}
     */
    private static function discover(
        ?ClientInterface $client,
        ?RequestFactoryInterface $requestFactory,
        ?StreamFactoryInterface $streamFactory,
    ): array {
        $client ??= self::guzzleClient();

        if ($requestFactory === null || $streamFactory === null) {
            $factory = self::guzzleFactory();
            $requestFactory ??= $factory;
            $streamFactory ??= $factory;
        }

        return [$client, $requestFactory, $streamFactory];
    }

    private static function guzzleClient(): ClientInterface
    {
        if (!class_exists(\GuzzleHttp\Client::class)) {
            throw new ConfigException(
                'No PSR-18 HTTP client was provided and guzzlehttp/guzzle is not installed. '
                . 'Install it (composer require guzzlehttp/guzzle) or pass your own client to Atlas\\Client.',
            );
        }

        $client = new \GuzzleHttp\Client([
            // A backend management call should fail fast, not hang a request thread.
            'timeout' => 30,
            'connect_timeout' => 10,
            // Errors are shaped by Atlas\Http into AtlasApiException; let it see the body.
            'http_errors' => false,
        ]);

        if (!$client instanceof ClientInterface) {
            throw new ConfigException('The installed Guzzle version is not PSR-18 compatible; require guzzlehttp/guzzle ^7.');
        }

        return $client;
    }

    private static function guzzleFactory(): RequestFactoryInterface&StreamFactoryInterface
    {
        if (!class_exists(\GuzzleHttp\Psr7\HttpFactory::class)) {
            throw new ConfigException(
                'No PSR-17 factories were provided and guzzlehttp/psr7 is not installed. '
                . 'Install it or pass your own request/stream factories to Atlas\\Client.',
            );
        }

        $factory = new \GuzzleHttp\Psr7\HttpFactory();

        if (!$factory instanceof RequestFactoryInterface || !$factory instanceof StreamFactoryInterface) {
            throw new ConfigException('The installed guzzlehttp/psr7 does not provide PSR-17 factories; require ^2.');
        }

        return $factory;
    }
}
