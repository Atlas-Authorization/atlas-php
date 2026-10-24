<?php

declare(strict_types=1);

namespace Atlas\PartnerLink;

use Atlas\ErrorItem;
use Atlas\Exception\AtlasApiException;
use Atlas\Exception\ConfigException;
use Atlas\Http;
use Atlas\Webhook\Webhook;
use Atlas\Webhook\WebhookVerification;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * The partner product's side of the partner-link request protocol (`atlas.partner_link_request.v1`). A partner needs no Atlas
 * credential: it asks an instance for a link, the owner approves it (`$client->partnerLinks->approveRequest()`), and Atlas POSTs the
 * credentials (or a denial) to the partner's `callback_url`, signed with the `request_secret` returned at creation.
 *
 *     $requests = new PartnerLinkRequests();
 *     $created = $requests->create([
 *         'partner' => 'acme', 'resource' => 'ins_123', 'scopes' => ['privacy:execute'],
 *         'callback_url' => 'https://acme.example/atlas/callback',
 *     ]);
 *     store($created['id'], $created['request_secret']);   // shown once
 *
 *     // in the callback route:
 *     $result = PartnerLinkRequests::verifyDelivery(file_get_contents('php://input'), getallheaders(), $storedSecret);
 *     if ($result->verified && ($result->event['object'] ?? '') === 'partner_link.credentials') {
 *         save($result->event['client_id'], $result->event['client_secret']);
 *     }
 */
final class PartnerLinkRequests
{
    public const PROTOCOL = 'atlas.partner_link_request.v1';

    private readonly string $apiUrl;
    private readonly ClientInterface $client;
    private readonly RequestFactoryInterface $requests;

    public function __construct(string $apiUrl = Http::DEFAULT_API_URL, ?ClientInterface $httpClient = null, ?RequestFactoryInterface $requestFactory = null)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        if ($httpClient === null || $requestFactory === null) {
            if (!class_exists(\GuzzleHttp\Client::class)) {
                throw new ConfigException('Pass a PSR-18 client and a PSR-17 request factory, or install guzzlehttp/guzzle.');
            }
            $httpClient ??= new \GuzzleHttp\Client(['timeout' => 30, 'connect_timeout' => 10, 'http_errors' => false]);
            $requestFactory ??= new \GuzzleHttp\Psr7\HttpFactory();
        }
        $this->client = $httpClient;
        $this->requests = $requestFactory;
    }

    /**
     * `POST /v1/partner_link_requests` — ask an instance for a link. Body: `{partner, resource (ins_… or a publishable key), scopes, callback_url, name?, state?, cert_fingerprints?}`.
     *
     * @return array<string,mixed> the request with its one-time `request_secret`
     *
     * @throws AtlasApiException when Atlas refuses
     */
    public function create(array $body): array
    {
        $request = $this->requests->createRequest('POST', $this->apiUrl . '/v1/partner_link_requests')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(\GuzzleHttp\Psr7\Utils::streamFor(json_encode($body, JSON_THROW_ON_ERROR)));

        return $this->send($request);
    }

    /**
     * `GET /v1/partner_link_requests/:id` — the status of a request, read with its `request_secret`.
     *
     * @return array<string,mixed>
     *
     * @throws AtlasApiException when Atlas refuses
     */
    public function get(string $id, string $requestSecret): array
    {
        $request = $this->requests->createRequest('GET', $this->apiUrl . '/v1/partner_link_requests/' . rawurlencode($id))
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $requestSecret);

        return $this->send($request);
    }

    /**
     * Verify and parse what Atlas delivered to your callback: `partner_link.credentials` or `partner_link.denied`. The verified
     * `event` is the delivery. Pass `$url` (your callback URL) to also accept the URL-bound `v2` signature.
     *
     * @param array<string, string|list<string>> $headers
     */
    public static function verifyDelivery(string $body, array $headers, string $requestSecret, ?int $nowMs = null, ?string $url = null): WebhookVerification
    {
        return Webhook::verify($body, $headers, $requestSecret, $nowMs, Webhook::REPLAY_TOLERANCE_MS, $url);
    }

    /**
     * @return array<string,mixed>
     */
    private function send(\Psr\Http\Message\RequestInterface $request): array
    {
        $response = $this->client->sendRequest($request);
        $status = $response->getStatusCode();
        $data = json_decode((string) $response->getBody(), true);
        if ($status < 200 || $status > 299) {
            $items = [];
            if (is_array($data) && isset($data['errors']) && is_array($data['errors'])) {
                foreach ($data['errors'] as $item) {
                    if (is_array($item)) {
                        $items[] = ErrorItem::fromArray($item);
                    }
                }
            }
            if ($items === []) {
                $items[] = new ErrorItem('UNKNOWN', 'Atlas refused the partner link request (HTTP ' . $status . ').');
            }
            throw AtlasApiException::fromResponse($status, $items);
        }

        return is_array($data) ? $data : [];
    }
}
