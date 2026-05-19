<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Security\SensitiveDataEncryptor;
use Tests\TestCase;

class SensitiveDataEncryptorTest extends TestCase
{
    private SensitiveDataEncryptor $encryptor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encryptor = new SensitiveDataEncryptor;
    }

    public function test_encrypt_and_decrypt_round_trip(): void
    {
        $original = 'FR7630006000011234567890189';
        $encrypted = $this->encryptor->encrypt($original);
        $decrypted = $this->encryptor->decrypt($encrypted);

        $this->assertNotSame($original, $encrypted);
        $this->assertSame($original, $decrypted);
    }

    public function test_encrypted_value_has_prefix(): void
    {
        $encrypted = $this->encryptor->encrypt('sensitive-data');

        $this->assertTrue($this->encryptor->isEncrypted($encrypted));
        $this->assertStringStartsWith('enc:', $encrypted);
    }

    public function test_double_encrypt_is_idempotent(): void
    {
        $original = 'my-iban';
        $encrypted = $this->encryptor->encrypt($original);
        $doubleEncrypted = $this->encryptor->encrypt($encrypted);

        $this->assertSame($encrypted, $doubleEncrypted);
    }

    public function test_decrypt_plain_text_returns_as_is(): void
    {
        $plain = 'not-encrypted';
        $result = $this->encryptor->decrypt($plain);

        $this->assertSame($plain, $result);
    }

    public function test_encrypt_array_encrypts_sensitive_fields(): void
    {
        $data = [
            'name' => 'John',
            'iban' => 'FR7630006000011234567890189',
            'ssn' => '1234567890',
        ];

        $result = $this->encryptor->encryptArray($data, ['iban', 'ssn']);

        $this->assertSame('John', $result['name']);
        $this->assertTrue($this->encryptor->isEncrypted($result['iban']));
        $this->assertTrue($this->encryptor->isEncrypted($result['ssn']));
    }

    public function test_decrypt_array_decrypts_sensitive_fields(): void
    {
        $data = [
            'name' => 'John',
            'iban' => $this->encryptor->encrypt('FR76300'),
            'ssn' => $this->encryptor->encrypt('123456'),
        ];

        $result = $this->encryptor->decryptArray($data, ['iban', 'ssn']);

        $this->assertSame('John', $result['name']);
        $this->assertSame('FR76300', $result['iban']);
        $this->assertSame('123456', $result['ssn']);
    }

    public function test_encrypt_array_skips_missing_fields(): void
    {
        $data = ['name' => 'John'];
        $result = $this->encryptor->encryptArray($data, ['iban', 'ssn']);

        $this->assertSame(['name' => 'John'], $result);
    }
}
