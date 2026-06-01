<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class CompanyBrandingControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_manager_updates_company_branding_with_logo(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $response = $this->actingAs($manager, 'sanctum')
            ->patch('/api/v1/company/branding', [
                'display_name' => 'Leopardo Demo RH',
                'primary_color' => '#123ABC',
                'accent_color' => '#10b981',
                'brand_mode' => 'dark',
                'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.branding.display_name', 'Leopardo Demo RH')
            ->assertJsonPath('data.branding.primary_color', '#123ABC')
            ->assertJsonPath('data.branding.accent_color', '#10B981')
            ->assertJsonPath('data.branding.brand_mode', 'dark');

        $logoPath = $response->json('data.branding.logo_path');
        $this->assertIsString($logoPath);
        Storage::disk('public')->assertExists($logoPath);

        $this->assertSame('Leopardo Demo RH', Company::query()->findOrFail($company->id)->metadata['branding']['display_name']);
    }

    public function test_employee_can_read_but_cannot_update_company_branding(): void
    {
        $company = Company::factory()->create([
            'metadata' => [
                'branding' => [
                    'display_name' => 'Client Terrain',
                    'primary_color' => '#0F766E',
                ],
            ],
        ]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/company/branding')
            ->assertOk()
            ->assertJsonPath('data.branding.display_name', 'Client Terrain')
            ->assertJsonPath('data.branding.primary_color', '#0F766E');

        $this->actingAs($employee, 'sanctum')
            ->patchJson('/api/v1/company/branding', ['display_name' => 'Non autorise'])
            ->assertForbidden();
    }

    public function test_branding_rejects_invalid_colors(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);

        $this->actingAs($manager, 'sanctum')
            ->patchJson('/api/v1/company/branding', [
                'primary_color' => 'red',
                'accent_color' => '#12345Z',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_color', 'accent_color']);
    }
}
