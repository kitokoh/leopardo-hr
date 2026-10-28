<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Application\Actions\ProvisionKiosk;
use App\Modules\Attendance\Infrastructure\Services\KioskProvisioningWriter;
use App\Modules\Attendance\Domain\Models\AttendanceKiosk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7962 — l'extraction du provisioning kiosque du controleur vers
 * `ProvisionKiosk` ne doit changer **aucun** comportement.
 *
 * Le controleur porte des regles non evidentes qu'une extraction peut
 * silencieusement laisser derriere elle :
 *
 *  1. **normalisation BIO-006** : l'interface envoie `card` (vocabulaire
 *     ZKTeco), le domaine stocke `badge`. Si l'action oublie la
 *     normalisation, la matrice de methodes contient une valeur inconnue et
 *     le pointage par badge est refuse — panne totale, sans erreur de
 *     provisioning.
 *  2. **garde fail-closed (#6678)** : ne rendre les credentials QUE si la
 *     ligne est resolvable par les routes publiques kiosque. Une ligne
 *     orpheline doit etre supprimee et l'appel doit echouer bruyamment.
 *
 * Ces tests exercent l'action **directement** (pas via HTTP) : c'est le
 * contrat de l'action qui doit tenir, sinon l'extraction n'a rien apporte.
 */
final class ProvisionKioskActionTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_it_normalizes_the_legacy_card_method_to_badge(): void
    {
        [$manager, $company] = $this->seedScenario();

        $result = $this->app->make(ProvisionKiosk::class)->execute($company, [
            'name' => 'Entree atelier',
            'biometric_mode' => 'fingerprint',
            // `card` est le vocabulaire ZKTeco envoye par les bornes.
            'punch_methods' => ['card', 'pin'],
        ]);

        // Le contrat de l'action garantit deja `string` (docblock) : on
        // verifie la forme, pas le type — un assertIsString ici serait
        // tautologique et PHPStan strict le signale.
        $this->assertNotSame('', $result['device_code']);

        DB::statement('SET search_path TO shared_tenants,public');
        /** @var AttendanceKiosk $kiosk */
        $kiosk = AttendanceKiosk::query()->findOrFail($result['kiosk']->id);

        // La valeur stockee est celle du DOMAINE, pas celle de l'interface :
        // c'est ce qui permet au pointage par badge de reconnaitre la methode.
        $this->assertSame(['badge', 'pin'], $kiosk->punch_methods);
        $this->assertNotContains('card', $kiosk->punch_methods ?? []);
    }

    public function test_it_drops_non_string_methods_instead_of_storing_garbage(): void
    {
        [$manager, $company] = $this->seedScenario();

        $result = $this->app->make(ProvisionKiosk::class)->execute($company, [
            'name' => 'Entree mixte',
            'biometric_mode' => 'fingerprint',
            // Charge volontairement malformee : le writer attend une
            // `list<string>`, la normalisation doit filtrer, pas planter.
            'punch_methods' => ['face', 42, null, 'card'],
        ]);

        DB::statement('SET search_path TO shared_tenants,public');
        /** @var AttendanceKiosk $kiosk */
        $kiosk = AttendanceKiosk::query()->findOrFail($result['kiosk']->id);

        $this->assertSame(['face', 'badge'], $kiosk->punch_methods);
    }

    public function test_it_leaves_absent_method_matrix_untouched(): void
    {
        [$manager, $company] = $this->seedScenario();

        // Aucun `punch_methods` : l'action ne doit PAS inventer de matrice
        // par defaut — l'absence a un sens metier (toutes methodes du mode).
        $result = $this->app->make(ProvisionKiosk::class)->execute($company, [
            'name' => 'Entree sans matrice',
            'biometric_mode' => 'fingerprint',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');
        /** @var AttendanceKiosk $kiosk */
        $kiosk = AttendanceKiosk::query()->findOrFail($result['kiosk']->id);

        $this->assertNull($kiosk->punch_methods);
    }

    public function test_it_fails_closed_when_the_kiosk_is_not_resolvable_in_public_context(): void
    {
        [$manager, $company] = $this->seedScenario();

        // Le writer est substitue par un double qui rend une ligne fantome :
        // c'est la garde fail-closed (#6678) qui doit reagir, pas le writer.
        $ghost = new AttendanceKiosk;
        $ghost->id = 987654;
        $ghost->company_id = $company->id;
        $ghost->device_code = 'ghost-'.Str::random(8);

        $this->app->instance(KioskProvisioningWriter::class, new class($ghost) extends KioskProvisioningWriter
        {
            public function __construct(private readonly AttendanceKiosk $ghost) {}

            /**
             * @param  array<string, mixed>  $attributes
             * @return array{kiosk: AttendanceKiosk, device_code: string, sync_token: string}
             */
            public function create(Company $company, array $attributes): array
            {
                return [
                    'kiosk' => $this->ghost,
                    'device_code' => $this->ghost->device_code,
                    'sync_token' => 'ghost-token',
                ];
            }
        });

        $this->expectException(\Throwable::class);

        $this->app->make(ProvisionKiosk::class)->execute($company, [
            'name' => 'Entree fantome',
            'biometric_mode' => 'fingerprint',
        ]);
    }

    /**
     * @return array{0: Employee, 1: Company}
     */
    private function seedScenario(): array
    {
        $company = Company::query()->create([
            'name' => 'Company kiosk action',
            'slug' => 'company-kiosk-action-'.Str::random(6),
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'kiosk-action@provision.test',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        // `password_hash` et `company_id` ne sont pas fillable (protection
        // contre l'escalade de privileges via mass assignment) : on les pose
        // explicitement, comme les autres tests kiosque du projet.
        $manager = new Employee([
            'first_name' => 'Karim',
            'last_name' => 'Action',
            'email' => 'manager@kiosk-action.test',
        ]);
        $manager->forceFill(['password_hash' => \Illuminate\Support\Facades\Hash::make('password')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        return [$manager, $company];
    }
}
