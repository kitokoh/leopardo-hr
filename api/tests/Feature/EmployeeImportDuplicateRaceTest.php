<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3726 — import CSV employés : race check-then-create.
 *
 * Avant : toute violation d'unicité (import concurrent insérant le même email
 * entre le `exists()` et l'`Employee::create`) empoisonnait la transaction
 * globale → rollback + 500. Après : la ligne est skippée (SQLSTATE 23505
 * rattrapé ligne par ligne), réponse 201/422, jamais 500.
 */
class EmployeeImportDuplicateRaceTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeManager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    public function test_concurrent_duplicate_email_is_skipped_not_500(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        Sanctum::actingAs($this->makeManager($company));

        $racedEmail = 'race-'.uniqid().'@example.com';

        // Simule la race de façon déterministe : juste avant l'INSERT Eloquent,
        // un "import concurrent" insère le même email via le query builder
        // (pas d'événements → pas de récursion). Le exists() du contrôleur a
        // déjà répondu false à ce stade → l'INSERT réel viole l'unicité.
        $fired = false;
        Employee::creating(function () use (&$fired, $racedEmail, $company): void {
            if ($fired) {
                return;
            }
            $fired = true;
            DB::table('employees')->insert([
                'company_id' => $company->id,
                'first_name' => 'Concurrent',
                'last_name' => 'Import',
                'email' => $racedEmail,
                'password_hash' => Hash::make('password123'),
                'role' => 'employee',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $csv = "first_name,last_name,email\n"
            ."Raced,User,{$racedEmail}\n"
            .'Clean,User,clean-'.uniqid()."@example.com\n";

        $response = $this->post('/api/v1/employees/import', [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        // Jamais 500 : la ligne racée est skippée, la ligne saine est importée.
        $response->assertStatus(201);
        $response->assertJsonPath('data.imported', 1);
        $response->assertJsonPath('data.skipped', 1);

        $errors = $response->json('data.errors');
        $this->assertIsArray($errors);
        $this->assertStringContainsString(
            'existe deja',
            (string) ($errors[0]['error'] ?? '')
        );
    }

    public function test_existing_email_is_skipped_and_valid_rows_import(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        Sanctum::actingAs($this->makeManager($company));

        $existingEmail = 'exists-'.uniqid().'@example.com';
        Employee::factory()->create([
            'company_id' => $company->id,
            'email' => $existingEmail,
        ]);

        $csv = "first_name,last_name,email\n"
            ."Dupe,User,{$existingEmail}\n"
            .'New,User,new-'.uniqid()."@example.com\n";

        $response = $this->post('/api/v1/employees/import', [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.imported', 1);
        $response->assertJsonPath('data.skipped', 1);
    }
}
