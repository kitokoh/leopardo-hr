<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

class RoleInvitationService
{
    /**
     * Returns the deep link URL for downloading the app based on manager_role.
     * Links use universal links / app store links with a fallback.
     */
    public static function getAppDownloadLink(string $role, string $managerRole, string $platform = 'both'): array
    {
        // #4180 : le lien iOS provient de la config (env LEOPARDO_IOS_APP_LINKS) ;
        // null tant que l'app n'est pas publiée sur l'App Store. Ne jamais
        // réintroduire d'identifiant placeholder id000000000*.
        /** @var array<string, string|null> $iosLinks */
        $iosLinks = config('services.mobile_app_links.ios', []);

        return match ($managerRole) {
            'rh' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.rh',
                'ios' => $iosLinks['rh'] ?? null,
                'name' => 'Leopardo RH',
                'deep_link_scheme' => 'leopardo-rh',
            ],
            'comptable' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.comptable',
                'ios' => $iosLinks['comptable'] ?? null,
                'name' => 'Leopardo Comptable',
                'deep_link_scheme' => 'leopardo-comptable',
            ],
            'marketing' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.marketing',
                'ios' => $iosLinks['marketing'] ?? null,
                'name' => 'Leopardo Marketing',
                'deep_link_scheme' => 'leopardo-marketing',
            ],
            'principal' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.admin',
                'ios' => $iosLinks['principal'] ?? null,
                'name' => 'Leopardo Admin',
                'deep_link_scheme' => 'leopardo-admin',
            ],
            default => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.employee',
                'ios' => $iosLinks['employee'] ?? null,
                'name' => 'Leopardo Employee',
                'deep_link_scheme' => 'leopardo-employee',
            ],
        };
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
