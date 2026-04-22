<?php

namespace Tests\Feature;

use App\Mail\UserInvitationMail;
use App\Models\Employee;
use App\Models\SuperAdmin;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Couvre le parcours complet d'onboarding API-first :
 *   1. Le super admin cree une entreprise + un manager principal
 *   2. Le manager recoit un email d'invitation avec lien d'activation
 *   3. Le manager active son compte (mot de passe) via le lien
 *   4. Le manager se connecte via l'API et recupere son auth/me + capacites
 *   5. Le manager (principal) cree un RH, qui recoit aussi une invitation
 *   6. Le RH active son compte, cree un employe simple, qui peut se connecter
 *      et consulter son espace /me
 */
class OnboardingE2ETest extends TestCase
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

    public function test_full_onboarding_flow_from_super_admin_to_employee(): void
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

        // 1. Super admin cree la societe + manager principal.
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
            'password_hash' => Hash::make('admin'),
        ]);

        $createResponse = $this
            ->actingAs($superAdmin, 'super_admin_api')
            ->postJson('/api/v1/platform/companies', [
                'name' => 'Acme SARL',
                'sector' => 'Industrie',
                'country' => 'DZ',
                'city' => 'Alger',
                'email' => 'contact@acme.test',
                'plan_id' => 1,
                'manager_first_name' => 'Lina',
                'manager_last_name' => 'Belkacem',
                'manager_email' => 'lina@acme.test',
            ]);
        $createResponse->assertCreated();
        $companyId = $createResponse->json('data.company.id');

        // 2. L'email d'invitation manager est parti.
        $managerActivationUrl = null;
        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$managerActivationUrl): bool {
            if ($mail->employee->email !== 'lina@acme.test') {
                return false;
            }
            $managerActivationUrl = $mail->activationUrl;

            return true;
        });
        $this->assertNotNull($managerActivationUrl);
        $plainToken = basename(parse_url($managerActivationUrl, PHP_URL_PATH) ?? '');
        $this->assertNotEmpty($plainToken);

        // 3. Le manager active son compte via le lien (token plain reconstitue).
        $this->get('/activate/'.$plainToken)->assertOk();
        $this->post('/activate/'.$plainToken, [
            'password' => 'ManagerStrong!123',
            'password_confirmation' => 'ManagerStrong!123',
        ])->assertRedirect('/login');

        DB::statement('SET search_path TO public');
        $this->assertDatabaseHas('user_invitations', [
            'email' => 'lina@acme.test',
        ]);
        $invitation = UserInvitation::query()->where('email', 'lina@acme.test')->firstOrFail();
        $this->assertNotNull($invitation->accepted_at);

        // 4. Login API + /auth/me.
        DB::statement('SET search_path TO shared_tenants,public');
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'lina@acme.test',
            'password' => 'ManagerStrong!123',
            'device_name' => 'tests',
        ]);
        $login->assertOk();
        $managerToken = $login->json('token');
        $this->assertNotEmpty($managerToken);

        $me = $this->withHeader('Authorization', 'Bearer '.$managerToken)
            ->getJson('/api/v1/auth/me');
        $me->assertOk();
        $me->assertJsonPath('data.role', 'manager');
        $me->assertJsonPath('data.manager_role', 'principal');
        $me->assertJsonPath('data.capabilities.can_create_employees', true);
        $me->assertJsonPath('data.capabilities.can_manage_invitations', true);
        $me->assertJsonPath('data.capabilities.is_principal', true);

        // 5. Le manager principal cree un RH (send_invitation=true pour declencher le mail).
        $createRh = $this->withHeader('Authorization', 'Bearer '.$managerToken)
            ->postJson('/api/v1/employees', [
                'first_name' => 'Youcef',
                'last_name' => 'Rh',
                'email' => 'rh@acme.test',
                'role' => 'manager',
                'manager_role' => 'rh',
                'send_invitation' => true,
            ]);
        $createRh->assertCreated();
        $createRh->assertJsonPath('data.manager_role', 'rh');

        // Verifier que le RH a recu une invitation.
        $rhActivationUrl = null;
        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$rhActivationUrl): bool {
            if ($mail->employee->email !== 'rh@acme.test') {
                return false;
            }
            $rhActivationUrl = $mail->activationUrl;

            return true;
        });
        $this->assertNotNull($rhActivationUrl);
        $rhToken = basename(parse_url($rhActivationUrl, PHP_URL_PATH) ?? '');

        // 6. Le RH active son compte et cree un employe simple.
        $this->post('/activate/'.$rhToken, [
            'password' => 'RhStrong!123',
            'password_confirmation' => 'RhStrong!123',
        ])->assertRedirect('/login');

        DB::statement('SET search_path TO shared_tenants,public');
        $rhLogin = $this->postJson('/api/v1/auth/login', [
            'email' => 'rh@acme.test',
            'password' => 'RhStrong!123',
            'device_name' => 'tests',
        ]);
        $rhLogin->assertOk();
        $rhApiToken = $rhLogin->json('token');

        $createEmployee = $this->withHeader('Authorization', 'Bearer '.$rhApiToken)
            ->postJson('/api/v1/employees', [
                'first_name' => 'Sami',
                'last_name' => 'Employe',
                'email' => 'sami@acme.test',
                'role' => 'employee',
                'send_invitation' => true,
            ]);
        $createEmployee->assertCreated();

        // L'employe recoit aussi son invitation + peut activer + se connecter + voir /me.
        $employeeActivationUrl = null;
        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$employeeActivationUrl): bool {
            if ($mail->employee->email !== 'sami@acme.test') {
                return false;
            }
            $employeeActivationUrl = $mail->activationUrl;

            return true;
        });
        $employeeToken = basename(parse_url($employeeActivationUrl, PHP_URL_PATH) ?? '');
        $this->post('/activate/'.$employeeToken, [
            'password' => 'EmpStrong!123',
            'password_confirmation' => 'EmpStrong!123',
        ])->assertRedirect('/login');

        DB::statement('SET search_path TO shared_tenants,public');

        // 7. Employe login Web → redirige sur /me.
        $this->get('/login');
        $csrfToken = session()->token();
        $loginWeb = $this->withSession(['_token' => $csrfToken])->post('/login', [
            '_token' => $csrfToken,
            'email' => 'sami@acme.test',
            'password' => 'EmpStrong!123',
        ]);
        $loginWeb->assertRedirect('/me');
        $this->assertAuthenticated('web');

        $this->get('/me')->assertOk()->assertSee('Sami');

        // 8. Toutes les societes sont bien sur le meme schema partage.
        DB::statement('SET search_path TO public');
        $this->assertSame('shared_tenants', DB::table('companies')->where('id', $companyId)->value('schema_name'));
    }

    public function test_rbac_api_enforcement_between_manager_and_employee(): void
    {
        Mail::fake();
        DB::table('plans')->insert([
            'id' => 1, 'name' => 'Starter', 'price_monthly' => 29, 'price_yearly' => 290,
            'trial_days' => 14, 'is_active' => true,
        ]);

        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
            'password_hash' => Hash::make('admin'),
        ]);

        $this->actingAs($superAdmin, 'super_admin_api')
            ->postJson('/api/v1/platform/companies', [
                'name' => 'Acme',
                'sector' => 'Tech',
                'country' => 'DZ',
                'city' => 'Alger',
                'email' => 'acme@test.dz',
                'plan_id' => 1,
                'manager_first_name' => 'Rita',
                'manager_last_name' => 'Principal',
                'manager_email' => 'rita@acme.test',
            ])->assertCreated();

        DB::statement('SET search_path TO shared_tenants,public');
        $rita = Employee::query()->where('email', 'rita@acme.test')->firstOrFail();
        $rita->password_hash = Hash::make('Rita!123456');
        $rita->email_verified_at = now();
        $rita->invitation_accepted_at = now();
        $rita->save();

        // Principal cree un RH et un employe.
        $this->resetAuth();
        Sanctum::actingAs($rita, ['*']);
        $this->postJson('/api/v1/employees', [
            'first_name' => 'Yacine', 'last_name' => 'Rh',
            'email' => 'yacine@acme.test', 'password' => 'Yacine!1234',
            'role' => 'manager', 'manager_role' => 'rh',
        ])->assertCreated();

        $this->resetAuth();
        Sanctum::actingAs($rita, ['*']);
        $this->postJson('/api/v1/employees', [
            'first_name' => 'Sami', 'last_name' => 'Employe',
            'email' => 'sami@acme.test', 'password' => 'Sami!1234',
            'role' => 'employee',
        ])->assertCreated();

        DB::statement('SET search_path TO shared_tenants,public');
        $yacine = Employee::query()->where('email', 'yacine@acme.test')->firstOrFail();
        $sami = Employee::query()->where('email', 'sami@acme.test')->firstOrFail();

        // auth/me pour chaque role -> capacites correctes.
        $this->resetAuth();
        Sanctum::actingAs($rita, ['*']);
        $this->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('data.manager_role', 'principal')
            ->assertJsonPath('data.capabilities.can_create_employees', true)
            ->assertJsonPath('data.capabilities.can_manage_invitations', true);

        $this->resetAuth();
        Sanctum::actingAs($yacine, ['*']);
        $this->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('data.manager_role', 'rh')
            ->assertJsonPath('data.capabilities.can_create_employees', true);

        $this->resetAuth();
        Sanctum::actingAs($sami, ['*']);
        $this->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.role', 'employee')
            ->assertJsonPath('data.capabilities.can_create_employees', false)
            ->assertJsonPath('data.capabilities.can_manage_invitations', false);

        // Employe -> ne peut pas creer d'autre employe / lister invitations.
        $this->resetAuth();
        Sanctum::actingAs($sami, ['*']);
        $this->postJson('/api/v1/employees', [
            'first_name' => 'X', 'last_name' => 'X',
            'email' => 'x@acme.test', 'password' => 'SomePass!1',
            'role' => 'employee',
        ])->assertStatus(403);

        $this->resetAuth();
        Sanctum::actingAs($sami, ['*']);
        $this->getJson('/api/v1/invitations')->assertStatus(403);

        // RH peut lister les invitations.
        $this->resetAuth();
        Sanctum::actingAs($yacine, ['*']);
        $list = $this->getJson('/api/v1/invitations');
        $list->assertOk();
        $this->assertGreaterThanOrEqual(1, count($list->json('data')));

        DB::statement('SET search_path TO public');
        $ritaInvId = UserInvitation::query()->where('email', 'rita@acme.test')->value('id');
        // L'invitation de Rita a ete acceptee via ORM (invitation_accepted_at),
        // donc le resend peut aboutir 200 (renvoi) ou 410 (deja acceptee) selon la logique.
        $this->resetAuth();
        Sanctum::actingAs($yacine, ['*']);
        $resend = $this->postJson('/api/v1/invitations/'.$ritaInvId.'/resend');
        $this->assertContains($resend->status(), [200, 410]);
    }

    private function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    public function test_super_admin_can_create_multiple_shared_companies(): void
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

        foreach (['Alpha', 'Beta', 'Gamma'] as $i => $name) {
            $response = $this->actingAs($superAdmin, 'super_admin_api')
                ->postJson('/api/v1/platform/companies', [
                    'name' => $name,
                    'sector' => 'Services',
                    'country' => 'DZ',
                    'city' => 'Alger',
                    'email' => strtolower($name).'@test.dz',
                    'plan_id' => 1,
                    'manager_first_name' => 'Manager',
                    'manager_last_name' => (string) $i,
                    'manager_email' => 'manager-'.strtolower($name).'@test.dz',
                ]);
            $response->assertCreated();
        }

        DB::statement('SET search_path TO public');
        $this->assertSame(3, DB::table('companies')->where('schema_name', 'shared_tenants')->count());

        DB::statement('SET search_path TO shared_tenants,public');
        $this->assertSame(3, Employee::query()->where('role', 'manager')->where('manager_role', 'principal')->count());
    }
}
