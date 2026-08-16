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
     */
    public static function getAppDownloadLink(string $role, string $managerRole, string $platform = 'both'): array
    {
        $link = match($managerRole) {
            'rh'        => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.rh',
                'ios_key' => 'rh',
                'name'    => 'Leopardo RH',
                'deep_link_scheme' => 'leopardo-rh',
            ],
            'comptable' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.comptable',
                'ios_key' => 'comptable',
                'name'    => 'Leopardo Comptable',
                'deep_link_scheme' => 'leopardo-comptable',
            ],
            'marketing' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.marketing',
                'ios_key' => 'marketing',
                'name'    => 'Leopardo Marketing',
                'deep_link_scheme' => 'leopardo-marketing',
            ],
            'principal' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.admin',
                'ios_key' => 'principal',
                'name'    => 'Leopardo Admin',
                'deep_link_scheme' => 'leopardo-admin',
            ],
            default => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.employee',
                'ios_key' => 'employee',
                'name'    => 'Leopardo Employee',
                'deep_link_scheme' => 'leopardo-employee',
            ],
        };

        // #4180 : plus jamais de placeholder App Store (id000000000N) envoyé
        // aux utilisateurs. Le lien iOS n'existe que si un identifiant réel
        // est configuré (config/mobile.php, vars IOS_APP_STORE_ID_*).
        $iosId = config('mobile.ios_app_store_ids.'.$link['ios_key']);
        $link['ios'] = $iosId
            ? 'https://apps.apple.com/app/'.$link['name'].'/id'.$iosId
            : null;
        unset($link['ios_key']);

        return $link;
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

