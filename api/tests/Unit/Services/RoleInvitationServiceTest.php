<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\HR\Infrastructure\Services\RoleInvitationService;
use Tests\TestCase;

/**
 * #4180 : les liens de téléchargement des apps ne doivent jamais contenir
 * d'identifiant placeholder (id000000000*). Le lien iOS est piloté par la
 * config `services.mobile_app_links.ios` (env LEOPARDO_IOS_APP_LINKS) et
 * vaut null tant que l'app n'est pas publiée sur l'App Store.
 */
class RoleInvitationServiceTest extends TestCase
{
    public function test_ios_link_is_null_when_not_configured(): void
    {
        config()->set('services.mobile_app_links.ios', []);

        $links = RoleInvitationService::getAppDownloadLink('manager', 'rh');

        $this->assertNull($links['ios']);
        $this->assertSame('https://play.google.com/store/apps/details?id=com.leopardo.rh', $links['android']);
        $this->assertSame('Leopardo RH', $links['name']);
    }

    public function test_ios_link_comes_from_config(): void
    {
        config()->set('services.mobile_app_links.ios', [
            'rh' => 'https://apps.apple.com/app/leopardo-rh/id1234567890',
        ]);

        $links = RoleInvitationService::getAppDownloadLink('manager', 'rh');

        $this->assertSame('https://apps.apple.com/app/leopardo-rh/id1234567890', $links['ios']);
    }

    public function test_default_role_uses_employee_links(): void
    {
        config()->set('services.mobile_app_links.ios', []);

        $links = RoleInvitationService::getAppDownloadLink('employee', 'unknown-role');

        $this->assertNull($links['ios']);
        $this->assertSame('Leopardo Employee', $links['name']);
    }

    public function test_no_placeholder_id_in_any_link(): void
    {
        config()->set('services.mobile_app_links.ios', []);

        foreach (['rh', 'comptable', 'marketing', 'principal', 'employee'] as $role) {
            $links = RoleInvitationService::getAppDownloadLink('manager', $role);
            foreach ($links as $key => $value) {
                if (is_string($value)) {
                    $this->assertStringNotContainsString(
                        'id000000000',
                        $value,
                        "Lien placeholder dans la branche {$role}.{$key}"
                    );
                }
            }
        }
    }
}
