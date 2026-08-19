<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Partner;
use App\Modules\EdgeSync\Application\Services\SyncEngineService;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use App\Modules\HR\Domain\Models\TrainingCourse;
use App\Modules\HR\Domain\Models\TrainingEnrollment;
use App\Modules\HR\Domain\Models\TrainingSession;
use App\Modules\Payroll\Domain\Models\Commission;
use App\Modules\Payroll\Domain\Models\Payment;
use App\Modules\Payroll\Domain\Models\PublicHoliday;
use App\Modules\Payroll\Infrastructure\Services\CommissionService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3811 — races check-then-create résiduelles (6 sites, hors #3726).
 *
 * Chaque site fait un `exists()`/`firstOrCreate()` suivi d'un insert protégé
 * par contrainte unique : une requête concurrente gagnant la course entre les
 * deux provoquait un QueryException 23505 → 500. Le contrat : catch 23505 →
 * réponse 409/422 idempotente (ou retour silencieux côté service), log warning,
 * jamais de 500. Les tests simulent la course avec le hook Eloquent
 * `creating` (motif EmployeeImportRaceTest #3726) : au moment où le modèle
 * s'insère, la requête concurrente (insert brut) gagne la course.
 */
class CheckThenCreateRaceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->employee = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
    }

    public function test_self_enroll_race_returns_422_not_500(): void
    {
        Sanctum::actingAs($this->employee);

        $course = TrainingCourse::create([
            'company_id' => $this->company->id,
            'title' => 'Sécurité au travail',
            'type' => 'internal',
        ]);

        $session = TrainingSession::create([
            'training_course_id' => $course->id,
            'company_id' => $this->company->id,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);

        // Course simulée : au moment où le modèle s'insère, un enroll
        // concurrent (même session, même employé) gagne la course →
        // violation de la contrainte unique (training_session_id, employee_id).
        $employeeId = $this->employee->id;
        TrainingEnrollment::creating(function (TrainingEnrollment $model) use ($session, $employeeId): void {
            if ($model->training_session_id !== $session->id) {
                return;
            }

            DB::table('training_enrollments')->insert([
                'training_session_id' => $session->id,
                'employee_id' => $employeeId,
                'company_id' => $this->company->id,
                'status' => 'enrolled',
            ]);
        });

        $response = $this->postJson("/api/v1/me/trainings/{$session->id}/enroll");

        // Jamais de 500 : 422 idempotent, même message que le check exists().
        $response->assertStatus(422)
            ->assertJsonPath('message', 'Already enrolled in this session.');

        $this->assertSame(1, TrainingEnrollment::where('training_session_id', $session->id)
            ->where('employee_id', $employeeId)
            ->count());
    }

    public function test_public_holiday_store_race_returns_422_not_500(): void
    {
        // Manager principal : le férié est scopé à SA société (company_id NOT
        // NULL) — la contrainte unique standard
        // public_holidays_country_year_date_company_unique s'applique (le
        // férié national company_id NULL nécessiterait l'index partiel PgSQL).
        Sanctum::actingAs($this->employee);

        // Course simulée : un manager concurrent insère le même férié entre
        // assertUnique() et create() → violation de contrainte unique.
        PublicHoliday::creating(function ($model): void {
            if ($model->date !== '2026-07-05' || $model->company_id === null) {
                return;
            }

            DB::table('public_holidays')->insert([
                'country_code' => 'DZ',
                'name' => 'Fête de l\'indépendance (concurrent)',
                'date' => '2026-07-05',
                'year' => 2026,
                'company_id' => $model->company_id,
                'is_recurring' => false,
                'month_day' => null,
                'holiday_type' => 'fixed',
                'created_by' => null,
            ]);
        });

        $response = $this->postJson('/api/v1/public-holidays', [
            'country_code' => 'DZ',
            'name' => 'Fête de l\'indépendance',
            'date' => '2026-07-05',
            'year' => 2026,
            'is_recurring' => false,
            'month_day' => null,
            'holiday_type' => 'fixed',
        ]);

        // Jamais de 500 : 422 (ValidationException) identique à assertUnique().
        $response->assertStatus(422);
        $this->assertStringContainsString('already exists', $response->json('errors.date.0'));

        $this->assertSame(1, \App\Modules\Payroll\Domain\Models\PublicHoliday::query()
            ->where('date', '2026-07-05')
            ->where('company_id', $this->company->id)
            ->count());
    }

    public function test_commission_service_race_is_idempotent(): void
    {
        // partners.user_id references public.users, not tenant employees.
        $partnerUser = User::factory()->create();
        $partner = Partner::create([
            'user_id' => $partnerUser->id,
            'referral_code' => 'QA-'.strtoupper(substr(uniqid(), -6)),
            'application_status' => 'approved',
            'status' => 'active',
            'type' => 'individual',
            'default_commission_rate' => 1000,
            'tax_rate' => 0,
            'payout_threshold' => 0,
        ]);

        $this->company->referrer_partner_id = $partner->id;
        $this->company->save();

        $invoice = DB::table('invoices')->insertGetId([
            'company_id' => $this->company->id,
            'number' => 'INV-RACE-'.uniqid(),
            'amount' => 1000.00,
            'currency' => 'DZD',
            'tax_amount' => 0,
            'total' => 1000.00,
            'status' => 'paid',
            'due_date' => now()->toDateString(),
            'paid_at' => now(),
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice,
            'company_id' => $this->company->id,
            'amount' => 1000.00,
            'currency' => 'DZD',
            'method' => 'card',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // Course simulée : une requête concurrente crée la commission entre
        // l'exists() d'idempotence et le create() → violation de l'index
        // unique commissions_payment_id_unique.
        Commission::creating(function (Commission $model) use ($payment): void {
            if ($model->payment_id !== $payment->id) {
                return;
            }

            DB::table('commissions')->insert([
                'partner_id' => $model->partner_id,
                'company_id' => $model->company_id,
                'payment_id' => $model->payment_id,
                'amount' => $model->amount,
                'currency' => $model->currency,
                'applied_rate' => $model->applied_rate,
                'status' => 'pending',
            ]);
        });

        $service = app(CommissionService::class);
        $result = $service->recordCommissionForPayment($payment);

        // Idempotent : le perdant retourne null (comme si l'exists() l'avait
        // vu), jamais d'exception, jamais de commission dupliquée.
        $this->assertNull($result);
        $this->assertSame(1, Commission::where('payment_id', $payment->id)->count());
    }

    public function test_company_request_user_first_or_create_race_is_resolved(): void
    {
        // Employé « ordinary » sans session user_api → resolveRequestUser passe
        // par User::firstOrCreate (chemin employé).
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'ordinary',
            'status' => 'active',
        ]);
        Sanctum::actingAs($employee);

        $email = $employee->email;

        // Course simulée : un user plateforme concurrent (même email) est créé
        // entre le SELECT de firstOrCreate et l'insert → violation de
        // l'index unique users.email.
        User::creating(function (User $model) use ($email): void {
            if ($model->email !== $email) {
                return;
            }

            DB::table('users')->insert([
                'first_name' => 'Concurrent',
                'last_name' => 'User',
                'email' => $email,
                'provider' => 'email',
                'preferred_language' => 'fr',
                'status' => 'active',
            ]);
        });

        $response = $this->postJson('/api/v1/company-requests', [
            'company_name' => 'Société Concurrente',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $email,
        ]);

        // Jamais de 500 : le gagnant est récupéré, la demande est créée (201),
        // et il n'existe qu'un seul user pour cet email.
        $response->assertCreated();

        $this->assertSame(1, User::where('email', $email)->count());
        $this->assertDatabaseHas('company_requests', ['email' => $email]);
    }

    public function test_sync_engine_attendance_duplicate_external_event_returns_conflict(): void
    {
        $node = EdgeNode::create([
            'name' => 'Edge Race',
            'slug' => 'edge-race-'.uniqid(),
            'status' => 'active',
            'mode' => 'hybrid',
        ]);

        $item = SyncQueue::create([
            'edge_node_id' => $node->id,
            'entity_type' => 'attendance_logs',
            'entity_id' => 'evt-race-'.uniqid(),
            'operation' => 'create',
            'payload' => [
                'employee_id' => $this->employee->id,
                'company_id' => $this->company->id,
                'date' => now()->toDateString(),
                'external_event_id' => 'evt-race',
                'check_in' => now()->toIso8601String(),
            ],
            'status' => 'pending',
        ]);

        // Le doublon existe déjà (sync concurrent passé avant) : l'exists()
        // renvoie conflict — jamais d'insert, jamais d'exception.
        DB::table('attendance_logs')->insert([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => now()->toDateString(),
            'external_event_id' => 'evt-race',
        ]);

        $service = new ExposedSyncEngineService();
        $result = $service->exposeApplyAttendanceLog($item);

        $this->assertTrue($result['conflict']);
        $this->assertSame('Duplicate external_event_id — create skipped.', $result['conflict_note']);
    }
}

/**
 * Expose la méthode protégée applyAttendanceLog pour le test (aucune
 * modification de la classe de production).
 */
class ExposedSyncEngineService extends SyncEngineService
{
    public function exposeApplyAttendanceLog(SyncQueue $item): array
    {
        return $this->applyAttendanceLog($item);
    }
}
