<?php

declare(strict_types=1);

namespace Atlas\Mirror;

/** CIDR allowlist matching for IPv4 and IPv6 (`10.0.0.0/8`, `2001:db8::/32`, a bare address = /32 or /128). */
final class Cidr
{
    /** @param list<string> $cidrs */
    public static function contains(string $ip, array $cidrs): bool
    {
        $addr = @inet_pton(trim($ip));
        if ($addr === false) {
            return false;
        }
        foreach ($cidrs as $entry) {
            [$base, $bits] = array_pad(explode('/', trim($entry), 2), 2, null);
            $net = @inet_pton((string) $base);
            if ($net === false || strlen($net) !== strlen($addr)) {
                continue;
            }
            $max = strlen($net) * 8;
            $bits = $bits === null ? $max : (ctype_digit($bits) ? (int) $bits : -1);
            if ($bits < 0 || $bits > $max) {
                continue;
            }
            $full = intdiv($bits, 8);
            $rest = $bits % 8;
            if (substr($addr, 0, $full) !== substr($net, 0, $full)) {
                continue;
            }
            if ($rest === 0 || ((ord($addr[$full]) ^ ord($net[$full])) & (0xFF << (8 - $rest)) & 0xFF) === 0) {
                return true;
            }
        }

        return false;
    }
}
