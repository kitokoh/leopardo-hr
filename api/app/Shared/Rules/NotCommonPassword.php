<?php

declare(strict_types=1);

namespace App\Shared\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Audit onboarding 2026-09-14 — la politique de mot de passe se limitait à
 * `Password::min(8)->numbers()` : `password123`, `azerty123` et `12345678`
 * passaient. On ajoute un refus explicite des mots de passe les plus courants
 * (liste locale, AUCUN appel réseau : pas de dépendance à un service tiers
 * dans le chemin d'inscription) et des motifs trivialement devinables.
 *
 * Usage :
 *   'password' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers(), new NotCommonPassword()],
 */
final class NotCommonPassword implements ValidationRule
{
    /**
     * Mots de passe les plus utilisés (fuites publiques, top mondial) +
     * variantes locales FR/EN/AR/TR et motifs clavier. Volontairement court :
     * la cible est le mot de passe « évident », pas une liste exhaustive.
     *
     * @var list<string>
     */
    private const BLOCKLIST = [
        'password', 'password1', 'password12', 'password123', 'password1234',
        'motdepasse', 'motdepasse1', 'motdepasse123', 'mdp', 'mdp123',
        'azerty', 'azerty1', 'azerty123', 'qwerty', 'qwerty123', 'qwertyuiop',
        '123456', '1234567', '12345678', '123456789', '1234567890', '12345678910',
        '111111', '000000', '654321', '987654321', 'abcdef', 'abcdefg', 'abc123',
        'letmein', 'welcome', 'welcome1', 'welcome123', 'admin', 'admin123',
        'administrator', 'root', 'root123', 'toor', 'test', 'test123', 'test1234',
        'guest', 'guest123', 'user', 'user123', 'login', 'login123',
        'leopardo', 'leopardo1', 'leopardo123', 'leopardorh',
        'secret', 'secret123', 'iloveyou', 'monkey', 'dragon', 'sunshine',
        'football', 'baseball', 'master', 'shadow', 'superman', 'batman',
        'solo1234', 'entreprise', 'societe', 'societe123', 'employe', 'employe123',
        'parola', 'parola123', 'sifre', 'sifre123', 'turkiye', 'istanbul123',
        'dz123456', 'algerie', 'algerie123', 'maroc123', 'tunisie123',
        'khalifa', 'mohamed123', 'amine123', 'karim123',
    ];

    /** Suites alphabétiques / numériques triviales détectables par algorithme. */
    private const SEQUENCES = [
        'abcdefghijklmnopqrstuvwxyz',
        '01234567890',
        'qwertyuiopasdfghjklzxcvbnm',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $normalized = mb_strtolower($value);
        // Les chiffres en fin de mot de passe sont le réflexe n°1 (`password1`) :
        // on teste aussi la racine sans suffixe numérique.
        $root = rtrim($normalized, '0123456789');

        foreach ([$normalized, $root] as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if (in_array($candidate, self::BLOCKLIST, true)) {
                $fail(__('errors.PASSWORD_TOO_COMMON'));

                return;
            }
        }

        // Mot de passe entièrement composé d'un seul caractère répété, ou
        // contenant la totalité d'une séquence connue (`abcdefgh…`).
        if (preg_match('/^(.)\1{7,}$/', $normalized) === 1) {
            $fail(__('errors.PASSWORD_TOO_COMMON'));

            return;
        }
        foreach (self::SEQUENCES as $sequence) {
            if (mb_strlen($normalized) >= 8 && str_contains($sequence, $normalized)) {
                $fail(__('errors.PASSWORD_TOO_COMMON'));

                return;
            }
        }
    }
}
