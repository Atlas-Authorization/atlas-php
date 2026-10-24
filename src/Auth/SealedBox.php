<?php

declare(strict_types=1);

namespace Atlas\Auth;

use Atlas\Exception\ConfigException;

/**
 * Sealed delivery of secrets (libsodium `crypto_box_seal`, needs ext-sodium). Generate a key pair, send
 * `secretRecipient` (`x25519:<base64 public key>`) with a key-creating call, and Atlas answers with `sealed_*` fields only
 * you can open: the secret never crosses the wire in the clear.
 *
 *     $pair = SealedBox::generateKeyPair();
 *     $created = $atlas->secretKeys->create(['name' => 'ci', 'secret_recipient' => $pair['secretRecipient']]);
 *     $secret = SealedBox::open($created['sealed_secret'], $pair['publicKey'], $pair['secretKey']);
 */
final class SealedBox
{
    /**
     * @return array{publicKey:string,secretKey:string,secretRecipient:string} raw 32-byte keys and the `x25519:` recipient string
     */
    public static function generateKeyPair(): array
    {
        self::requireSodium();
        $pair = sodium_crypto_box_keypair();
        $public = sodium_crypto_box_publickey($pair);

        return [
            'publicKey' => $public,
            'secretKey' => sodium_crypto_box_secretkey($pair),
            'secretRecipient' => 'x25519:' . base64_encode($public),
        ];
    }

    /** Open a `sealed_*` field (base64). Throws when it was not sealed to this key pair. */
    public static function open(string $sealedBase64, string $publicKey, string $secretKey): string
    {
        self::requireSodium();
        $sealed = base64_decode($sealedBase64, true);
        if ($sealed === false) {
            throw new \InvalidArgumentException('The sealed value is not valid base64.');
        }
        $plain = sodium_crypto_box_seal_open($sealed, sodium_crypto_box_keypair_from_secretkey_and_publickey($secretKey, $publicKey));
        if ($plain === false) {
            throw new \InvalidArgumentException('Could not open the sealed value with this key pair.');
        }

        return $plain;
    }

    /** Seal `$plain` to a recipient (`x25519:<base64>` or a raw 32-byte public key); for tests and mirrors. */
    public static function seal(string $plain, string $recipient): string
    {
        self::requireSodium();
        $public = str_starts_with($recipient, 'x25519:') ? base64_decode(substr($recipient, 7), true) : $recipient;
        if ($public === false || strlen($public) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new \InvalidArgumentException('The recipient is not a 32-byte x25519 public key.');
        }

        return base64_encode(sodium_crypto_box_seal($plain, $public));
    }

    private static function requireSodium(): void
    {
        if (!function_exists('sodium_crypto_box_seal')) {
            throw new ConfigException('Sealed delivery needs ext-sodium.');
        }
    }
}
