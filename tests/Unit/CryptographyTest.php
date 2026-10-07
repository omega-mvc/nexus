<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CryptographyTest extends TestCase
{
    public function testPasswordHashRoundTrip(): void
    {
        $hash = Cryptography::hashPassword('s3cret');
        $this->assertTrue(Cryptography::checkPasswordHash('s3cret', $hash));
        $this->assertFalse(Cryptography::checkPasswordHash('wrong', $hash));
    }

    public function testLegacyPasswordHashPrefixStillVerifies(): void
    {
        $hash = Cryptography::hashPassword('s3cret');
        $legacy = 'fCryptography::' . substr($hash, strlen('Cryptography::'));
        $this->assertTrue(Cryptography::checkPasswordHash('s3cret', $legacy));
    }

    public function testSymmetricRoundTrip(): void
    {
        $key = 'secret-key-123';
        $ct = Cryptography::symmetricKeyEncrypt('top secret', $key);
        $this->assertStringNotContainsString('top secret', $ct);
        $this->assertSame('top secret', Cryptography::symmetricKeyDecrypt($ct, $key));
    }

    public function testLegacySymmetricPrefixStillDecrypts(): void
    {
        $key = 'secret-key-123';
        $ct = Cryptography::symmetricKeyEncrypt('top secret', $key);
        $legacy = str_replace(
            'Cryptography::symmetric2#',
            'fCryptography::symmetric2#',
            $ct
        );
        $this->assertSame('top secret', Cryptography::symmetricKeyDecrypt($legacy, $key));
    }

    public function testMcryptMarkerIsRejected(): void
    {
        $this->expectException(EnvironmentException::class);
        Cryptography::symmetricKeyDecrypt('Cryptography::symmetric#zz', 'secret-key-123');
    }

    public function testWeakSymmetricKeyIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        Cryptography::symmetricKeyEncrypt('data', 'short');
    }

    public function testPublicKeyEncryptDecryptAndSignVerify(): void
    {
        $resource = openssl_pkey_new(
            array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA)
        );
        $this->assertIsObject($resource);
        openssl_pkey_export($resource, $private_pem, 'pass123');
        $public_pem = openssl_pkey_get_details($resource)['key'];

        $private_file = tempnam(sys_get_temp_dir(), 'pvk');
        $public_file  = tempnam(sys_get_temp_dir(), 'pub');
        file_put_contents($private_file, $private_pem);
        file_put_contents($public_file, $public_pem);

        try {
            $ct = Cryptography::publicKeyEncrypt('sealed msg', $public_file);
            $this->assertSame(
                'sealed msg',
                Cryptography::publicKeyDecrypt($ct, $private_file, 'pass123')
            );

            $signature = Cryptography::publicKeySign('data', $private_file, 'pass123');
            $this->assertTrue(Cryptography::publicKeyVerify('data', $signature, $public_file));
            $this->assertFalse(Cryptography::publicKeyVerify('tampered', $signature, $public_file));
        } finally {
            unlink($private_file);
            unlink($public_file);
        }
    }

    public function testHashHmac(): void
    {
        $this->assertSame(
            hash_hmac('sha256', 'data', 'key'),
            Cryptography::hashHMAC('sha256', 'data', 'key')
        );
    }
}
