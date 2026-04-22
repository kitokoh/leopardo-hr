<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\UserInvitation;
use App\Services\UserInvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gestion des invitations cote manager (RH / Principal).
 *
 * Routes :
 *  - GET  /hr/invitations
 *  - POST /hr/invitations/{invitation}/resend
 *
 * Middleware : auth:web + tenant + manager_role:principal,rh
 */
class InvitationManagementController extends Controller
{
    public function __construct(
        private readonly UserInvitationService $userInvitationService,
    ) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        $companyId = $actor->company_id;

        $invitations = UserInvitation::query()
            ->where('company_id', $companyId)
            ->orderByDesc('last_sent_at')
            ->limit(200)
            ->get();

        $employees = Employee::query()
            ->whereIn('id', $invitations->pluck('employee_id')->filter()->all())
            ->get()
            ->keyBy('id');

        return view('hr.invitations.index', [
            'invitations' => $invitations,
            'employees' => $employees,
        ]);
    }

    public function resend(Request $request, string $invitation): RedirectResponse
    {
        $actor = $request->user();

        /** @var UserInvitation $record */
        $record = UserInvitation::query()
            ->where('id', $invitation)
            ->where('company_id', $actor->company_id)
            ->firstOrFail();

        $employee = Employee::query()->findOrFail($record->employee_id);

        if ($record->accepted_at !== null) {
            return back()->withErrors(['invitation' => 'Cette invitation a deja ete acceptee.']);
        }

        $this->userInvitationService->createAndSend(
            company: $employee->company,
            employee: $employee,
            invitedByType: 'manager',
            invitedByEmail: $actor->email,
        );

        return back()->with('status', 'Invitation renvoyee a '.$employee->email);
    }
}
