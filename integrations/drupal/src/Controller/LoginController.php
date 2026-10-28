<?php

declare(strict_types=1);

namespace Drupal\atlas_auth\Controller;

use Drupal\atlas_auth\Core\AtlasOidc;
use Drupal\atlas_auth\Core\LoginException;
use Drupal\atlas_auth\Core\OidcException;
use Drupal\atlas_auth\Core\Options;
use Drupal\atlas_auth\Core\UserResolver;
use Drupal\atlas_auth\Core\UserStore;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The two endpoints of "Log in with Atlas": start (redirect to the authorize
 * endpoint with PKCE, state and nonce) and callback (verify the state, exchange the
 * code, verify the ID token locally, map it to a user, log in).
 */
final class LoginController extends ControllerBase {

  /** Seconds a login may stay open between start and callback. */
  private const FLOW_TTL = 600;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly UserStore $store,
    private readonly LoggerInterface $logger,
  ) {
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('http_client'),
      $container->get('atlas_auth.user_store'),
      $container->get('logger.channel.user'),
    );
  }

  public function start(Request $request): Response {
    $options = $this->options();
    if (!$options->isConfigured()) {
      throw new NotFoundHttpException();
    }
    $state = bin2hex(random_bytes(20));
    $nonce = bin2hex(random_bytes(20));
    $authorize = $this->oidc($options)->buildAuthorizeUrl($state, $nonce);

    $destination = (string) $request->query->get('destination', '');
    $request->getSession()->set('atlas_auth.flow.' . $state, [
      'code_verifier' => $authorize['code_verifier'],
      'nonce' => $nonce,
      'destination' => $this->isLocalPath($destination) ? $destination : '',
      'expires' => time() + self::FLOW_TTL,
    ]);

    $response = new TrustedRedirectResponse($authorize['url']);
    $response->addCacheableDependency($this->config('atlas_auth.settings'));
    $response->setPrivate();
    return $response;
  }

  public function callback(Request $request): Response {
    $state = (string) $request->query->get('state', '');
    $code = (string) $request->query->get('code', '');
    $session = $request->getSession();
    $key = 'atlas_auth.flow.' . $state;
    $flow = $state !== '' ? $session->get($key) : NULL;
    // A state is single use: whatever happens next, it cannot be replayed.
    $session->remove($key);

    if ($code === '' || !is_array($flow) || ($flow['expires'] ?? 0) < time()) {
      return $this->fail('unknown_or_expired_state');
    }

    $options = $this->options();
    try {
      $identity = $this->oidc($options)->handleCallback($code, (string) $flow['code_verifier'], (string) $flow['nonce']);
      $userId = UserResolver::resolveUser($identity, $options, $this->store);
      $this->store->signIn($userId);
    }
    catch (OidcException | LoginException $e) {
      return $this->fail($e->getMessage());
    }
    catch (\Throwable $e) {
      // Fail closed on anything unexpected, without telling the visitor why.
      return $this->fail('unexpected_error');
    }

    $destination = (string) ($flow['destination'] ?? '');
    $target = $destination !== '' ? $destination : Url::fromRoute('<front>')->toString();
    return new RedirectResponse($target);
  }

  private function options(): Options {
    return Options::fromArray($this->config('atlas_auth.settings')->get() ?? []);
  }

  private function oidc(Options $options): AtlasOidc {
    $redirect = Url::fromRoute('atlas_auth.callback', [], ['absolute' => TRUE])->toString();
    return AtlasOidc::fromOptions($options, $redirect, httpClient: $this->httpClient);
  }

  private function fail(string $reason): Response {
    // The reason is a coarse code for the log; the visitor only learns that it failed.
    $this->logger->warning('Log in with Atlas failed: @reason', ['@reason' => $reason]);
    $this->messenger()->addError($this->t('Logging in with Atlas failed. Please try again or use another method.'));
    return new RedirectResponse(Url::fromRoute('user.login')->toString());
  }

  private function isLocalPath(string $path): bool {
    return $path !== '' && $path[0] === '/' && !str_starts_with($path, '//') && !str_contains($path, '\\');
  }

}
