<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Infrastructure\Mail\PasswordResetMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Issue #2626 — réinitialisation de mot de passe.
 *
 * POST /auth/forgot-password : émission d'un jeton à usage unique (60 min),
 * réponse générique anti-énumération.
 * POST /auth/reset-password : validation du jeton + nouveau mot de passe,
 * révocation des tokens Sanctum existants.
 */
class PasswordResetController
{
    private const TOKEN_TTL_MINUTES = 60;

    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        /** @var Employee|null $employee */
        $employee = Employee::withoutGlobalScopes()->where('email', $email)->first();

        // Anti-énumération : même réponse que l'email existe ou non.
        if ($employee !== null) {
            $token = Str::random(64);

            DB::table('public.password_reset_tokens')->insert([
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
                'used_at' => null,
                'created_at' => now(),
            ]);

            Mail::to($email)->send(new PasswordResetMail($token, $email));
        }

        return new JsonResponse([
            'message' => 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = strtolower(trim($validated['email']));
        $tokenHash = hash('sha256', $validated['token']);

        $row = DB::table('public.password_reset_tokens')
            ->where('email', $email)
            ->where('token_hash', $tokenHash)
            ->first();

        // Token absent, expiré ou déjà consommé → refus générique (422).
        if ($row === null || $row->used_at !== null || now()->greaterThan($row->expires_at)) {
            return new JsonResponse([
                'message' => 'Jeton de réinitialisation invalide, expiré ou déjà utilisé.',
                'error' => 'INVALID_RESET_TOKEN',
            ], 422);
        }

        /** @var Employee|null $employee */
        $employee = Employee::withoutGlobalScopes()->where('email', $email)->first();

        if ($employee === null) {
            return new JsonResponse([
                'message' => 'Jeton de réinitialisation invalide, expiré ou déjà utilisé.',
                'error' => 'INVALID_RESET_TOKEN',
            ], 422);
        }

        // Consommation du jeton (idempotence) puis mise à jour du mot de passe.
        DB::table('public.password_reset_tokens')
            ->where('email', $email)
            ->where('token_hash', $tokenHash)
            ->update(['used_at' => now()]);

        $employee->update(['password_hash' => Hash::make($validated['password'])]);

        // Issue #2626 : révocation des sessions existantes (tokens Sanctum).
        $employee->tokens()->delete();

        return new JsonResponse([
            'message' => 'Mot de passe réinitialisé. Connectez-vous avec votre nouveau mot de passe.',
        ]);
    }
}
