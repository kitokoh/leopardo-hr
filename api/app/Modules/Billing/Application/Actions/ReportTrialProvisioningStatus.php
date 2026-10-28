<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Actions;

use App\Modules\Billing\Application\Contracts\TrialProvisioningReader;
use Illuminate\Support\Carbon;

/**
 * Cas d'usage : restituer l'état d'un provisioning d'essai à un visiteur
 * anonyme, à partir de son `provisioning_token` (#7962).
 *
 * Extrait de `SelfServiceTrialController::status()`. L'Action ne fait aucun
 * HTTP : elle prend le token, rend un tableau normalisé ou `null` quand le
 * token est inconnu. Le contrôleur garde le contrat de transport (headers,
 * codes 404/200, forme de l'enveloppe `success` + `data`).
 *
 * Deux invariants métier portés ici, qui n'ont rien à faire dans un
 * contrôleur :
 * - le lien d'accès n'est exposé QUE lorsque le sandbox est `ready` — un
 *   prospect n'a rien à ouvrir tant que le provisioning n'a pas abouti ;
 * - `provisioned_at` est stocké en chaîne (insert `DB::table`) : on ne peut
 *   jamais appeler `->toIso8601String()` sur une chaîne (500, cf. #2903).
 */
class ReportTrialProvisioningStatus
{
    public function __construct(
        private readonly TrialProvisioningReader $provisionings,
    ) {}

    /**
     * @return array{status: string, provisioned_at: string|null, password_set: bool, access_sent: bool, login_url?: string}|null
     */
    public function execute(string $token): ?array
    {
        $row = $this->provisionings->findByToken($token);

        if ($row === null) {
            return null;
        }

        $provisionedAt = is_scalar($row->provisioned_at) && (string) $row->provisioned_at !== ''
            ? Carbon::parse((string) $row->provisioned_at)->toIso8601String()
            : null;

        $data = [
            'status' => (string) $row->status,
            'provisioned_at' => $provisionedAt,
            // Onboarding sans mailer : le front doit savoir si un mot de passe a
            // déjà été choisi, et si l'e-mail d'accès est réellement parti.
            'password_set' => is_scalar($row->password_set_at) && (string) $row->password_set_at !== '',
            'access_sent' => is_scalar($row->access_sent_at ?? null)
                && (string) ($row->access_sent_at ?? '') !== '',
        ];

        // Le lien d'accès n'est exposé qu'une fois le sandbox prêt (jamais
        // dans l'état pending — le prospect n'a rien à ouvrir avant).
        if ($data['status'] === 'ready' && is_string($row->login_url) && $row->login_url !== '') {
            $data['login_url'] = $row->login_url;
        }

        return $data;
    }

    /**
     * Valide le format du token de provisioning (64 caractères).
     *
     * Vit dans l'Action pour que l'interface n'ait pas à connaître la règle :
     * un token mal formé et un token inconnu produisent la même réponse,
     * conformément à l'anti-énumération.
     */
    public function isWellFormed(string $token): bool
    {
        return strlen($token) === 64;
    }
}
