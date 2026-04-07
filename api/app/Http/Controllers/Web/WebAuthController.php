<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class WebAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var Employee|null $employee */
        $employee = Employee::query()
            ->where('email', $validated['email'])
            ->first();

        if (! $employee || ! Hash::check($validated['password'], $employee->password_hash)) {
            return back()
                ->withInput(['email' => $validated['email']])
                ->withErrors(['email' => 'Identifiants invalides.']);
        }

        if (! $employee->isManager()) {
            return back()
                ->withInput(['email' => $validated['email']])
                ->withErrors(['email' => 'Acces reserve aux managers.']);
        }

        if ($employee->status !== 'active') {
            return back()
                ->withInput(['email' => $validated['email']])
                ->withErrors(['email' => 'Compte inactif.']);
        }

        if (in_array($employee->company?->status, ['suspended', 'expired'], true)) {
            return back()
                ->withInput(['email' => $validated['email']])
                ->withErrors(['email' => 'Societe suspendue ou expiree.']);
        }

        Auth::guard('web')->login($employee);
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
