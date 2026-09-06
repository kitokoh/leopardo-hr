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
 *
 * #6942 : le mapping (rôle → package Android) doit rester aligné sur le
 * catalogue réel des apps du monorepo (build.gradle.kts des apps sous
 * front/mobile_apps) et sur `MobileExperienceService::appContextFor` (T120).
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

    public function test_employee_without_manager_role_gets_employee_app(): void
    {
        config()->set('services.mobile_app_links.ios', []);

        $links = RoleInvitationService::getAppDownloadLink('employee', 'employee');

        $this->assertNull($links['ios']);
        $this->assertSame('com.leopardo.employee', $this->androidPackage($links));
        $this->assertSame('Leopardo Employee', $links['name']);
    }

    /**
     * #6942 : tout manager sans app dédiée distribuée reçoit l'app Manager
     * (parité MobileExperienceService — plus jamais de package fantôme
     * com.leopardo.admin / com.leopardo.comptable, ni l'app Employee pour un
     * manager dept/superviseur).
     */
    public function test_managers_without_dedicated_app_get_manager_app(): void
    {
        config()->set('services.mobile_app_links.ios', []);

        foreach (['principal', 'dept', 'superviseur', 'comptable', 'marketing'] as $managerRole) {
            $links = RoleInvitationService::getAppDownloadLink('manager', $managerRole);

            $this->assertSame(
                'com.leopardo.manager',
                $this->androidPackage($links),
                "manager_role {$managerRole} doit pointer vers l'app Manager (com.leopardo.manager)"
            );
            $this->assertSame('Leopardo Manager', $links['name']);
        }
    }

    /**
     * #6942 : verrou (rôle → package) — chaque package Android renvoyé doit
     * exister dans le catalogue réel du monorepo.
     */
    public function test_every_returned_android_package_exists_in_real_app_catalog(): void
    {
        $existingPackages = [
            'com.leopardo.employee',
            'com.leopardo.manager',
            'com.leopardo.rh',
        ];

        foreach (['employee', 'rh', 'principal', 'dept', 'superviseur', 'comptable', 'marketing'] as $managerRole) {
            $links = RoleInvitationService::getAppDownloadLink('manager', $managerRole);

            $this->assertContains(
                $this->androidPackage($links),
                $existingPackages,
                "manager_role {$managerRole} renvoie un package absent du catalogue des apps"
            );
        }
    }

    public function test_no_placeholder_id_in_any_link(): void
    {
        config()->set('services.mobile_app_links.ios', []);

        foreach (['rh', 'comptable', 'marketing', 'principal', 'employee', 'dept', 'superviseur'] as $role) {
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

    /**
     * @param  array<string, mixed>  $links
     */
    private function androidPackage(array $links): string
    {
        /** @var string $android */
        $android = $links['android'];

        $parts = explode('id=', $android);

        return $parts[1];
    }
}
