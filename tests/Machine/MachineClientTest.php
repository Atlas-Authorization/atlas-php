<?php

declare(strict_types=1);

namespace Atlas\Tests\Machine;

use Atlas\Machine\FileMachineStore;
use Atlas\Machine\MachineClient;
use Atlas\Machine\MachineClientException;
use Atlas\Machine\MemoryMachineStore;
use Atlas\Tests\Support\MockClient;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

final class MachineClientTest extends TestCase
{
    private int $t = 1_000_000_000;

    private function client(MockClient $mock, MemoryMachineStore $store, string|bool|null $dpop = null): MachineClient
    {
        $f = new HttpFactory();

        return new MachineClient('https://acme-fapi.example.test', $store, $mock, $f, $f, dpopKey: $dpop, now: fn (): int => $this->t);
    }

    public function testEnrolThenMintProvesPossessionOfTheEnrolledKey(): void
    {
        $mock = new MockClient();
        $store = new MemoryMachineStore();
        $machine = $this->client($mock, $store);
        $mock->push(200, ['id' => 'mch_1', 'status' => 'active', 'approval_required' => false, 'enrolled_at' => 5]);
        $res = $machine->enroll('mtok_x', 'worker', ['region' => 'eu']);
        self::assertSame(['machineId' => 'mch_1', 'status' => 'active', 'approvalRequired' => false], $res);
        $enrol = json_decode((string) $mock->lastRequest()->getBody(), true);
        self::assertSame('mtok_x', $enrol['enrolment_token']);
        self::assertStringStartsWith('-----BEGIN PUBLIC KEY-----', $enrol['public_key_pem']);

        $mock->push(200, ['nonce' => 'nonce-123']);
        $mock->push(200, ['token' => 'jwt', 'token_type' => 'Bearer', 'expires_in' => 600, 'audience' => 'https://svc']);
        $token = $machine->getToken(['audience' => 'https://svc', 'scopes' => ['b', 'a']]);
        self::assertSame('jwt', $token['token']);
        self::assertSame($this->t + 600_000, $token['expiresAt']);

        $minted = json_decode((string) $mock->lastRequest()->getBody(), true);
        $public = base64_decode((string) preg_replace('/-----[A-Z ]+-----|\s+/', '', $enrol['public_key_pem']), true);
        $sig = base64_decode(strtr($minted['signature'], '-_', '+/'), true);
        self::assertTrue(sodium_crypto_sign_verify_detached($sig, 'nonce-123', substr($public, -32)), 'the signature verifies under the enrolled public key');

        // cached until the refresh skew, then minted again
        self::assertSame($token, $machine->getToken(['audience' => 'https://svc', 'scopes' => ['a', 'b']]));
        self::assertSame(3, $mock->requestCount());
        $this->t += 560_000;
        $mock->push(200, ['nonce' => 'n2']);
        $mock->push(200, ['token' => 'jwt2', 'expires_in' => 600]);
        self::assertSame('jwt2', $machine->getToken(['audience' => 'https://svc', 'scopes' => ['a', 'b']])['token']);
    }

    public function testMintingBeforeEnrolmentFails(): void
    {
        $this->expectException(MachineClientException::class);
        $this->client(new MockClient(), new MemoryMachineStore())->getToken();
    }

    public function testAnApiErrorCarriesTheCode(): void
    {
        $mock = new MockClient();
        $mock->push(403, ['errors' => [['code' => 'ENROLMENT_TOKEN_USED', 'message' => 'used']]]);
        try {
            $this->client($mock, new MemoryMachineStore())->enroll('x', 'n');
            self::fail('expected an exception');
        } catch (MachineClientException $e) {
            self::assertSame(403, $e->status);
            self::assertSame('ENROLMENT_TOKEN_USED', $e->errorCode);
        }
    }

    public function testDpopProofsBindTheTokenAndMethod(): void
    {
        $machine = $this->client(new MockClient(), new MemoryMachineStore(), true);
        $proof = $machine->dpopProof('post', 'https://api.example.com/v1/x?q=1', 'tok', 'srv-nonce');
        [$h, $p, $s] = array_map(static fn (string $v): string => (string) base64_decode(strtr($v, '-_', '+/'), true), explode('.', $proof));
        $header = json_decode($h, true);
        $payload = json_decode($p, true);
        self::assertSame('dpop+jwt', $header['typ']);
        self::assertSame('POST', $payload['htm']);
        self::assertSame('https://api.example.com/v1/x', $payload['htu']);
        self::assertSame('srv-nonce', $payload['nonce']);
        self::assertSame(rtrim(strtr(base64_encode(hash('sha256', 'tok', true)), '+/', '-_'), '='), $payload['ath']);
        $pub = base64_decode(strtr($header['jwk']['x'], '-_', '+/'), true);
        $input = explode('.', $proof);
        self::assertTrue(sodium_crypto_sign_verify_detached($s, $input[0] . '.' . $input[1], $pub));
        self::assertSame(43, strlen((string) $machine->dpopThumbprint()));
    }

    public function testFileStoreWritesPrivateFiles(): void
    {
        $dir = sys_get_temp_dir() . '/atlas-machine-' . bin2hex(random_bytes(4));
        $store = new FileMachineStore($dir . '/m.json');
        self::assertNull($store->load());
        $store->save(['machine_id' => 'mch_1']);
        self::assertSame('mch_1', $store->load()['machine_id']);
        self::assertSame('0600', substr(sprintf('%o', fileperms($dir . '/m.json')), -4));
        $store->clear();
        self::assertNull($store->load());
        @rmdir($dir);
    }
}
