<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * Statut du provisioning d'un essai (#9732).
 *
 * Cas d'usage extrait du `SelfServiceTrialController::status()` : la lecture de
 * `trial_provisionings` et la construction de la charge utile (dont le lien
 * d'activation #9306) ne sont pas du HTTP.
 *
 * ## Regles conservees
 *
 * 1. **#2903** : `provisioned_at` est stocke en string (insert `DB::table`) —
 *    ne JAMAIS appeler `->toIso8601String()` sur une string (500).
 * 2. **#9306** : un espace provisionne doit etre ACCESSIBLE. Tant que le
 *    manager n'a pas choisi son mot de passe, on expose le lien d'activation
 *    (meme token que `POST /trial/set-password`), sans dependre d'un e-mail.
 * 3. Le lien d'acces n'est expose qu'une fois le sandbox pret (jamais en
 *    `pending` — le prospect n'a rien a ouvrir avant).
 */
final class GetTrialStatus
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @return array{success: true, data: array<string, mixed>}|array{success: false, error: string}
     */
    public function execute(string $token): array
    {
        if ($token === '' || strlen($token) !== 64) {
            return ['success' => false, 'error' => 'PROVISIONING_TOKEN_INVALID'];
        }

        $row = $this->db->table('trial_provisionings')
            ->where('provisioning_token', $token)
            ->first();

        if ($row === null) {
            return ['success' => false, 'error' => 'PROVISIONING_TOKEN_INVALID'];
        }

        // #2903 : provisioned_at est stocke en string — ne JAMAIS appeler
        // ->toIso8601String() sur une string (500).
        $provisionedAt = is_scalar($row->provisioned_at) && (string) $row->provisioned_at !== ''
            ? Carbon::parse((string) $row->provisioned_at)->toIso8601String()
            : null;

        $data = [
            'status' => $row->status,
            'provisioned_at' => $provisionedAt,
        ];

        // Le lien d'acces n'est expose qu'une fois le sandbox pret.
        if ($row->status === 'ready' && is_string($row->login_url) && $row->login_url !== '') {
            $data['login_url'] = $row->login_url;
        }
        if ($row->status === 'failed') {
            $data['message'] = __('billing.trial_status_failed');
        }

        // Onboarding sans mailer : le front doit savoir si un mot de passe a
        // deja ete choisi et si l'email d'acces est reellement parti.
        $data['password_set'] = is_scalar($row->password_set_at)
            && (string) $row->password_set_at !== '';
        $data['access_sent'] = is_scalar($row->access_sent_at ?? null)
            && (string) ($row->access_sent_at ?? '') !== '';

        // #9306 — tant que le manager n'a pas choisi son mot de passe, on
        // expose le lien d'activation pour que l'ecran de suivi puisse
        // l'afficher immediatement, sans dependre d'un e-mail.
        if ($row->status === 'ready' && $data['password_set'] === false) {
            // `/auth/set-password?token=` est l'ecran qui consomme le
            // `provisioning_token` (POST /trial/set-password). `/auth/activate`
            // est reserve au flux d'invitation collaborateur.
            $data['activation_url'] = rtrim((string) config('app.frontend_url'), '/')
                .'/auth/set-password?token='.$token;
        }

        return ['success' => true, 'data' => $data];
    }
}
