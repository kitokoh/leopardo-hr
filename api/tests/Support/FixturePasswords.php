<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * #8129 — mots de passe de fixture CONFORMES à la politique unique
 * (App\Shared\Rules\PasswordPolicy : ≥12 caractères, au moins un chiffre,
 * hors blocklist NotCommonPassword, aucune séquence triviale).
 *
 * Depuis le durcissement de la politique (#7995/#8021), toute fixture qui
 * poste `password` / `new_password` sur un endpoint de création ou de
 * changement de mot de passe DOIT utiliser ces valeurs : un mot de passe
 * faible de fixture (`password123`, `secret1234`…) se prend un 422 avant
 * même d'atteindre le comportement testé.
 *
 * Les écritures directes en base (`password_hash` via factory/forceFill)
 * contournent la validation HTTP et n'ont PAS besoin de ce helper.
 */
final class FixturePasswords
{
    /**
     * Mot de passe de création de compte/employé conforme.
     * (Valeur déjà éprouvée dans la suite — EmployeePasswordProvisioningTest,
     * EmployeeServiceCreateFillableTest, CriticalFunnelPayrollE2ETest.)
     */
    public const VALID = 'ProvidedPass123!';

    /**
     * Variante conforme distincte de VALID — pour les flux exigeant deux
     * mots de passe différents (ex. changement : ancien ≠ nouveau).
     */
    public const VALID_ALTERNATIVE = 'Str0ngPass!2026';

    public static function valid(): string
    {
        return self::VALID;
    }

    public static function validAlternative(): string
    {
        return self::VALID_ALTERNATIVE;
    }
}
