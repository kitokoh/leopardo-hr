<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Application\Services\TimetableService;
use App\Modules\EduManager\Domain\Exceptions\TimetableConflictException;
use App\Modules\EduManager\Domain\Models\EduTimetableSlot;
use App\Modules\EduManager\Domain\Policies\EduTimetableSlotPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5822 (EDU-006) — emplois du temps : créneaux et détection de
 * conflits.
 *
 * Couvre :
 *   - création d'un créneau par un gestionnaire du tenant (principal) ;
 *   - conflit CLASSE détecté (même classe, même jour, intervalles
 *     [start,end) qui se chevauchent, même si l'enseignant diffère) ;
 *   - conflit ENSEIGNANT détecté (même enseignant, même jour, chevauchement,
 *     même si la classe diffère) ;
 *   - créneaux adjacents autorisés (pas de faux positif demi-ouvert) ;
 *   - cross-tenant refusé (policy view/update/delete bornées tenant, pas de
 *     faux conflit entre tenants, calendrier invisible hors tenant) ;
 *   - calendrier paginé, ordonné (day_of_week, start_time) ;
 *   - « jour courant » résolu dans le FUSEAU du tenant (timezone tenant).
 *
 * Les tables parentes edu_academic_years / edu_classes / edu_subjects /
 * edu_teachers sont livrées par les issues parallèles EDU-003/004/005
 * (#5819/#5820/#5821) : si elles sont absentes (checkout partiel), des
 * fixtures minimales sont créées ici (garde schemaTableExists).
 */
class EduTimetableTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $manager;

    private int $classA;

    private int $classA2;

    private int $classB;

    private int $subjectId;

    private int $subjectB;

    private int $teacherA1;

    private int $teacherA2;

    private int $teacherA3;

    private int $teacherB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->otherCompany = $other;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;

        // Contexte tenant : le service et le scope BelongsToCompany résolvent
        // la compagnie courante via ce binding (pattern tests Unit du repo).
        app()->instance('current_company', $company);

        $academicYearA = $this->academicYearRow($this->company);
        $academicYearB = $this->academicYearRow($this->otherCompany);

        $this->classA = $this->classRow($this->company, $academicYearA, 'CLS-A');
        $this->classA2 = $this->classRow($this->company, $academicYearA, 'CLS-A2');
        $this->classB = $this->classRow($this->otherCompany, $academicYearB, 'CLS-B');
        $this->subjectId = $this->subjectRow($this->company, 'MATH');
        $this->subjectB = $this->subjectRow($this->otherCompany, 'PHYS');
        $this->teacherA1 = $this->teacherRow($this->company, 'T-A1');
        $this->teacherA2 = $this->teacherRow($this->company, 'T-A2');
        $this->teacherA3 = $this->teacherRow($this->company, 'T-A3');
        $this->teacherB = $this->teacherRow($this->otherCompany, 'T-B');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_manager_can_create_timetable_slot(): void
    {
        $policy = app(EduTimetableSlotPolicy::class);
        $this->assertTrue($policy->create($this->manager));

        $slot = $this->slot(
            $this->classA,
            $this->teacherA1,
            1,
            '08:00:00',
            '09:00:00',
            room: 'Salle 12'
        );

        $this->assertInstanceOf(EduTimetableSlot::class, $slot);
        $this->assertSame($this->company->id, $slot->company_id);
        $this->assertSame(1, $slot->day_of_week);
        $this->assertSame('08:00:00', $slot->start_time);
        $this->assertSame('09:00:00', $slot->end_time);
        $this->assertSame('Salle 12', $slot->room);

        $this->assertDatabaseHas('edu_timetable_slots', [
            'id' => $slot->id,
            'company_id' => $this->company->id,
            'class_id' => $this->classA,
            'teacher_id' => $this->teacherA1,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);

        // Le gestionnaire voit les créneaux de SON tenant.
        $this->assertTrue($policy->view($this->manager, $slot));
        $this->assertTrue($policy->update($this->manager, $slot));
        $this->assertTrue($policy->delete($this->manager, $slot));
    }

    public function test_same_class_overlap_is_rejected(): void
    {
        $this->slot($this->classA, $this->teacherA1, 1, '08:00:00', '09:00:00');

        // Même classe, même jour, intervalle qui chevauche [08:00, 09:00),
        // même si l'ENSEIGNANT est différent → conflit CLASSE.
        $this->expectException(TimetableConflictException::class);
        $this->expectExceptionMessage('La classe');

        $this->slot($this->classA, $this->teacherA2, 1, '08:30:00', '09:30:00');
    }

    public function test_same_teacher_overlap_is_rejected(): void
    {
        $this->slot($this->classA, $this->teacherA1, 1, '08:00:00', '09:00:00');

        // Même enseignant, même jour, intervalle qui chevauche, même si la
        // CLASSE est différente → conflit ENSEIGNANT.
        $this->expectException(TimetableConflictException::class);
        $this->expectExceptionMessage('enseignant');

        $this->slot($this->classA2, $this->teacherA1, 1, '08:30:00', '09:30:00');
    }

    public function test_non_overlapping_adjacent_slot_is_allowed(): void
    {
        $this->slot($this->classA, $this->teacherA1, 1, '08:00:00', '09:00:00');

        // Intervalle demi-ouvert [start, end) : 09:00-10:00 ne chevauche PAS
        // 08:00-09:00 (adjacence autorisée), et l'enseignant diffère.
        $second = $this->slot($this->classA, $this->teacherA2, 1, '09:00:00', '10:00:00');

        $this->assertSame('09:00:00', $second->start_time);
        $this->assertDatabaseCount('edu_timetable_slots', 2);
    }

    public function test_cross_tenant_slot_is_refused(): void
    {
        // Un créneau existe chez le tenant B (même jour, mêmes horaires).
        $slotB = $this->slot(
            $this->classB,
            $this->teacherB,
            1,
            '08:00:00',
            '09:00:00',
            companyId: $this->otherCompany->id,
            subjectId: $this->subjectB
        );

        $policy = app(EduTimetableSlotPolicy::class);

        // Le gestionnaire du tenant A ne voit JAMAIS un créneau du tenant B.
        $this->assertFalse($policy->view($this->manager, $slotB));
        $this->assertFalse($policy->update($this->manager, $slotB));
        $this->assertFalse($policy->delete($this->manager, $slotB));

        // Pas de faux conflit cross-tenant : le créneau du tenant A, même
        // jour/heure, est créé sans exception.
        $slotA = $this->slot($this->classA, $this->teacherA1, 1, '08:00:00', '09:00:00');
        $this->assertSame($this->company->id, $slotA->company_id);

        // Le calendrier du tenant A n'expose rien pour la classe du tenant B.
        $calendar = app(TimetableService::class)->calendarForClass($this->classB, '1');
        $this->assertSame(0, $calendar->total());
    }

    public function test_calendar_for_class_is_ordered_and_paginated(): void
    {
        $this->slot($this->classA, $this->teacherA1, 1, '08:00:00', '09:00:00');
        $this->slot($this->classA, $this->teacherA2, 1, '09:00:00', '10:00:00');
        $this->slot($this->classA, $this->teacherA3, 1, '10:00:00', '11:00:00');
        $this->slot($this->classA, $this->teacherA1, 2, '08:00:00', '09:00:00');

        // Jour filtré, page de 2 : tri (day_of_week, start_time), pagination.
        $calendar = app(TimetableService::class)->calendarForClass($this->classA, '1', 2);

        $this->assertCount(2, $calendar->items());
        $this->assertSame(3, $calendar->total());
        $this->assertSame('08:00:00', $calendar->items()[0]->start_time);
        $this->assertSame('09:00:00', $calendar->items()[1]->start_time);
        $this->assertSame(1, $calendar->items()[0]->day_of_week);

        // Page 2 : le créneau restant du jour 1.
        $page2 = app(TimetableService::class)->calendarForClass($this->classA, '1', 2, page: 2);
        $this->assertCount(1, $page2->items());
        $this->assertSame('10:00:00', $page2->items()[0]->start_time);

        // Le jour 2 est isolé via le filtre explicite.
        $day2 = app(TimetableService::class)->calendarForClass($this->classA, '2');
        $this->assertSame(1, $day2->total());
        $this->assertSame(2, $day2->items()[0]->day_of_week);
    }

    public function test_calendar_default_day_is_resolved_in_tenant_timezone(): void
    {
        // 2026-09-07 23:30 UTC = lundi 23:30 UTC, mais mardi 00:30 en
        // Africa/Algiers (tenant DZ, UTC+1) → le « jour courant » du tenant
        // est le MARDI (2), pas le lundi (1) d'UTC.
        Carbon::setTestNow(Carbon::parse('2026-09-07 23:30:00', 'UTC'));

        try {
            $this->slot($this->classA, $this->teacherA1, 1, '08:00:00', '09:00:00');
            $this->slot($this->classA, $this->teacherA2, 2, '08:00:00', '09:00:00');

            // Sans filtre jour : le calendrier montre le jour LOCAL du tenant.
            $calendar = app(TimetableService::class)->calendarForClass($this->classA);

            $this->assertSame(1, $calendar->total());
            $this->assertSame(2, $calendar->items()[0]->day_of_week);

            // Le filtre explicite reste disponible.
            $monday = app(TimetableService::class)->calendarForClass($this->classA, '1');
            $this->assertSame(1, $monday->total());
            $this->assertSame(1, $monday->items()[0]->day_of_week);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Table parente — si la vraie migration (EDU-003/004/005, issues
     * parallèles #5819/#5820/#5821) ne l'a pas livrée, fixture minimale
     * miroir des colonnes utilisées par les inserts du test.
     */
    private function ensureTable(string $tableName): void
    {
        if (schemaTableExists($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
            $table->id();
            $table->uuid('company_id');

            if ($tableName === 'edu_academic_years') {
                $table->string('name', 120);
                $table->date('start_date');
                $table->date('end_date');
            } elseif ($tableName === 'edu_classes') {
                $table->unsignedBigInteger('academic_year_id');
                $table->string('name', 120);
            } elseif ($tableName === 'edu_subjects') {
                $table->string('code', 50);
                $table->string('name', 120);
            } elseif ($tableName === 'edu_teachers') {
                $table->string('display_name', 120);
            }

            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['id', 'company_id'], $tableName.'_id_company_unique');
        });
    }

    private function academicYearRow(Company $company): int
    {
        $this->ensureTable('edu_academic_years');

        return (int) DB::table('edu_academic_years')->insertGetId([
            'company_id' => $company->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function classRow(Company $company, int $academicYearId, string $name): int
    {
        $this->ensureTable('edu_classes');

        return (int) DB::table('edu_classes')->insertGetId([
            'company_id' => $company->id,
            'academic_year_id' => $academicYearId,
            'name' => $name,
            'status' => 'active',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function subjectRow(Company $company, string $name): int
    {
        $this->ensureTable('edu_subjects');

        return (int) DB::table('edu_subjects')->insertGetId([
            'company_id' => $company->id,
            'code' => strtoupper($name),
            'name' => $name,
            'status' => 'active',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function teacherRow(Company $company, string $displayName): int
    {
        $this->ensureTable('edu_teachers');

        return (int) DB::table('edu_teachers')->insertGetId([
            'company_id' => $company->id,
            'display_name' => $displayName,
            'status' => 'active',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  int  $dayOfWeek  1 = lundi … 7 = dimanche
     * @param  string|null  $companyId  défaut : tenant courant ($this->company)
     * @param  int|null  $subjectId  défaut : matière du tenant courant
     */
    private function slot(
        int $classId,
        int $teacherId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        ?string $companyId = null,
        ?int $subjectId = null,
        ?string $room = null,
    ): EduTimetableSlot {
        return app(TimetableService::class)->create([
            'company_id' => $companyId ?? $this->company->id,
            'class_id' => $classId,
            'subject_id' => $subjectId ?? $this->subjectId,
            'teacher_id' => $teacherId,
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'room' => $room,
        ]);
    }
}
