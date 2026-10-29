<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * #7962 — création d'un employé depuis un profil Google, réservée au mode démo.
 *
 * Ce code vivait dans `AuthController::handleGoogleCallback`. Il a sa place en
 * Infrastructure et non en Application pour deux raisons : il touche `Hash` et
 * `Str` (façades interdites en Application par `check-layer-purity.sh`) et il
 * écrit en base — c'est un effet de bord, pas une décision métier.
 *
 * L'issue #3724 interdit tout auto-provisionnement silencieux : la décision
 * d'appeler ce service reste dans l'Action, derrière la double garde
 * `demo_mode_enabled` + autorisation explicite du flux.
 */
final class DemoEmployeeProvisioner
{
    /**
     * Champs sensibles posés explicitement (non mass-assignables, #3597).
     */
    public function provision(SocialiteUser $googleUser): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::forceCreate([
            'first_name' => $googleUser->offsetGet('given_name') ?? $googleUser->getName(),
            'last_name' => $googleUser->offsetGet('family_name') ?? '',
            'email' => $googleUser->getEmail(),
            'password_hash' => Hash::make(Str::random(24)),
            'google_id' => (string) $googleUser->getId(),
        ]);

        $employee->role = 'ordinary';
        $employee->status = 'active';
        $employee->save();

        return $employee;
    }
}
