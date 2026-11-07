<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Services;

use App\Core\Auth\Domain\Models\User;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount;
use App\Support\PhoneNumber;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * IAM-L4 (#9094) — Pont d'identité entre un compte grand public et `users`.
 *
 * ## Ce que ce service fait, et ce qu'il refuse de faire
 *
 * Il implémente l'algorithme de résolution de l'ADR-IDENTITE-CLIENTS-PUBLICS §6 :
 * on cherche un `users` par **téléphone normalisé** d'abord, puis par e-mail, et
 * on en crée un seulement si aucun ne correspond.
 *
 * Il **ne fusionne jamais deux `users` existants**. C'est la garde-fou central
 * contre la prise de contrôle de compte : si le téléphone pointe vers un `users`
 * et l'e-mail vers un **autre**, on ne choisit pas « le meilleur des deux » — on
 * refuse et on laisse l'opération à un humain (support, avec trace). Une fusion
 * est une opération **administrée**, pas un effet de bord d'inscription.
 *
 * ## Pourquoi le téléphone doit être vérifié
 *
 * `link()` n'écrit `user_id` que si `phone_verified_at` est posé : la contrainte
 * `CHECK` de la migration le refuserait de toute façon (SQLSTATE 23514), mais
 * échouer ici donne un message exploitable plutôt qu'une erreur SQL brute.
 *
 * ## Messages d'exception
 *
 * Les messages sont des **codes de diagnostic** (préfixe `public_identity_*`),
 * volontairement sans accent : ce ne sont pas des messages utilisateur (ceux-ci
 * passent par le catalogue `__()`), et le garde i18n `check-hardcoded-accented-messages.sh`
 * interdit tout littéral accentué sur cette surface.
 */
final class PublicIdentityBridge
{
    /**
     * Retrouve ou crée le `users` correspondant à un compte grand public.
     *
     * @param  string|null  $email  E-mail de contact (jamais la clé d'identité).
     * @param  string|null  $phoneE164  Numéro déjà normalisé en E.164.
     *
     * @throws RuntimeException si le téléphone et l'e-mail désignent deux `users` distincts.
     */
    public function resolveUserForPublicAccount(?string $email, ?string $phoneE164): User
    {
        $phone = $this->normalizePhone($phoneE164);
        $email = $this->normalizeEmail($email);

        $byPhone = $phone !== null
            ? User::query()->where('phone_e164', $phone)->first()
            : null;

        $byEmail = $email !== null
            ? User::query()->where('email', $email)->first()
            : null;

        // Deux identités distinctes désignées par deux facteurs : on REFUSE.
        // Fusionner automatiquement serait exactement la prise de contrôle que
        // l'ADR interdit (§6, avertissement).
        if ($byPhone !== null && $byEmail !== null && $byPhone->id !== $byEmail->id) {
            throw new RuntimeException(
                'public_identity_conflict: le telephone et l email designent deux comptes distincts; '
                .'une fusion est une operation administree, jamais un effet de bord d inscription.'
            );
        }

        if ($byPhone !== null) {
            return $byPhone;
        }

        if ($byEmail !== null) {
            return $byEmail;
        }

        return $this->createUser($email, $phone);
    }

    /**
     * Relie un compte grand public à un `users`, **après vérification du
     * téléphone**. Idempotent : relier deux fois le même couple ne change rien.
     *
     * @throws RuntimeException si le téléphone n'est pas vérifié.
     */
    public function link(TravelCustomerAccount $account, User $user): TravelCustomerAccount
    {
        if ($account->phone_verified_at === null) {
            throw new RuntimeException(
                'public_identity_unverified_phone: on ne relie jamais un compte a un users '
                .'sur un telephone non verifie (garde anti-prise-de-controle).'
            );
        }

        if ($account->user_id === $user->id) {
            return $account;
        }

        $account->forceFill(['user_id' => $user->id])->save();

        return $account->refresh();
    }

    /**
     * Marque le téléphone du compte comme vérifié, puis relie.
     *
     * Le numéro est **normalisé à l'écriture** (ADR §4, règle 2) : deux
     * écritures du même numéro doivent produire la même chaîne, sinon l'unicité
     * en base ne sert à rien.
     */
    public function verifyPhoneAndLink(
        TravelCustomerAccount $account,
        string $phoneE164,
        ?User $user = null,
    ): TravelCustomerAccount {
        $phone = $this->normalizePhone($phoneE164);

        if ($phone === null) {
            throw new RuntimeException('public_identity_invalid_phone: le numero n est pas un E.164 valide.');
        }

        $account->forceFill([
            'phone_e164' => $phone,
            'phone_verified_at' => now(),
        ])->save();

        $account->refresh();

        $resolved = $user ?? $this->resolveUserForPublicAccount($account->email, $phone);

        return $this->link($account, $resolved);
    }

    /**
     * Normalise un numéro en E.164, ou `null`. Délègue au noyau partagé : une
     * seule normalisation dans tout le dépôt (#8736).
     */
    private function normalizePhone(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        return PhoneNumber::isValidE164($raw) ? $raw : PhoneNumber::toE164($raw);
    }

    private function normalizeEmail(?string $email): ?string
    {
        $value = trim((string) $email);

        return $value === '' ? null : Str::lower($value);
    }

    /**
     * Crée un `users` minimal. `first_name`/`last_name` sont dérivés de l'e-mail
     * faute de mieux : le profil se complète ensuite, l'identité existe déjà.
     */
    private function createUser(?string $email, ?string $phone): User
    {
        $local = $email !== null ? Str::before($email, '@') : 'client';

        return User::query()->create([
            'first_name' => Str::title($local),
            'last_name' => '',
            'email' => $email ?? $local.'@public.local',
            'phone_e164' => $phone,
            'provider' => 'public',
            'preferred_language' => 'fr',
            'status' => 'active',
        ]);
    }
}
