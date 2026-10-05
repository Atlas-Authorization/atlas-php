<?php

declare(strict_types=1);

namespace Atlas\Tests\Symfony;

use Atlas\Symfony\AtlasAuthenticator;
use Atlas\Symfony\AtlasUser;
use Atlas\Tests\Support\VerifierHarness;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * The Symfony Security authenticator: supports() over the two token carriers,
 * authenticate() yielding a passport whose user carries the Atlas subject, and
 * the rejection of missing/invalid tokens. The verifier underneath is the real
 * {@see \Atlas\Verify\SessionVerifier} primed off a mocked PSR-18 client.
 */
final class AtlasAuthenticatorTest extends TestCase
{
    use VerifierHarness;

    public function testSupportsBearerHeader(): void
    {
        $authenticator = new AtlasAuthenticator($this->verifier());
        $request = Request::create('/', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->mint()]);

        $this->assertTrue($authenticator->supports($request));
    }

    public function testSupportsSessionCookie(): void
    {
        $authenticator = new AtlasAuthenticator($this->verifier());
        $request = Request::create('/', 'GET', cookies: ['__session' => $this->mint()]);

        $this->assertTrue($authenticator->supports($request));
    }

    public function testDoesNotSupportRequestWithoutToken(): void
    {
        $authenticator = new AtlasAuthenticator($this->verifier());

        $this->assertFalse($authenticator->supports(Request::create('/', 'GET')));
    }

    public function testAuthenticateValidTokenYieldsPassportWithAtlasUser(): void
    {
        $authenticator = new AtlasAuthenticator($this->verifier());
        $request = Request::create('/', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->mint([
                'sub' => 'user_42',
                'org_role' => 'admin',
            ]),
        ]);

        $passport = $authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $user = $passport->getUser();
        $this->assertInstanceOf(AtlasUser::class, $user);
        $this->assertSame('user_42', $user->getUserIdentifier());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertSame('user_42', $user->claims()['sub']);
    }

    public function testAuthenticateFromSessionCookie(): void
    {
        $authenticator = new AtlasAuthenticator($this->verifier());
        $request = Request::create('/', 'GET', cookies: ['__session' => $this->mint(['sub' => 'cookie_user'])]);

        $passport = $authenticator->authenticate($request);
        $this->assertSame('cookie_user', $passport->getUser()->getUserIdentifier());
    }

    public function testAuthenticateInvalidTokenIsRejected(): void
    {
        $authenticator = new AtlasAuthenticator($this->verifier());
        $request = Request::create('/', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->mint(['iss' => 'https://evil.test']),
        ]);

        $this->expectException(AuthenticationException::class);
        $authenticator->authenticate($request);
    }

    public function testAuthenticateMissingTokenIsRejected(): void
    {
        $authenticator = new AtlasAuthenticator($this->verifier());

        $this->expectException(AuthenticationException::class);
        $authenticator->authenticate(Request::create('/', 'GET'));
    }

    public function testOnAuthenticationFailureReturns401(): void
    {
        $authenticator = new AtlasAuthenticator($this->verifier());
        $response = $authenticator->onAuthenticationFailure(
            Request::create('/', 'GET'),
            new AuthenticationException('nope'),
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testOnAuthenticationSuccessLetsRequestProceed(): void
    {
        $authenticator = new AtlasAuthenticator($this->verifier());
        $token = $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);

        $this->assertNull($authenticator->onAuthenticationSuccess(Request::create('/', 'GET'), $token, 'main'));
    }
}
