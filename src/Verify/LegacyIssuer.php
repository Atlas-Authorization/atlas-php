<?php

declare(strict_types=1);

namespace Atlas\Verify;

use Atlas\Exception\ConfigException;

/**
 * One OLD issuer whose JWTs a {@see SessionVerifier} also accepts during a cutover, today Better
 * Auth's `jwt` plugin tokens.
 *
 * A token whose `iss` is this issuer, inside the window, is verified against THIS issuer's key set
 * only (the JWKS you imported into Atlas, or the old app's `/api/auth/jwks`); `exp` is required, and
 * its `sub` (the Better Auth user id) is mapped to the Atlas user id, because Atlas keeps the Better
 * Auth id as the user's `external_id`. After `$until`, those tokens are refused like any other
 * foreign token.
 */
final class LegacyIssuer
{
    /** Better Auth's `jwt` plugin key algorithms, the default. */
    public const BETTER_AUTH_ALGS = ['EdDSA', 'ES256', 'ES512', 'RS256', 'PS256'];

    /** End of the window, epoch milliseconds. */
    public readonly int $untilMs;

    /** @var list<string> */
    public readonly array $algorithms;

    /** @var list<string>|null */
    public readonly ?array $audience;

    /** @var (\Closure(string,array<string,mixed>):?string)|null */
    public readonly ?\Closure $resolveSubject;

    /**
     * @param string                                         $issuer         the `iss` the old tokens carry (Better Auth: its
     *                                                                       `baseURL`, unless `jwt.issuer` was set)
     * @param \DateTimeInterface|int|string                  $until          end of the acceptance window (a date, epoch ms, or
     *                                                                       an ISO-8601 string); required, never forever
     * @param array{keys:array<int,array<string,mixed>>}|null $jwks          the imported key set; exactly one of `$jwks` / `$jwksUrl`
     * @param string|null                                    $jwksUrl        where to fetch the old key set
     *                                                                       (e.g. `https://app.example.com/api/auth/jwks`)
     * @param string|list<string>|null                       $audience       expected `aud` (unchecked when null)
     * @param list<string>|null                              $algorithms     accepted algorithms; default {@see BETTER_AUTH_ALGS}
     * @param callable(string,array<string,mixed>):?string|null $resolveSubject map the old `sub` to the Atlas user id; default:
     *                                                                       look the user up by `external_id` through the Backend
     *                                                                       API (needs `secretKey` on the verifier), cached 5 minutes.
     *                                                                       Return null to refuse the token.
     */
    public function __construct(
        public readonly string $issuer,
        \DateTimeInterface|int|string $until,
        public readonly ?array $jwks = null,
        public readonly ?string $jwksUrl = null,
        string|array|null $audience = null,
        ?array $algorithms = null,
        ?callable $resolveSubject = null,
        public readonly string $kind = 'better_auth',
    ) {
        if ($issuer === '') {
            throw new ConfigException('legacyIssuers: every entry needs an issuer.');
        }
        if (($jwksUrl !== null && $jwksUrl !== '') === ($jwks !== null)) {
            throw new ConfigException("legacyIssuers: \"{$issuer}\" needs exactly one of jwks or jwksUrl.");
        }
        $ms = self::toMs($until);
        if ($ms <= 0) {
            throw new ConfigException("legacyIssuers: \"{$issuer}\" needs a valid until (the end of the window).");
        }
        $this->untilMs = $ms;
        $algs = array_values($algorithms ?? self::BETTER_AUTH_ALGS);
        foreach ($algs as $alg) {
            if (!in_array($alg, self::BETTER_AUTH_ALGS, true)) {
                throw new ConfigException("legacyIssuers: \"{$issuer}\" lists an unsupported algorithm \"{$alg}\".");
            }
        }
        $this->algorithms = $algs;
        $this->audience = $audience === null ? null : (is_array($audience) ? array_values($audience) : [$audience]);
        $this->resolveSubject = $resolveSubject === null ? null : \Closure::fromCallable($resolveSubject);
    }

    /** The window end in epoch ms (0 when unparseable). */
    public static function toMs(\DateTimeInterface|int|string $until): int
    {
        if ($until instanceof \DateTimeInterface) {
            return (int) $until->format('Uv');
        }
        if (is_int($until)) {
            return $until;
        }
        if (trim($until) === '') {
            return 0;
        }
        try {
            return (int) (new \DateTimeImmutable(trim($until)))->format('Uv');
        } catch (\Exception) {
            return 0;
        }
    }
}
