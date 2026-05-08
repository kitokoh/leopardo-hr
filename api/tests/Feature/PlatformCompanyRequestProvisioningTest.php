<?php

namespace Tests\Feature;

use App\Mail\UserInvitationMail;
use App\Models\CompanyRequest;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformCompanyRequestProvisioningTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->setUpCompanyRequestTables();
    }

    protected function tearDown(): void
    {
        $this->tearDownCompanyRequestTables();
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_approving_company_request_provisions_company_and_manager_invitation(): void
    {
        Mail::fake();

        DB::table('plans')->insert([
            'id' => 1,
            'name' => 'Starter',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
            'password_hash' => Hash::make('admin'),
        ]);

        $user = User::query()->create([
            'first_name' => 'Nadia',
            'last_name' => 'Bensaid',
            'email' => 'nadia@example.com',
            'phone' => '+213555000111',
            'password_hash' => Hash::make('secret'),
        ]);

        $companyRequest = CompanyRequest::query()->create([
            'user_id' => $user->id,
            'company_name' => 'Terrain Plus',
            'sector' => 'BTP',
            'country' => 'DZ',
            'city' => 'Alger',
            'manager_name' => 'Nadia Bensaid',
            'manager_phone' => '+213555000111',
            'email' => 'contact@terrain-plus.dz',
            'phone' => '+213555000222',
            'description' => 'Besoin de controler les pointages chantier.',
            'status' => 'pending',
        ]);

        $response = $this
            ->actingAs($superAdmin, 'super_admin_api')
            ->patchJson("/api/v1/platform/company-requests/{$companyRequest->id}", [
                'status' => 'approved',
                'admin_notes' => 'Client pilote prioritaire.',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'approved');
        $this->assertNotNull($response->json('data.approved_company_id'));

        $this->assertDatabaseHas('company_requests', [
            'id' => $companyRequest->id,
            'status' => 'approved',
            'admin_notes' => 'Client pilote prioritaire.',
        ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Terrain Plus',
            'email' => 'contact@terrain-plus.dz',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $this->assertDatabaseHas('employees', [
            'email' => 'nadia@example.com',
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        DB::statement('SET search_path TO public');

        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail): bool {
            return $mail->employee->email === 'nadia@example.com'
                && $mail->employee->role === 'manager'
                && str_contains($mail->activationUrl, '/activate/');
        });
    }

    private function setUpCompanyRequestTables(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');

            return;
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('password_hash')->nullable();
                $table->string('provider')->default('email');
                $table->string('preferred_language', 2)->default('fr');
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_requests')) {
            Schema::create('company_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable();
                $table->string('company_name');
                $table->string('sector')->nullable();
                $table->string('country')->nullable();
                $table->string('city')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('pending');
                $table->uuid('approved_company_id')->nullable();
                $table->text('admin_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    private function tearDownCompanyRequestTables(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');

            return;
        }

        Schema::dropIfExists('company_requests');
        Schema::dropIfExists('users');
    }
}
