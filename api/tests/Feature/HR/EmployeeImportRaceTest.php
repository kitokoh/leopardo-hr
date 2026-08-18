<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3726 (T003) : import CSV employés — course check-then-create.
 *
 * Deux imports concurrents (ou un doublon arrivé entre le `exists()` et le
 * `Employee::create()`) violent l'index unique global `employees(email)` →
 * QueryException SQLSTATE 23505 → le contrôleur doit skippper la ligne et
 * répondre 201/422 (jamais 500, jamais de rollback global).
 */
class EmployeeImportRaceTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(): Company
    {
        return Company::factory()->create(['country' => 'DZ']);
    }

    private function makeManager(Company $company): Employee
    {
        $sensitiveEmployee1 = new Employee([
            'first_name' => 'Manager',
            'last_name' => 'Import',
            'email' => 'manager-'.uniqid().'@test.local',
        ]);
        $sensitiveEmployee1->forceFill(['password_hash' => Hash::make('password123')])->save();
        $sensitiveEmployee1->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();
        return $sensitiveEmployee1;
    }

    public function test_duplicate_email_within_same_file_is_skipped_per_line(): void
    {
        Storage::fake('local');

        $company = $this->makeCompany();
        Sanctum::actingAs($this->makeManager($company));

        $email = 'dup-'.uniqid().'@example.com';
        $csv = "first_name,last_name,email\n"
            ."Ali,Ben,{$email}\n"
            ."Sara,Khan,{$email}\n";

        $response = $this->post('/api/v1/employees/import', [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, $response->json('data.imported'));
        $this->assertSame(1, $response->json('data.skipped'));

        $errors = $response->json('data.errors');
        $this->assertCount(1, $errors);
        $this->assertSame(3, $errors[0]['line']);
        $this->assertStringContainsString('existe deja', $errors[0]['error']);

        $this->assertDatabaseHas('employees', ['email' => $email]);
    }

    public function test_concurrent_unique_violation_is_skipped_not_500(): void
    {
        Storage::fake('local');

        $company = $this->makeCompany();
        Sanctum::actingAs($this->makeManager($company));

        $email = 'race-'.uniqid().'@example.com';
        $csv = "first_name,last_name,email\n"
            ."Ali,Ben,{$email}\n";

        // Connexion dédiée « race » : simule un VRAI import concurrent dont la
        // transaction est indépendante (autocommit). Sans cela, l'insert simulé
        // dans le hook `creating` vivrait dans le SAVEPOINT de l'import et serait
        // annulé par le ROLLBACK TO SAVEPOINT du conflit (#4947 : PostgreSQL
        // aborte la transaction à la première erreur SQL, même catchée).
        config()->set('database.connections.race', array_merge(
            config('database.connections.pgsql'),
            ['database' => config('database.connections.pgsql.database')],
        ));
        DB::purge('race');

        // Simule la course : au moment où le contrôleur insère l'employé, un
        // import concurrent a déjà inséré le même email (index unique global).
        Employee::creating(function (Employee $model) use ($company, $email): void {
            if ($model->email !== $email) {
                return;
            }

            DB::connection('race')->table('employees')->insert([
                'company_id' => $company->id,
                'email' => $email,
                'first_name' => 'Concurrent',
                'last_name' => 'Insert',
                'password_hash' => Hash::make('x'),
                'status' => 'active',
            ]);
        });

        $response = $this->post('/api/v1/employees/import', [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        // Pas de 500 : la ligne est signalée (422 car aucune ligne importée).
        $response->assertStatus(422);

        $body = $response->json();
        $this->assertSame(0, $body['data']['imported']);
        $this->assertSame(1, $body['data']['skipped']);
        $this->assertSame(2, $body['data']['errors'][0]['line']);
        $this->assertStringContainsString('existe deja', $body['data']['errors'][0]['error']);

        // Le concurrent (seul vrai enregistrement) est bien présent.
        $this->assertDatabaseHas('employees', ['email' => $email, 'first_name' => 'Concurrent']);
    }

    public function test_valid_lines_are_imported_even_when_another_line_conflicts(): void
    {
        Storage::fake('local');

        $company = $this->makeCompany();
        Sanctum::actingAs($this->makeManager($company));

        $conflictEmail = 'existing-'.uniqid().'@example.com';
        $sensitiveEmployee0 = new Employee([
            'first_name' => 'Employe',
            'last_name' => 'Import',
            'email' => $conflictEmail,
        ]);
        $sensitiveEmployee0->forceFill(['password_hash' => Hash::make('x')])->save();
        $sensitiveEmployee0->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        $csv = "first_name,last_name,email\n"
            ."Old,Dup,{$conflictEmail}\n"
            .'New,One,new-'.uniqid()."@example.com\n"
            .'New,Two,new2-'.uniqid()."@example.com\n";

        $response = $this->post('/api/v1/employees/import', [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertStatus(201);
        $this->assertSame(2, $response->json('data.imported'));
        $this->assertSame(1, $response->json('data.skipped'));
        $this->assertSame(2, $response->json('data.errors.0.line'));
    }
}
