<?php

declare(strict_types=1);

namespace Atlas\Verify;

/**
 * DPoP proof verification (RFC 9449) for {@see AccessTokenVerifier}.
 *
 * A DPoP-bound access token carries `cnf.jkt`, the RFC 7638 thumbprint of the
 * client's key. Each request must then arrive with the `DPoP` scheme and a
 * `DPoP` header: a short-lived JWT (`typ` `dpop+jwt`) signed by that key,
 * carrying the public key itself, the request method (`htm`) and URL (`htu`),
 * the hash of the access token (`ath`), a unique `jti`, and the server's nonce
 * when the API asks for one.
 */
final class Dpop
{
    /** How old a DPoP proof may be. */
    public const PROOF_MAX_AGE_SECONDS = 300;
    /** Clock skew tolerated on a proof's `iat`. */
    public const CLOCK_SKEW_SECONDS = 5;
    /** The JWS algorithms accepted on a DPoP proof (and advertised in challenges / metadata). */
    public const ALGS = ['ES256', 'ES384', 'ES512', 'RS256', 'RS384', 'RS512', 'PS256', 'PS384', 'PS512', 'EdDSA'];

    private const PRIVATE_MEMBERS = ['d', 'k', 'p', 'q', 'dp', 'dq', 'qi'];
    /** alg => [crv, curve OID (DER), coordinate size, hash] */
    private const EC = [
        'ES256' => ['P-256', "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07", 32, 'sha256'],
        'ES384' => ['P-384', "\x06\x05\x2b\x81\x04\x00\x22", 48, 'sha384'],
        'ES512' => ['P-521', "\x06\x05\x2b\x81\x04\x00\x23", 66, 'sha512'],
    ];
    private const OID_EC_PUBLIC_KEY = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";
    private const OID_RSA = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01";

    private function __construct()
    {
    }

