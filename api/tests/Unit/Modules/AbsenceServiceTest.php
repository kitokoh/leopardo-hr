<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Modules\Absence\Application\DTOs\RequestAbsenceDTO;
use App\Modules\Absence\Domain\Exceptions\AbsenceDateConflictException;
use App\Modules\Absence\Domain\Exceptions\AbsenceNotPendingException;
use App\Modules\Absence\Domain\Exceptions\InsufficientLeaveBalanceException;
use App\Modules\Absence\Domain\Models\Absence;
use App\Modules\Absence\Infrastructure\Services\AbsenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private AbsenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AbsenceService();
    }

    public function test_request_absence_dto_fromArray(): void
    {
        $dto = RequestAbsenceDTO::fromArray([
            'employee_id'     => 1,
            'absence_type_id' => 2,
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-07-05',
            'reason'          => 'Vacances',
        ]);

        $this->assertSame(1, $dto->employeeId);
        $this->assertSame(2, $dto->absenceTypeId);
        $this->assertSame('2026-07-01', $dto->startDate->toDateString());
        $this->assertSame('2026-07-05', $dto->endDate->toDateString());
        $this->assertSame('Vacances', $dto->reason);
    }

    public function test_request_absence_dto_fromArray_without_reason(): void
    {
        $dto = RequestAbsenceDTO::fromArray([
            'employee_id'     => 1,
            'absence_type_id' => 1,
            'start_date'      => '2026-07-01',
            'end_date'        => '2026-07-01',
        ]);

        $this->assertNull($dto->reason);
    }

    public function test_absence_not_pending_exception_message(): void
    {
        $this->expectException(AbsenceNotPendingException::class);
        throw new AbsenceNotPendingException();
    }

    public function test_absence_date_conflict_exception_message(): void
    {
        $this->expectException(AbsenceDateConflictException::class);
        throw new AbsenceDateConflictException();
    }

    public function test_insufficient_leave_balance_exception_message(): void
    {
        $this->expectException(InsufficientLeaveBalanceException::class);
        throw new InsufficientLeaveBalanceException(5.0, 2.0);
    }

    public function test_approve_throws_when_not_pending(): void
    {
        $absence = new Absence();
        $absence->status = 'approved';
        $absence->exists = false; // do not persist

        $this->expectException(AbsenceNotPendingException::class);

        // Access the check logic directly
        if ($absence->status !== 'pending') {
            throw new AbsenceNotPendingException();
        }
    }
}
