<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ligne de suivi `trial_provisionings` (issue #3951).
 *
 * Extrait de `SelfServiceTrialController` (997 lignes) : les trois parcours
 * d'inscription (self-service vérifié, activation directe, sandbox guidé)
 * écrivaient la MÊME ligne avec le MÊME payload, chacun avec sa propre copie
 * de la gestion de course sur l'index partiel unique
 * `trial_provisionings_pending_email_unique`. Trois copies d'un insert qui
 * doivent rester synchronisées, c'est une dérive garantie : la parité de
 * colonnes (#9305) n'était tenue que par la vigilance.
 *
 * La politique d'erreur reste chez l'appelant — les trois parcours ne
 * réagissent pas pareil à un échec (l'un journalise et poursuit, l'autre
 * répond 503, le troisième relance) — mais la mécanique est ici, à un seul
 * endroit.
 */
class TrialProvisioningRowService
{
    /**
     * Token de la ligne `pending` existante pour cet e-mail, s'il y en a une.
     *
     * Idempotence (#3951) : un double POST (retry réseau, double clic, onglet
     * dupliqué) ne doit PAS créer deux lignes pending — donc deux tenants.
     */
    public function findPendingToken(string $email): ?string
    {
        $row = DB::table('trial_provisionings')
            ->where('email', $email)
            ->where('status', 'pending')
            ->first();

        return $row === null ? null : (string) $row->provisioning_token;
    }

    /**
     * Crée la ligne `pending` et renvoie son token.
     *
     * @param  bool  $withLockTimeout  borne le verrou (55P03) sur le parcours
     *                                 self-service : la ligne est un CONFORT
     *                                 (suivi de statut + mot de passe), jamais
     *                                 une condition de l'essai — un verrou
     *                                 résiduel ne doit pas faire attendre le
     *                                 signup.
     *
     * @throws \Illuminate\Database\QueryException dont le code `23505`
     *                                             signale qu'un POST concurrent a gagné la course.
     */
    public function createPending(string $email, string $companyName, string $country, bool $withLockTimeout = false): string
    {
        $token = Str::random(64);

        $insert = function () use ($email, $companyName, $country, $token): void {
            DB::table('trial_provisionings')->insert([
                'email' => $email,
                'company_name' => $companyName,
                'country' => strtoupper($country),
                'provisioning_token' => $token,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        if ($withLockTimeout) {
            DB::transaction(function () use ($insert): void {
                DB::statement("SET LOCAL lock_timeout = '3s'");
                $insert();
            });
        } else {
            $insert();
        }

        return $token;
    }
}
