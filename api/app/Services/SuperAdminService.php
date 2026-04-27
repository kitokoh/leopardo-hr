<?php

namespace App\Services;

use App\Models\SuperAdmin;

class SuperAdminService
{
    /**
     * Genere un secret 2FA pour un super-admin.
     */
    public function generateSecret(): string
    {
        if ($google2fa = $this->google2fa()) {
            return $google2fa->generateSecretKey();
        }

        return $this->base32Encode(random_bytes(20));
    }

    /**
     * Verifie si un code 2FA est valide.
     */
    public function verifyCode(SuperAdmin $superAdmin, string $code): bool
    {
        if (!$superAdmin->two_fa_secret) {
            return true;
        }

        if ($google2fa = $this->google2fa()) {
            return $google2fa->verifyKey($superAdmin->two_fa_secret, $code);
        }

        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($normalizedCode) !== 6) {
            return false;
        }

        $timestamp = time();

        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($this->totpAt($superAdmin->two_fa_secret, $timestamp + ($offset * 30)), $normalizedCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Genere l'URL du QR Code pour l'enrolement.
     */
    public function getQrCodeUrl(SuperAdmin $superAdmin, string $secret): string
    {
        if ($google2fa = $this->google2fa()) {
            return $google2fa->getQRCodeUrl(
                'Leopardo RH Platform',
                $superAdmin->email,
                $secret
            );
        }

        $issuer = rawurlencode('Leopardo RH Platform');
        $label = rawurlencode('Leopardo RH Platform:'.$superAdmin->email);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    private function google2fa(): ?object
    {
        if (!class_exists(\PragmaRX\Google2FA\Google2FA::class)) {
            return null;
        }

        return new \PragmaRX\Google2FA\Google2FA();
    }

    private function totpAt(string $secret, int $timestamp): string
    {
        $counter = intdiv($timestamp, 30);
        $binaryCounter = pack('N2', 0, $counter);
        $key = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $chunk = substr($hash, $offset, 4);
        $value = unpack('N', $chunk)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }

    private function base32Decode(string $value): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $normalized = strtoupper(rtrim($value, '='));
        $bits = '';

        foreach (str_split($normalized) as $char) {
            if (!array_key_exists($char, $alphabet)) {
                continue;
            }

            $bits .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                continue;
            }

            $decoded .= chr(bindec($chunk));
        }

        return $decoded;
    }
}
