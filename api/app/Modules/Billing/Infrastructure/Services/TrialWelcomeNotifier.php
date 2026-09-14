<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Mail\TrialWelcomeMail;
use Illuminate\Support\Facades\Mail;

/**
 * Envoi des e-mails de bienvenue d'essai — EDU/BILLING, pattern #6568.
 *
 * Extraite de `VerifyTrialSignup` (couche `Application/`) : la facade `Mail`
 * y était utilisée, ce que la garde de pureté des couches interdit
 * (`check-layer-purity.sh`, facades réservées à `Interfaces/` et
 * `Infrastructure/`). Le déplacement vers `Infrastructure/Services` est le
 * pattern sanctionné (ADR-0020) et **préserve la sémantique de la facade**,
 * dont dépendent les tests (`Mail::fake()`).
 *
 * L'envoi reste best-effort côté appelant : cette classe ne rattrape rien.
 */
final class TrialWelcomeNotifier
{
    public function sendWelcome(Company $company, Employee $manager, string $temporaryPassword): void
    {
        Mail::to($manager->email)->send(
            new TrialWelcomeMail($company, $manager, $temporaryPassword)
        );
    }
}
