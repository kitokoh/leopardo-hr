<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;

class RoleInvitationService
{
    /**
     * Returns the deep link URL for downloading the app based on manager_role.
     * Links use universal links / app store links with a fallback.
     */
    public static function getAppDownloadLink(string $role, string $managerRole, string $platform = 'both'): array
    {
        return match($managerRole) {
            'rh'        => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.rh',
                'ios'     => 'https://apps.apple.com/app/leopardo-rh/id0000000002',
                'name'    => 'Leopardo RH',
                'deep_link_scheme' => 'leopardo-rh',
            ],
            'comptable' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.comptable',
                'ios'     => 'https://apps.apple.com/app/leopardo-comptable/id0000000003',
                'name'    => 'Leopardo Comptable',
                'deep_link_scheme' => 'leopardo-comptable',
            ],
            'marketing' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.marketing',
                'ios'     => 'https://apps.apple.com/app/leopardo-marketing/id0000000004',
                'name'    => 'Leopardo Marketing',
                'deep_link_scheme' => 'leopardo-marketing',
            ],
            'principal' => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.admin',
                'ios'     => 'https://apps.apple.com/app/leopardo-admin/id0000000001',
                'name'    => 'Leopardo Admin',
                'deep_link_scheme' => 'leopardo-admin',
            ],
            default => [
                'android' => 'https://play.google.com/store/apps/details?id=com.leopardo.employee',
                'ios'     => 'https://apps.apple.com/app/leopardo-employee/id0000000000',
                'name'    => 'Leopardo Employee',
                'deep_link_scheme' => 'leopardo-employee',
            ],
        };
    }

    /**
     * Returns the label for the manager_role in French.
     */
    public static function getRoleLabel(string $managerRole): string
    {
        return match($managerRole) {
            'principal'  => 'Administrateur de l\'entreprise',
            'rh'         => 'Responsable RH',
            'comptable'  => 'Responsable Comptable',
            'marketing'  => 'Responsable Marketing',
            'dept'       => 'Chef de département',
            default      => 'Manager',
        };
    }
}
