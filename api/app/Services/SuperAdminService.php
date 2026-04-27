<?php

namespace App\Services;

use App\Models\SuperAdmin;
use PragmaRX\Google2FA\Google2FA;

class SuperAdminService
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Genere un secret 2FA pour un super-admin.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Verifie si un code 2FA est valide.
     */
    public function verifyCode(SuperAdmin $superAdmin, string $code): bool
    {
        if (!$superAdmin->two_fa_secret) {
            return true;
        }

        return $this->google2fa->verifyKey($superAdmin->two_fa_secret, $code);
    }

    /**
     * Genere l'URL du QR Code pour l'enrolement.
     */
    public function getQrCodeUrl(SuperAdmin $superAdmin, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            'Leopardo RH Platform',
            $superAdmin->email,
            $secret
        );
    }
}
