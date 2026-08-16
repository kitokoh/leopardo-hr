<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\HR\Infrastructure\Services\RoleInvitationService;
use Tests\TestCase;

/**
 * #4180 — le lien iOS d'invitation de rôle ne doit JAMAIS contenir
 * d'identifiant App Store placeholder (id000000000N) : sans identifiant réel
 * configuré, le lien iOS est omis (null) plutôt que mort.
 */
class RoleInvitationServiceTest extends TestCase
{
    public function test_ios_link_is_omitted_when_no_real_app_store_id_is_configured(): void
    {
        config()->set('mobile.ios_app_store_ids', [
            'rh' => null,
            'comptable' => null,
            'marketing' => null,
            'principal' => null,
            'employee' => null,
        ]);

        foreach (['rh', 'comptable', 'marketing', 'principal', 'employee'] as $role) {
            $links = RoleInvitationService::getAppDownloadLink('manager', $role);

            $this->assertArrayHasKey('android', $links, 'Le lien Android doit toujours exister.');
            $this->assertNull($links['ios'], "Le lien iOS doit être null pour {$role} sans identifiant configuré.");
            $this->assertStringNotContainsString(
                'id000000000',
                json_encode($links, JSON_THROW_ON_ERROR),
                "Aucun placeholder App Store ne doit fuiter pour {$role}.",
            );
        }
    }

    public function test_ios_link_uses_the_configured_app_store_id(): void
    {
        config()->set('mobile.ios_app_store_ids', [
            'rh' => '1234567890',
            'comptable' => null,
            'marketing' => null,
            'principal' => null,
            'employee' => null,
        ]);

        $rh = RoleInvitationService::getAppDownloadLink('manager', 'rh');
        $this->assertSame('https://apps.apple.com/app/Leopardo RH/id1234567890', $rh['ios']);

        $employee = RoleInvitationService::getAppDownloadLink('manager', 'employee');
        $this->assertNull($employee['ios']);
    }

    public function test_android_links_are_always_real_package_names(): void
    {
        $employee = RoleInvitationService::getAppDownloadLink('manager', 'employee');

        $this->assertSame(
            'https://play.google.com/store/apps/details?id=com.leopardo.employee',
            $employee['android'],
        );
        $this->assertStringNotContainsString('0000000', $employee['android']);
    }
}
