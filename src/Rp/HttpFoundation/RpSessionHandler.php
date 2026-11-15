<?php

declare(strict_types=1);

namespace Atlas\Rp\HttpFoundation;

use Atlas\Verify\LogoutTokenClaims;
use Symfony\Component\HttpFoundation\Request;

/** Your session, your cookie: what the Laravel and Symfony sign-in routes call around Atlas's redirect flow. */
interface RpSessionHandler
{
    /**
     * Start your session for the verified user.
     *
     * @param array<string,mixed> $claims the verified ID token claims (`sub`, `sid`, `email`, ...)
     * @param array<string,mixed> $tokens the token response (`access_token`, `id_token`, `refresh_token`, ...)
     *
     * @return list<\Symfony\Component\HttpFoundation\Cookie> the cookies that carry it
     */
    public function signedIn(array $claims, array $tokens, Request $request): array;

    /**
     * End your session.
     *
     * @return array{sid?:?string,idToken?:?string,cookies?:list<\Symfony\Component\HttpFoundation\Cookie>}
     */
    public function signedOut(Request $request): array;

    /**
     * A back-channel logout arrived: end these of your sessions.
     *
     * @param list<string> $appSessionIds the sessions bound to the logout's `sid` (empty when none)
     */
    public function backchannelLogout(array $appSessionIds, LogoutTokenClaims $claims): void;
}
