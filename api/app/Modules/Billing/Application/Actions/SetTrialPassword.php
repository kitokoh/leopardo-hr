<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * #7962 — cas d'usage « choisir son mot de passe d'essai » extrait du
 * `SelfServiceTrialController` (859 lignes, le seul controller qui GROSSISSAIT
 * depuis l'audit structurel : +163 lignes).
 *
 * Le controller ne fait plus que du HTTP : lire l'en-tête, valider la forme du
 * mot de passe, appeler cette Action, sérialiser le résultat. Toutes les
 * règles métier vivent ici, et sont donc exerçables par un test qui ne passe
 * PAS par le controller — c'est la condition posée par la règle D4 du
 * registre (« pas d'extraction sans test ») : un test qui passe par le
 * controller ne survit pas à la prochaine refactorisation du controller.
 *
 * ## Pourquoi ce parcours existe
 *
 * Le guided trial créait le manager avec un mot de passe aléatoire jamais
 * communiqué et un `login_url` pointant sur une page à mot de passe ; l'email
 * d'accès étant best-effort, un client pouvait se retrouver provisionné sans
 * aucun moyen d'entrer (mailer non configuré en dev comme en prod).
 *
 * Le prospect détient déjà un secret : le `provisioning_token` renvoyé par
 * `/trial/signup` et pollé via `/trial/status`. Il lui suffit de choisir son
 * mot de passe avec ce token — l'email redevient un simple bonus.
 *
 * ## Garanties
 *
 * 1. **Token à usage unique.** Un second appel répond 409 : un token égaré ne
 *    peut pas réécrire un mot de passe déjà choisi.
 * 2. **Expiration 72 h** (#7490). Le lien magique de l'email de bienvenue
 *    porte ce token ; passé le délai, l'utilisateur passe par « Recevoir un
 *    code de connexion » puis définit son mot de passe depuis l'espace.
 * 3. **Révocation du magic link.** Le hash du token d'accès est retiré de
 *    `extra_data` : un email égaré ne peut plus ouvrir de session.
 * 4. **Aucune PII en clair dans les logs** (BOS-002 / #8144) : seul
 *    `company_id` est journalisé.
 */
final class SetTrialPassword
{
    /** Durée de vie du lien de définition de mot de passe (#7490). */
    public const LINK_TTL_HOURS = 72;

    /**
     * @return array{success: true, message: string, data: array{login_url: string}}
     *                                                                               |array{success: false, error: string, message: string, status: int}
     */
    public function execute(string $token, string $password): array
    {
        if ($token === '' || strlen($token) !== 64) {
            return $this->failure('PROVISIONING_TOKEN_INVALID', 'billing.trial_status_token_invalid', 404);
        }

        $row = DB::table('trial_provisionings')
            ->where('provisioning_token', $token)
            ->first();

        if ($row === null || $row->status !== 'ready' || $row->company_id === null) {
            return $this->failure('PROVISIONING_NOT_READY', 'billing.trial_password_not_ready', 409);
        }

        if (is_scalar($row->password_set_at) && (string) $row->password_set_at !== '') {
            return $this->failure('TRIAL_PASSWORD_ALREADY_SET', 'billing.trial_password_already_set', 409);
        }

        $provisionedAtRaw = $row->provisioned_at ?? null;
        if (is_scalar($provisionedAtRaw) && (string) $provisionedAtRaw !== ''
            && Carbon::parse((string) $provisionedAtRaw)->addHours(self::LINK_TTL_HOURS)->isPast()) {
            return $this->failure('TRIAL_PASSWORD_LINK_EXPIRED', 'billing.trial_password_link_expired', 410);
        }

        $manager = $this->findTrialManager((string) $row->email, (string) $row->company_id);

        if ($manager === null) {
            Log::error('trial.set_password_manager_not_found', [
                'company_id' => $row->company_id,
            ]);

            return $this->failure('TRIAL_MANAGER_NOT_FOUND', 'billing.trial_password_manager_missing', 404);
        }

        $manager->forceFill(['password_hash' => Hash::make($password)])->save();

        // Le magic link d'accès n'a plus lieu d'être : on révoque son hash pour
        // qu'un email égaré ne puisse plus ouvrir de session.
        $extraData = $manager->extra_data;
        unset($extraData['demo_access_token_hash'], $extraData['demo_access_token_expires_at']);
        $manager->forceFill(['extra_data' => $extraData])->save();

        DB::table('trial_provisionings')
            ->where('id', $row->id)
            ->update(['password_set_at' => now(), 'updated_at' => now()]);

        Log::info('trial.set_password_success', ['company_id' => $row->company_id]);

        return [
            'success' => true,
            'message' => __('billing.trial_password_set'),
            'data' => [
                'login_url' => is_string($row->login_url) && $row->login_url !== ''
                    ? $row->login_url
                    : '/auth/login',
            ],
        ];
    }

    /**
     * @return array{success: false, error: string, message: string, status: int}
     */
    private function failure(string $error, string $translationKey, int $status): array
    {
        return [
            'success' => false,
            'error' => $error,
            'message' => __($translationKey),
            'status' => $status,
        ];
    }

    /**
     * Retrouve le manager du sandbox d'essai.
     *
     * Requête non authentifiée, exactement comme `DemoLoginController` (le
     * magic link) : les sandbox vivent dans le schéma `shared_tenants`, inclus
     * dans le search_path par défaut du service.
     */
    private function findTrialManager(string $email, string $companyId): ?Employee
    {
        /** @var Employee|null $manager */
        $manager = Employee::query()
            ->where('email', $email)
            ->where('company_id', $companyId)
            ->where('role', 'manager')
            ->orderBy('id')
            ->first();

        return $manager;
    }
}
