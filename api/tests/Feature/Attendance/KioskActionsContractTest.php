<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Application\Actions\BuildKioskAnnouncements;
use App\Modules\Attendance\Application\Actions\RegisterKiosk;
use App\Modules\Attendance\Application\Actions\RevokeKiosk;
use App\Modules\Attendance\Application\Actions\RotateKioskToken;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use App\Modules\Attendance\Infrastructure\Services\KioskProvisioningGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7962 — contrat des cas d'usage extraits de `KioskController`.
 *
 * Le but de cette suite n'est pas de re-tester les routes (couvertes par
 * `KioskDeviceLifecycleTest`) mais de figer les règles que le contrôleur
 * appliquait inline et qui, une fois déplacées dans une Action, pourraient
 * silencieusement changer de comportement :
 *
 * 1. `card` (vocabulaire ZKTeco) devient `badge` (domaine) à l'écriture ;
 * 2. la révocation est idempotente — `revoked_at` n'est pas réécrit ;
 * 3. la rotation invalide l'ancien token immédiatement ;
 * 4. l'absence de table d'annonces rend une liste vide, pas une 500.
 */
final class KioskActionsContractTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_register_normalizes_card_alias_to_badge(): void
    {
        [$manager, $company] = $this->seedManagerAndCompany();

        /** @var RegisterKiosk $action */
        $action = $this->app->make(RegisterKiosk::class);

        $created = $action->execute($company, [
            'name' => 'Entree ZKTeco',
            'biometric_mode' => 'fingerprint',
            'punch_methods' => ['card', 'fingerprint', 'card'],
        ]);

        // `card` -> `badge`, dédupliqué, ordre d'apparition conservé.
        $this->assertSame(['badge', 'fingerprint'], $created['kiosk']->resolvedPunchMethods());
    }

    public function test_register_stores_secrets_hashed_and_returns_plaintext_once(): void
    {
        [, $company] = $this->seedManagerAndCompany();

        /** @var RegisterKiosk $action */
        $action = $this->app->make(RegisterKiosk::class);

        $created = $action->execute($company, ['name' => 'Entree hash']);

        $stored = AttendanceKiosk::query()->whereKey($created['kiosk']->id)->firstOrFail();

        // Le device_code est stocké sous forme de hash déterministe : la valeur
        // en clair ne doit PAS apparaître en base.
        $this->assertNotSame($created['device_code'], $stored->device_code);
        $this->assertSame(AttendanceKiosk::hashDeviceCode($created['device_code']), $stored->device_code);

        // Le sync_token est vérifiable mais non réversible.
        $this->assertTrue(Hash::check($created['sync_token'], (string) $stored->sync_token_hash));
    }

    public function test_revoke_is_idempotent_and_keeps_first_revocation_timestamp(): void
    {
        [$manager] = $this->seedManagerAndCompany();
        $kiosk = $this->makeKiosk();

        /** @var RevokeKiosk $action */
        $action = $this->app->make(RevokeKiosk::class);

        $action->execute($kiosk, $manager);
        $firstRevokedAt = $kiosk->fresh()?->revoked_at?->toDateTimeString();

        // Rejouer la révocation ne doit pas déplacer la date de décision.
        $action->execute($kiosk->fresh() ?? $kiosk, $manager);

        $this->assertSame($firstRevokedAt, $kiosk->fresh()?->revoked_at?->toDateTimeString());
        $this->assertSame('revoked', $kiosk->fresh()?->status);
    }

    public function test_rotate_token_invalidates_previous_secret_immediately(): void
    {
        [$manager] = $this->seedManagerAndCompany();
        $kiosk = $this->makeKiosk();

        $oldToken = 'ancien-token-en-clair';
        $kiosk->forceFill(['sync_token_hash' => Hash::make($oldToken)])->save();

        /** @var RotateKioskToken $action */
        $action = $this->app->make(RotateKioskToken::class);

        $newToken = $action->execute($kiosk, $manager);

        $this->assertNotSame($oldToken, $newToken);
        $stored = $kiosk->fresh();
        $this->assertFalse(Hash::check($oldToken, (string) $stored?->sync_token_hash));
        $this->assertTrue(Hash::check($newToken, (string) $stored?->sync_token_hash));
    }

    public function test_announcements_return_empty_list_when_table_is_absent(): void
    {
        [, $company] = $this->seedManagerAndCompany();

        Schema::dropIfExists('kiosk_announcements');

        /** @var BuildKioskAnnouncements $action */
        $action = $this->app->make(BuildKioskAnnouncements::class);

        // Une table absente est un état normal (module d'affichage non activé),
        // pas une panne : la surface kiosque doit rester servie.
        $this->assertSame([], $action->execute($company));
    }

    public function test_provisioning_guard_accepts_a_kiosk_written_in_the_public_context(): void
    {
        [, $company] = $this->seedManagerAndCompany();

        /** @var RegisterKiosk $register */
        $register = $this->app->make(RegisterKiosk::class);
        $created = $register->execute($company, ['name' => 'Entree garde']);

        /** @var KioskProvisioningGuard $guard */
        $guard = $this->app->make(KioskProvisioningGuard::class);

        // Le kiosque vient d'être écrit dans le schéma courant : la garde doit
        // le résoudre et ne rien supprimer.
        $guard->assertResolvable($created['kiosk']);

        $this->assertNotNull(AttendanceKiosk::query()->whereKey($created['kiosk']->id)->first());
    }

    /**
     * @return array{0: Employee, 1: Company}
     */
    private function seedManagerAndCompany(): array
    {
        $company = Company::query()->create([
            'name' => 'Company actions',
            'slug' => 'company-kiosk-actions-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => Str::random(6).'@kiosk-actions.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $manager = new Employee([
            'first_name' => 'Manager',
            'last_name' => 'Kiosk',
            'email' => Str::random(6).'-manager@kiosk-actions.test',
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        return [$manager, $company];
    }

    private function makeKiosk(): AttendanceKiosk
    {
        $company = Company::query()->firstOrFail();

        return AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Entree '.Str::random(4),
            'biometric_mode' => 'fingerprint',
            'device_code' => AttendanceKiosk::hashDeviceCode(strtoupper(Str::random(10))),
            'sync_token_hash' => Hash::make(Str::random(48)),
            'status' => 'active',
        ]);
    }
}
