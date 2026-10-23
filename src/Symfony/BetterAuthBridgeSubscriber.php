<?php

declare(strict_types=1);

namespace Atlas\Symfony;

use Atlas\BetterAuth\SessionBridge;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Better Auth session bridge for Symfony: a `kernel.request` listener ahead of the firewall, with
 * the same behaviour as the PSR-15 middleware (GET / HEAD only; 303 back with the Atlas cookies set;
 * the sign-in URL when a second factor is owed; Better Auth cookies cleared only on a final refusal,
 * appended on `kernel.response` when the request continues). The end user's IP is
 * `Request::getClientIp()`, so `framework.trusted_proxies` applies.
 */
final class BetterAuthBridgeSubscriber implements EventSubscriberInterface
{
    public const CLEAR_ATTRIBUTE = '_atlas_better_auth_clear_cookies';

    public function __construct(private readonly SessionBridge $bridge, private readonly ?string $signInUrl = null)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 64: before the firewall (8) and the router (32).
        return [KernelEvents::REQUEST => ['onRequest', 64], KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $plan = $this->bridge->plan(
            $request->getMethod(),
            (string) $request->headers->get('Cookie', ''),
            $request->getUri(),
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
            $this->signInUrl,
        );

        if ($plan->redirects()) {
            $response = new RedirectResponse((string) $plan->redirectTo, 303);
            $response->headers->set('Cache-Control', 'no-store');
            foreach ($plan->setCookies as $header) {
                $response->headers->set('Set-Cookie', $header, false);
            }
            $event->setResponse($response);

            return;
        }
        if ($plan->setCookies !== []) {
            $request->attributes->set(self::CLEAR_ATTRIBUTE, $plan->setCookies);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $headers = $event->getRequest()->attributes->get(self::CLEAR_ATTRIBUTE);
        if (!$event->isMainRequest() || !is_array($headers)) {
            return;
        }
        foreach ($headers as $header) {
            $event->getResponse()->headers->set('Set-Cookie', (string) $header, false);
        }
    }
}
