<?php

declare(strict_types=1);

namespace App\Shared\Rules;

use Illuminate\Validation\Rules\Password;

/**
 * Politique de mots de passe UNIQUE du dépôt (norme #5620, unifiée par #7995).
 *
 * Avant #7995, quatre surfaces (création/modification d'employé, inscription
 * acheteur marketplace, acceptation d'invitation web) acceptaient `min:8`
 * sans robustesse ni blocklist alors que le changement de mot de passe
 * exigeait `Password::min(12)->numbers()` + `NotCommonPassword` — le compte
 * était moins protégé à sa création qu'à sa mise à jour.
 *
 * Toute nouvelle surface qui collecte un mot de passe DOIT passer par ce
 * helper (garde : dev-hub/tools/check-password-policy.sh).
 */
final class PasswordPolicy
{
    /**
     * Règles pour un champ mot de passe REQUIS (création de compte).
     *
     * @return array<int, mixed>
     */
    public static function required(bool $confirmed = true, int $max = 255): array
    {
        $rules = ['required', 'string', Password::min(12)->numbers(), new NotCommonPassword, "max:{$max}"];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    /**
     * Règles pour un champ mot de passe OPTIONNEL (ex. mise à jour où le
     * champ peut être absent ou nul = inchangé).
     *
     * @return array<int, mixed>
     */
    public static function optional(int $max = 255): array
    {
        return ['nullable', 'string', Password::min(12)->numbers(), new NotCommonPassword, "max:{$max}"];
    }
}
