<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
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
        /** @var Employee $actor */
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
        /** @var Employee $actor */
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

        $dispatch = $this->userInvitationService->createAndSend(
            company: $employee->company,
            employee: $employee,
            invitedByType: 'manager',
            invitedByEmail: $actor->email ?? '',
        );

        // #9114 : garde de course — la vérification `accepted_at` ci-dessus est
        // faite hors transaction ; le service est l'autorité et renvoie un jeton
        // vide quand l'invitation a été consommée entre-temps.
        if ($dispatch->token === '') {
            return back()->withErrors(['invitation' => 'Cette invitation a deja ete acceptee.']);
        }

        // #9322 : mailer KO ⇒ annoncer « renvoyée » serait un mensonge et
        // laisserait le collaborateur sans moyen d'activer. On remonte le lien
        // pour que le manager le transmette lui-même.
        if ($dispatch->hasFallbackLink()) {
            return back()
                ->with('status', 'Le courriel n a pas pu partir. Transmettez ce lien a '.$employee->email.'.')
                ->with('invitation_activation_url', $dispatch->activationUrl);
        }

        return back()->with('status', 'Invitation renvoyee a '.$employee->email);
    }
}
