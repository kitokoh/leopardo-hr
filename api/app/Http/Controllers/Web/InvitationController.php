<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HR\Domain\Models\UserInvitation;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use App\Shared\Rules\PasswordPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(
        private readonly UserInvitationService $userInvitationService,
    ) {}

    public function showActivationForm(string $token): View
    {
        $invitation = UserInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        return view('auth.activate-invitation', [
            'token' => $token,
            'invitation' => $invitation,
        ]);
    }

    public function activate(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate([
            'password' => PasswordPolicy::required(), // #7995 — premier mdp du compte : norme #5620
        ]);

        $this->userInvitationService->accept($token, $validated['password']);

        return redirect()->route('login')->with('status', 'Compte active. Vous pouvez maintenant vous connecter.');
    }
}
