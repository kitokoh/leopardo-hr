<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #4592 — 8 messages de validation ajoutés via $validator->errors()->add()
 * avec des littéraux FR/EN en dur (StoreEmployeeRequest, UpdateEmployeeRequest,
 * StorePayrollRunRequest, PublicHolidayController). Les tenants en/tr/ar
 * recevaient du français (ou de l'anglais) dans les 422. Les messages
 * passent désormais par lang/errors.php ×4 locales (SetLocale/Accept-Language).
 */
class ValidationMessagesLocalizedTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_employee_create_password_or_invitation_localized(): void
    {
        $expected = [
            'fr' => "Le mot de passe ou l'invitation email est requis.",
            'en' => 'A password or an email invitation is required.',
            'tr' => 'Şifre veya e-posta davetiyesi gereklidir.',
            'ar' => 'كلمة المرور أو دعوة البريد الإلكتروني مطلوبة.',
        ];

        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        foreach ($expected as $locale => $message) {
            // SetLocale : la préférence de l'utilisateur authentifié prime sur
            // Accept-Language — on pilote la locale via preferred_language.
            $manager->forceFill(['preferred_language' => $locale])->save();

            $response = $this->withHeader('Accept-Language', $locale)
                ->postJson('/api/v1/employees', [
                    'first_name' => 'Localized',
                    'last_name' => 'Test',
                    'email' => "localized-{$locale}@example.test",
                    'role' => 'employee',
                ]);

            $response->assertStatus(422)
                ->assertJsonPath('errors.password.0', $message);
        }
    }

    public function test_employee_create_manager_role_required_localized(): void
    {
        $expected = [
            'fr' => 'Le type de manager est requis.',
            'en' => 'The manager type is required.',
            'tr' => 'Yönetici türü gereklidir.',
            'ar' => 'نوع المدير مطلوب.',
        ];

        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        foreach ($expected as $locale => $message) {
            $manager->forceFill(['preferred_language' => $locale])->save();

            $response = $this->withHeader('Accept-Language', $locale)
                ->postJson('/api/v1/employees', [
                    'first_name' => 'Localized',
                    'last_name' => 'Manager',
                    'email' => "localized-mgr-{$locale}@example.test",
                    'password' => 'ProvidedPass123!',
                    'role' => 'manager',
                    // manager_role absent → erreur dédiée.
                ]);

            $response->assertStatus(422)
                ->assertJsonPath('errors.manager_role.0', $message);
        }
    }

    public function test_employee_update_role_change_forbidden_localized(): void
    {
        $expected = [
            'fr' => 'Seul le manager principal peut modifier les rôles RH.',
            'en' => 'Only the principal manager can change HR roles.',
            'tr' => 'Yalnızca baş yönetici İK rollerini değiştirebilir.',
            'ar' => 'يمكن للمدير الرئيسي فقط تعديل أدوار الموارد البشرية.',
        ];

        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $rhManager */
        $rhManager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        /** @var Employee $target */
        $target = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($rhManager);

        foreach ($expected as $locale => $message) {
            $rhManager->forceFill(['preferred_language' => $locale])->save();

            $response = $this->withHeader('Accept-Language', $locale)
                ->patchJson("/api/v1/employees/{$target->id}", [
                    'role' => 'manager',
                ]);

            $response->assertStatus(422)
                ->assertJsonPath('errors.role.0', $message);
        }
    }

    public function test_payroll_run_country_mismatch_localized_with_interpolation(): void
    {
        $expected = [
            'fr' => 'Le pays du run doit correspondre au pays légal du tenant (DZ).',
            'en' => "The run country must match the tenant's legal country (DZ).",
            'tr' => 'Çalıştırma ülkesi, kiracının yasal ülkesiyle eşleşmelidir (DZ).',
            'ar' => 'يجب أن تتطابق دولة التشغيل مع الدولة القانونية للمستأجر (DZ).',
        ];

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        foreach ($expected as $locale => $message) {
            $manager->forceFill(['preferred_language' => $locale])->save();

            $response = $this->withHeader('Accept-Language', $locale)
                ->postJson('/api/v1/payroll-runs', [
                    'country_code' => 'MA', // ≠ pays légal du tenant (DZ)
                    'period_start' => '2026-01-01',
                    'period_end' => '2026-01-31',
                ]);

            $response->assertStatus(422)
                ->assertJsonPath('errors.country_code.0', $message);
        }
    }

    public function test_public_holiday_year_mismatch_localized(): void
    {
        $expected = [
            'fr' => "L'année doit correspondre à l'année de la date.",
            'en' => 'The year must match the year of the date.',
            'tr' => 'Yıl, tarihin yılıyla eşleşmelidir.',
            'ar' => 'يجب أن تتطابق السنة مع سنة التاريخ.',
        ];

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($principal);

        foreach ($expected as $locale => $message) {
            $principal->forceFill(['preferred_language' => $locale])->save();

            $response = $this->withHeader('Accept-Language', $locale)
                ->postJson('/api/v1/public-holidays', [
                    'country_code' => 'DZ',
                    'name' => 'Test',
                    'date' => '2026-01-01',
                    'year' => 2025, // ≠ année de la date
                    'is_recurring' => false,
                ]);

            $response->assertStatus(422)
                ->assertJsonPath('errors.year.0', $message);
        }
    }
}
