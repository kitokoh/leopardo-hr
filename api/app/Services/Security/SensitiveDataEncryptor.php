<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Crypt;

class SensitiveDataEncryptor
{
    private const ENCRYPTED_PREFIX = 'enc:';

    public function encrypt(string $value): string
    {
        if ($this->isEncrypted($value)) {
            return $value;
        }

        return self::ENCRYPTED_PREFIX.Crypt::encryptString($value);
    }

    public function decrypt(string $value): string
    {
        if (! $this->isEncrypted($value)) {
            return $value;
        }

        $payload = substr($value, strlen(self::ENCRYPTED_PREFIX));

        return Crypt::decryptString($payload);
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::ENCRYPTED_PREFIX);
    }

    public function encryptArray(array $data, array $sensitiveFields): array
    {
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = $this->encrypt($data[$field]);
            }
        }

        return $data;
    }

    public function decryptArray(array $data, array $sensitiveFields): array
    {
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = $this->decrypt($data[$field]);
            }
        }

        return $data;
    }
}
