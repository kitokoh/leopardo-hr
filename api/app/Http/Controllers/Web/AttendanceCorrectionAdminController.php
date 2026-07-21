<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionAdminController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function index(Request $request): View
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('update', new AttendanceLog(['company_id' => $actor->company_id]));

        $status = $request->query('status', 'pending');

        $corrections = AttendanceCorrectionRequest::query()
            ->with(['employee:id,company_id,first_name,last_name,matricule', 'attendanceLog:id,employee_id,date,session_number,status'])
            ->where('company_id', $actor->company_id)
            ->when($status !== 'all', fn ($builder) => $builder->where('status', $status))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('attendance-corrections.index', [
            'corrections' => $corrections,
            'status' => $status,
        ]);
    }

    public function approve(Request $request, AttendanceCorrectionRequest $correction): RedirectResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('update', new AttendanceLog(['company_id' => $correction->company_id]));
        $this->ensureCorrectionBelongsToActorCompany($correction, $actor);

        if ($correction->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => [__('attendance.correction_already_processed')],
            ]);
        }

        $employee = Employee::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($correction->employee_id);

        $log = $correction->attendanceLog;
        if (! $log) {
            $sessionNumber = ((int) AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', $correction->date)
                ->max('session_number')) + 1;

            $log = new AttendanceLog([
                'company_id' => $actor->company_id,
                'employee_id' => $employee->id,
                'schedule_id' => $employee->schedule_id,
                'date' => $correction->date,
                'session_number' => $sessionNumber,
                'method' => 'manual',
                'work_type' => 'normal',
            ]);
        }

        $log->fill([
            'check_in' => $correction->requested_check_in,
            'check_out' => $correction->requested_check_out,
            'method' => 'manual',
            'corrected_by' => $actor->id,
            'correction_note' => $correction->reason,
        ]);

        $log = $this->attendanceService->recalculateLog($log);

        $correction->forceFill([
            'attendance_log_id' => $log->id,
            'status' => 'applied',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        return redirect()
            ->route('attendance-corrections.index')
            ->with('status', __('attendance.correction_applied'));
    }

    public function reject(Request $request, AttendanceCorrectionRequest $correction): RedirectResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('update', new AttendanceLog(['company_id' => $correction->company_id]));
        $this->ensureCorrectionBelongsToActorCompany($correction, $actor);

        if ($correction->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => [__('attendance.correction_already_processed')],
            ]);
        }

        $correction->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        return redirect()
            ->route('attendance-corrections.index')
            ->with('status', __('attendance.correction_rejected'));
    }

    private function ensureCorrectionBelongsToActorCompany(AttendanceCorrectionRequest $correction, Employee $actor): void
    {
        if ($correction->company_id !== $actor->company_id) {
            abort(404);
        }
    }
}