    public static function b64u(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $value): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]*$/', $value) !== 1) {
            return null;
        }
        $raw = base64_decode(strtr($value, '-_', '+/'), true);

        return $raw === false ? null : $raw;
    }

    /** The `ath` a DPoP proof carries for `$token`: base64url(SHA-256(token)). */
    public static function accessTokenHash(string $token): string
    {
        return self::b64u(hash('sha256', $token, true));
    }

    /**
     * The RFC 7638 SHA-256 thumbprint (base64url) of a public EC, RSA or OKP
     * JWK: the `cnf.jkt` a DPoP-bound token carries.
     *
     * @param array<string,mixed> $jwk
     *
     * @throws \InvalidArgumentException for an unsupported or incomplete key
     */
    public static function jwkThumbprint(array $jwk): string
    {
        $members = match ($jwk['kty'] ?? null) {
            'EC' => ['crv', 'kty', 'x', 'y'],
            'RSA' => ['e', 'kty', 'n'],
            'OKP' => ['crv', 'kty', 'x'],
            default => throw new \InvalidArgumentException('Unsupported JWK kty.'),
        };
        $picked = [];
        foreach ($members as $m) {
            if (!is_string($jwk[$m] ?? null) || $jwk[$m] === '') {
                throw new \InvalidArgumentException("JWK member {$m} is missing.");
            }
            $picked[$m] = $jwk[$m];
        }

        return self::b64u(hash('sha256', json_encode($picked, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), true));
    }

    /**
     * Reduce a URL to the form RFC 9449 §4.3 compares `htu` in: scheme and host
     * lower-cased, the default port dropped, an empty path as "/", no query or
     * fragment. Null for anything but an absolute http(s) URL.
     */
    public static function normalizeHtu(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        if ($port !== null && !(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
            $host .= ':' . $port;
        }
        $path = ($parts['path'] ?? '') === '' ? '/' : $parts['path'];

        return $scheme . '://' . $host . $path;
    }

    /**
     * Check one proof. Null when it is good, else `[error, description]` with
     * error `invalid_dpop_proof` or `use_dpop_nonce`.
     *
     * @return array{0:string,1:string}|null
     */
    public static function checkProof(
        string $proof,
        string $token,
        string $jkt,
        string $method,
        string $url,
        ?string $publicOrigin,
        int|float $now,
        JtiStore $replay,
        ?DpopNonceSource $nonces,
    ): ?array {
        $bad = static fn (string $d): array => ['invalid_dpop_proof', $d];
        $parts = explode('.', $proof);
        if (count($parts) !== 3) {
            return $bad('The DPoP proof is malformed.');
        }
        $header = self::json($parts[0]);
        if ($header === null) {
            return $bad('The DPoP proof is malformed.');
        }
        if (($header['typ'] ?? null) !== 'dpop+jwt') {
            return $bad('The DPoP proof must have typ dpop+jwt.');
        }
        $alg = $header['alg'] ?? null;
        if (!is_string($alg) || !in_array($alg, self::ALGS, true)) {
            return $bad('The DPoP proof uses an unsupported algorithm.');
        }
        $jwk = $header['jwk'] ?? null;
        if (!is_array($jwk) || array_is_list($jwk) && $jwk !== []) {
            return $bad('The DPoP proof must carry its public key.');
        }
        foreach (self::PRIVATE_MEMBERS as $m) {
            if (array_key_exists($m, $jwk)) {
                return $bad('The DPoP proof key must be a public key.');
            }
        }
        $sig = self::b64uDecode($parts[2]);
        if ($sig === null || !self::verifySignature($jwk, $alg, $parts[0] . '.' . $parts[1], $sig)) {
            return $bad('The DPoP proof signature is invalid.');
        }
        $payload = self::json($parts[1]);
        if ($payload === null) {
            return $bad('The DPoP proof is malformed.');
        }
        try {
            $thumb = self::jwkThumbprint($jwk);
        } catch (\InvalidArgumentException) {
            $thumb = '';
        }
        if ($thumb === '' || !hash_equals($thumb, $jkt)) {
            return $bad('The DPoP proof key does not match the token binding.');
        }
        if (($payload['htm'] ?? null) !== $method) {
            return $bad('The DPoP proof htm does not match the request method.');
        }
        $target = $url;
        if (preg_match('#^https?://#i', $url) !== 1) {
            $target = $publicOrigin !== null ? rtrim($publicOrigin, '/') . '/' . ltrim($url, '/') : null;
        }
        $expected = $target !== null ? self::normalizeHtu($target) : null;
        $htu = $payload['htu'] ?? null;
        if ($expected === null || !is_string($htu) || self::normalizeHtu($htu) !== $expected) {
            return $bad('The DPoP proof htu does not match the request URL.');
        }
        $iat = $payload['iat'] ?? null;
        if (!is_int($iat) && !is_float($iat) || $iat > $now + self::CLOCK_SKEW_SECONDS || $now - $iat > self::PROOF_MAX_AGE_SECONDS) {
            return $bad('The DPoP proof is too old or from the future.');
        }
        $exp = $payload['exp'] ?? null;
        if ((is_int($exp) || is_float($exp)) && $now >= $exp + self::CLOCK_SKEW_SECONDS) {
            return $bad('The DPoP proof is too old or from the future.');
        }
        if (($payload['ath'] ?? null) !== self::accessTokenHash($token)) {
            return $bad('The DPoP proof ath does not match the access token.');
        }
        $jti = $payload['jti'] ?? null;
        if (!is_string($jti) || $jti === '') {
            return $bad('The DPoP proof needs a jti.');
        }
        if ($nonces !== null) {
            $nonce = $payload['nonce'] ?? null;
            if (!is_string($nonce) || $nonce === '' || !$nonces->valid($nonce)) {
                return ['use_dpop_nonce', 'The DPoP proof must carry the nonce from the DPoP-Nonce header.'];
            }
        }
        $ttl = max(1, (int) ceil($iat + self::PROOF_MAX_AGE_SECONDS - $now));
        if (!$replay->add($jkt . ':' . $jti, $ttl)) {
            return $bad('The DPoP proof has already been used.');
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private static function json(string $segment): ?array
    {
        $raw = self::b64uDecode($segment);
        if ($raw === null) {
            return null;
        }
        try {
            $v = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($v) && (!array_is_list($v) || $v === []) ? $v : null;
    }

    /** @param array<string,mixed> $jwk */
    private static function verifySignature(array $jwk, string $alg, string $input, string $sig): bool
    {
        if (is_string($jwk['alg'] ?? null) && $jwk['alg'] !== '' && $jwk['alg'] !== $alg) {
            return false;
        }
        $str = static fn (string $k): ?string => is_string($jwk[$k] ?? null) ? self::b64uDecode($jwk[$k]) : null;
        if (isset(self::EC[$alg])) {
            [$crv, $curveOid, $size, $hash] = self::EC[$alg];
            $x = $str('x');
            $y = $str('y');
            if (($jwk['kty'] ?? null) !== 'EC' || ($jwk['crv'] ?? null) !== $crv || strlen($sig) !== 2 * $size
                || $x === null || $y === null || strlen($x) !== $size || strlen($y) !== $size) {
                return false;
            }
            $spki = self::seq(self::seq(self::OID_EC_PUBLIC_KEY . $curveOid) . self::bitString("\x04" . $x . $y));
            $der = self::seq(self::int(substr($sig, 0, $size)) . self::int(substr($sig, $size)));

            return self::opensslVerify($spki, $input, $der, $hash);
        }
        if (str_starts_with($alg, 'RS') || str_starts_with($alg, 'PS')) {
            $n = $str('n');
            $e = $str('e');
            if (($jwk['kty'] ?? null) !== 'RSA' || $n === null || $e === null || $e === '' || strlen($e) > 4) {
                return false;
            }
            $n = ltrim($n, "\x00");
            if (strlen($n) < 256 || strlen($sig) !== strlen($n)) {
                return false;
            }
            $spki = self::seq(self::seq(self::OID_RSA . "\x05\x00") . self::bitString(self::seq(self::int($n) . self::int($e))));
            $hash = 'sha' . substr($alg, 2);
            if ($alg[0] === 'R') {
                return self::opensslVerify($spki, $input, $sig, $hash);
            }

            return self::verifyPss($spki, strlen($n), $input, $sig, $hash);
        }
        if ($alg === 'EdDSA') {
            $x = $str('x');
            if (($jwk['kty'] ?? null) !== 'OKP' || ($jwk['crv'] ?? null) !== 'Ed25519' || $x === null
                || strlen($x) !== 32 || strlen($sig) !== 64 || !function_exists('sodium_crypto_sign_verify_detached')) {
                return false;
            }
            try {
                return sodium_crypto_sign_verify_detached($sig, $input, $x);
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    private static function opensslVerify(string $spkiDer, string $input, string $sig, string $hash): bool
    {
        $key = openssl_pkey_get_public(self::pem($spkiDer));
        if ($key === false) {
            return false;
        }

        return openssl_verify($input, $sig, $key, $hash) === 1;
    }

    /** RSASSA-PSS verification (RFC 8017 §8.1.2) with MGF1 over the same hash and a hash-length salt. */
    private static function verifyPss(string $spkiDer, int $k, string $input, string $sig, string $hash): bool
    {
        $key = openssl_pkey_get_public(self::pem($spkiDer));
        if ($key === false) {
            return false;
        }
        $em = '';
        if (!openssl_public_decrypt($sig, $em, $key, OPENSSL_NO_PADDING)) {
            return false;
        }
        $details = openssl_pkey_get_details($key);
        $modBits = is_array($details) ? (int) $details['bits'] : $k * 8;
        $emBits = $modBits - 1;
        $emLen = intdiv($emBits + 7, 8);
        $em = substr(str_pad($em, $k, "\x00", STR_PAD_LEFT), $k - $emLen);
        $hLen = strlen(hash($hash, '', true));
        $sLen = $hLen;
        $mHash = hash($hash, $input, true);
        if ($emLen < $hLen + $sLen + 2 || substr($em, -1) !== "\xbc") {
            return false;
        }
        $maskedDb = substr($em, 0, $emLen - $hLen - 1);
        $h = substr($em, $emLen - $hLen - 1, $hLen);
        $zeroBits = 8 * $emLen - $emBits;
        $topMask = $zeroBits > 0 ? (0xff << (8 - $zeroBits)) & 0xff : 0;
        if ((ord($maskedDb[0]) & $topMask) !== 0) {
            return false;
        }
        $dbMask = self::mgf1($h, $emLen - $hLen - 1, $hash);
        $db = $maskedDb ^ $dbMask;
        $db[0] = chr(ord($db[0]) & (0xff >> $zeroBits));
        $psLen = $emLen - $hLen - $sLen - 2;
        if (strspn($db, "\x00", 0, $psLen) !== $psLen || $db[$psLen] !== "\x01") {
            return false;
        }
        $salt = substr($db, -$sLen);
        $h2 = hash($hash, str_repeat("\x00", 8) . $mHash . $salt, true);

        return hash_equals($h, $h2);
    }

    private static function mgf1(string $seed, int $len, string $hash): string
    {
        $out = '';
        for ($i = 0; strlen($out) < $len; $i++) {
            $out .= hash($hash, $seed . pack('N', $i), true);
        }

        return substr($out, 0, $len);
    }

    private static function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function len(int $n): string
    {
        if ($n < 0x80) {
            return chr($n);
        }
        $b = ltrim(pack('N', $n), "\x00");

        return chr(0x80 | strlen($b)) . $b;
    }

    private static function seq(string $content): string
    {
        return "\x30" . self::len(strlen($content)) . $content;
    }

    private static function bitString(string $content): string
    {
        return "\x03" . self::len(strlen($content) + 1) . "\x00" . $content;
    }

    /** A DER INTEGER from unsigned big-endian bytes. */
    private static function int(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . self::len(strlen($bytes)) . $bytes;
    }
}
