<?php

declare(strict_types=1);

namespace Atlas\Tests\Support;

use Atlas\Verify\SessionVerifier;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Shared helper for the framework-integration tests: mints a valid RS256 session
 * token with the SDK's existing {@see Keys} key material, and builds a
 * {@see SessionVerifier} primed to serve the matching JWKS off the mocked PSR-18
 * client — exactly as {@see \Atlas\Tests\SessionVerifierTest} does, so the
 * middleware/authenticator are exercised against the real verifier, no network.
 */
trait VerifierHarness
{
    private Keys $harnessKeys;
    private int $harnessNow = 1_700_000_000;

    private function keys(): Keys
    {
        return $this->harnessKeys ??= new Keys();
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function mint(array $overrides = []): string
    {
        $claims = array_merge([
            'iss' => 'https://issuer.test',
            'sub' => 'user_1',
            'sid' => 'sess_1',
            'iat' => $this->harnessNow - 5,
            'nbf' => $this->harnessNow - 5,
            'exp' => $this->harnessNow + 60,
        ], $overrides);

        return JWT::encode($claims, $this->keys()->privateKeyPem, 'RS256', $this->keys()->kid);
    }

    private function verifier(): SessionVerifier
    {
        $mock = new MockClient();
        $mock->push(200, $this->keys()->jwks);

        return new SessionVerifier(
            jwksUrl: 'https://issuer.test/.well-known/jwks.json',
            issuer: 'https://issuer.test',
            httpClient: $mock,
            requestFactory: new HttpFactory(),
            streamFactory: new HttpFactory(),
            now: fn (): int => $this->harnessNow,
        );
    }
}
