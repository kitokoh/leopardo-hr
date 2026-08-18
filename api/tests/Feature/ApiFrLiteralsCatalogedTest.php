<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\App;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #4690 (audit 360° 2026-08-16) — plus aucun littéral FR codé en dur dans les
 * 4 chemins exposés : les messages passent par le catalogue errors.* et
 * suivent la locale du request (Accept-Language).
 */
class ApiFrLiteralsCatalogedTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_attendance_correction_checkout_before_checkin_is_localized(): void
    {
        // Locale résolue par SetLocale : pour un utilisateur authentifié,
        // c'est la langue de l'entreprise qui gagne (pas le header).
        /** @var Company $company */
        $company = Company::factory()->create(['language' => 'en']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/attendance/corrections', [
                'date' => '2026-05-27',
                'requested_check_in' => '2026-05-27 17:00:00',
                'requested_check_out' => '2026-05-27 08:00:00',
                'reason' => 'Test',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.requested_check_out.0', __('errors.ATTENDANCE_CHECKOUT_AFTER_CHECKIN', [], 'en'));
    }

    public function test_islamic_calendar_platform_only_is_localized(): void
    {
        // Locale résolue par SetLocale : langue de l'entreprise (en).
        /** @var Company $company */
        $company = Company::factory()->create(['language' => 'en']);

        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);

        // Un Employee authentifié sur la guard super_admin_api passe l'auth
        // mais échoue l'assertPlatformAdmin → 403 localisé.
        Sanctum::actingAs($principal, ['*'], 'super_admin_api');

        $this->putJson('/api/v1/admin/islamic-calendar/eid_al_fitr/2026', [
            'gregorian_date' => '2026-03-20',
            'duration_days' => 1,
        ])
            ->assertStatus(403)
            // #4689 (PR séparée) ajoute localizer le localized_message générique ;
            // ici on vérifie que le message porté par abort() est localisé.
            ->assertJsonPath('message', __('errors.ISLAMIC_CALENDAR_PLATFORM_ONLY', [], 'en'));
    }

    public function test_company_schema_mode_locked_is_localized(): void
    {
        // Pas de requête HTTP ici : on positionne la locale applicative
        // (équivalent SetLocale) pour vérifier la résolution au catalogue.
        App::setLocale('en');

        try {
            Company::query()->create([
                'name' => 'Schema Co',
                'slug' => 'schema-co',
                'schema_name' => 'schema_co',
                'tenancy_type' => 'schema',
                'status' => 'active',
            ]);
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame(__('errors.COMPANY_SCHEMA_MODE_LOCKED', [], 'en'), $e->getMessage());

            return;
        }

        $this->fail('Company schema-mode creation should have aborted.');
    }

    public function test_company_request_approve_without_plan_is_localized(): void
    {
        /** @var CompanyRequest $companyRequest */
        $companyRequest = CompanyRequest::create([
            'email' => 'founder@noplan.dz',
            'company_name' => 'No Plan Co',
            'status' => 'pending',
            'sector' => 'RH',
            'country' => 'DZ',
            'city' => 'Alger',
        ]);

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $superAdmin->forceFill(['password_hash' => bcrypt('secret123')])->save();

        // Table plans vide en base de test → provisionCompanyFromRequest
        // aborte avec COMPANY_REQUEST_NO_ACTIVE_PLAN.
        $this->actingAs($superAdmin, 'super_admin_api')
            ->withHeader('Accept-Language', 'en')
            ->patchJson('/api/v1/platform/company-requests/'.$companyRequest->id, [
                'status' => 'approved',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('errors.COMPANY_REQUEST_NO_ACTIVE_PLAN', [], 'en'));
    }
}
