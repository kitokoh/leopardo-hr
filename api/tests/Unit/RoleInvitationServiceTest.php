<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\HR\Infrastructure\Services\RoleInvitationService;
use Tests\TestCase;

/**
 * Issue #4180 : les liens App Store placeholder `id000000000X` ne doivent
 * jamais être renvoyés aux destinataires des e-mails d'invitation de rôle.
 */
class RoleInvitationServiceTest extends TestCase
{
    public function test_ios_links_are_absent_when_no_store_url_configured(): void
    {
        config()->set('mobile.app_store_urls', [
            'principal' => null,
            'rh' => null,
            'comptable' => null,
            'marketing' => null,
            'employee' => null,
        ]);

        foreach (['rh', 'comptable', 'marketing', 'principal', 'employee'] as $role) {
            $links = RoleInvitationService::getAppDownloadLink('manager', $role);

            $this->assertArrayNotHasKey('ios', $links, "role {$role}: ios doit être absent sans URL configurée");
            $this->assertStringNotContainsString('id000000000', json_encode($links), "role {$role}: aucun placeholder App Store");
        }
    }

    public function test_ios_link_comes_from_config_when_configured(): void
    {
        config()->set('mobile.app_store_urls', [
            'principal' => null,
            'rh' => 'https://apps.apple.com/app/leopardo-rh/id1234567890',
            'comptable' => null,
            'marketing' => null,
            'employee' => null,
        ]);

        $links = RoleInvitationService::getAppDownloadLink('manager', 'rh');

        $this->assertSame('https://apps.apple.com/app/leopardo-rh/id1234567890', $links['ios']);
        $this->assertArrayHasKey('android', $links);
        $this->assertSame('Leopardo RH', $links['name']);
    }

    public function test_employee_store_url_is_the_fallback_for_unmapped_roles(): void
    {
        config()->set('mobile.app_store_urls', [
            'principal' => null,
            'rh' => null,
            'comptable' => null,
            'marketing' => null,
            'employee' => 'https://apps.apple.com/app/leopardo-employee/id9876543210',
        ]);

        $links = RoleInvitationService::getAppDownloadLink('manager', 'principal');

        $this->assertSame('https://apps.apple.com/app/leopardo-employee/id9876543210', $links['ios']);
    }
}
