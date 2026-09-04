<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\Schedule;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #6546 (RGPD) — extra_data contient des clés sensibles (national_id,
 * tax_identifier, blood_group) : elles ne doivent pas fuir dans la liste
 * /employees et ne sont exposées au détail que pour les rôles autorisés
 * (mêmes règles que les salaires, #5262).
 */
final class EmployeeExtraDataMaskingTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_employee_list_does_not_leak_sensitive_extra_data(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Equipe QA',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 60,
            'work_days' => [1, 2, 3, 4, 5],
            'is_default' => true,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/employees', [
            'first_name' => 'Karim',
            'last_name' => 'Confidentiel',
            'email' => 'karim.confidentiel@example.test',
            'password' => 'password123',
            'role' => 'employee',
            'schedule_id' => $schedule->id,
            'extra_data' => [
                'job_title' => 'Technicien terrain',
                'work_location' => 'Site Est',
                'national_id' => 'NID-12345678',
                'tax_identifier' => 'TAX-998877',
                'blood_group' => 'O+',
            ],
        ])->assertCreated();

        // La liste ne doit exposer aucune clé sensible (et extra_data n'est
        // plus chargé en masse).
        $list = $this->getJson('/api/v1/employees?per_page=50')->assertOk();

        $payload = $list->json('data');
        $target = collect($payload)->firstWhere('email', 'karim.confidentiel@example.test');
        $this->assertNotNull($target, 'employé créé présent dans la liste');

        $extra = $target['extra_data'] ?? null;
        $this->assertIsArray($extra);
        $this->assertArrayNotHasKey('national_id', (array) $extra);
        $this->assertArrayNotHasKey('tax_identifier', (array) $extra);
        $this->assertArrayNotHasKey('blood_group', (array) $extra);
    }

    public function test_employee_show_exposes_sensitive_extra_data_to_authorized_roles(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'extra_data' => [
                'job_title' => 'Comptable',
                'national_id' => 'NID-87654321',
                'tax_identifier' => 'TAX-112233',
                'blood_group' => 'A-',
            ],
        ]);

        // Manager RH (hasManagerRole principal/rh/comptable) → accès complet.
        Sanctum::actingAs($manager);
        $detail = $this->getJson("/api/v1/employees/{$employee->id}")->assertOk();

        $extra = (array) $detail->json('data.extra_data');
        $this->assertSame('Comptable', $extra['job_title'] ?? null);
        $this->assertSame('NID-87654321', $extra['national_id'] ?? null);
        $this->assertSame('TAX-112233', $extra['tax_identifier'] ?? null);
        $this->assertSame('A-', $extra['blood_group'] ?? null);
    }

    public function test_employee_show_masks_sensitive_extra_data_for_regular_employee_viewer(): void
    {
        $company = Company::factory()->create();
        $viewer = Employee::factory()->create(['company_id' => $company->id]);
        $target = Employee::factory()->create([
            'company_id' => $company->id,
            'extra_data' => [
                'job_title' => 'Chauffeur',
                'national_id' => 'NID-55554444',
                'blood_group' => 'B+',
            ],
        ]);

        // Employé simple → le détail d'un collègue est masqué (clés RGPD).
        Sanctum::actingAs($viewer);
        $detail = $this->getJson("/api/v1/employees/{$target->id}");

        // Selon la policy, un employé simple peut ne pas voir le détail d'un
        // collègue (403) — dans ce cas le masquage est trivial. S'il y voit
        // accès, les clés sensibles doivent être absentes.
        if ($detail->status() === 200) {
            $extra = (array) $detail->json('data.extra_data');
            $this->assertSame('Chauffeur', $extra['job_title'] ?? null);
            $this->assertArrayNotHasKey('national_id', $extra);
            $this->assertArrayNotHasKey('blood_group', $extra);
        } else {
            $detail->assertStatus(403);
        }
    }
}
