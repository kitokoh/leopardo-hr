<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;

class RoleInvitationService
{
    /**
     * Returns the deep link URL for downloading the app based on manager_role.
     * Links use universal links / app store links with a fallback.
     *
     * Issue #4180 : les liens iOS ne sont inclus que si une URL réelle est
     * configurée (config/mobile.php + LEOPARDO_IOS_*_URL) — les placeholders
     * `id000000000X` ne doivent jamais atteindre les destinataires.
     */
    public static function getAppDownloadLink(string $role, string $managerRole, string $platform = 'both'): array
    {
        $links = match($managerRole) {
            'rh'        => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.rh',
                'name'    => 'Leopardo RH',
                'deep_link_scheme' => 'leopardo-rh',
            ],
            'comptable' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.comptable',
                'name'    => 'Leopardo Comptable',
                'deep_link_scheme' => 'leopardo-comptable',
            ],
            'marketing' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.marketing',
                'name'    => 'Leopardo Marketing',
                'deep_link_scheme' => 'leopardo-marketing',
            ],
            'principal' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.admin',
                'name'    => 'Leopardo Admin',
                'deep_link_scheme' => 'leopardo-admin',
            ],
            default => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.employee',
                'name'    => 'Leopardo Employee',
                'deep_link_scheme' => 'leopardo-employee',
            ],
        };

        // Lien App Store réel (config/env) — absent tant que l'app n'est pas
        // publiée : le bouton iOS est alors omis (template + API), jamais un
        // lien placeholder.
        $iosUrl = config('mobile.app_store_urls.'.$managerRole)
            ?? config('mobile.app_store_urls.employee');

        if (is_string($iosUrl) && $iosUrl !== '') {
            $links['ios'] = $iosUrl;
        }

        return $links;
    }

    /**
     * Returns the label for the manager_role, translated according to the
     * currently active application locale (see App::setLocale() callers).
     */
    public static function getRoleLabel(string $managerRole): string
    {
        $key = match ($managerRole) {
            'principal' => 'role_label_principal',
            'rh' => 'role_label_rh',
            'comptable' => 'role_label_comptable',
            'marketing' => 'role_label_marketing',
            'dept' => 'role_label_dept',
            default => 'role_label_default',
        };

        return __("emails.{$key}");
    }
}

