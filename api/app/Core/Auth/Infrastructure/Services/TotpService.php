<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use PragmaRX\Google2FA\Google2FA;

/**
 * #5436 — Génération/vérification TOTP partagée.
 *
 * Reprend la logique de `SuperAdminService` (compatibilité totale) et la rend
 * réutilisable pour les comptes entreprise. Le refactor de SuperAdminService
 * vers ce service est laissé à un nettoyage ultérieur (aucun changement de
 * comportement ici).
 */
final class TotpService
{
    /**
     * Génère un secret TOTP (base32, 20 octets).
     */
    public function generateSecret(): string
    {
        if ($this->google2fa() !== null) {
            return $this->google2fa()->generateSecretKey();
        }

        return $this->base32Encode(random_bytes(20));
    }

    /**
     * Vérifie un code TOTP (fenêtre ±1 période de 30 s, fail-closed : un
     * secret absent ou un code malformé renvoient false).
     */
    public function verifyCode(string $secret, string $code): bool
    {
        if ($secret === '') {
            return false;
        }

        if ($this->google2fa() !== null) {
            return $this->google2fa()->verifyKey($secret, $code);
        }

        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($normalizedCode) !== 6) {
            return false;
        }

        $timestamp = time();

        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($this->totpAt($secret, $timestamp + ($offset * 30)), $normalizedCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * URL otpauth:// pour le QR code d'enrôlement.
     */
    public function qrCodeUrl(string $account, string $secret, string $issuer = 'Leopardo'): string
    {
        if ($this->google2fa() !== null) {
            return $this->google2fa()->getQRCodeUrl($issuer, $account, $secret);
        }

        $encodedIssuer = rawurlencode($issuer);
        $label = rawurlencode($issuer.':'.$account);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    private function google2fa(): ?Google2FA
    {
        if (! class_exists(Google2FA::class)) {
            return null;
        }

        return new Google2FA;
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
            if (! array_key_exists($char, $alphabet)) {
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
