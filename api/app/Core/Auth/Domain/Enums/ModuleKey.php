<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Enums;

/**
 * Issue #7761 (délégation d'accès, spec MISSION_ESPACE_CLIENT §3.1) — registre
 * FERMÉ des modules délégables par le responsable du tenant (`principal`).
 *
 * Une valeur = un module de l'espace client qu'un grant
 * (`employee_module_grants.module_key`) peut ouvrir à un collaborateur, en
 * COMPLÉMENT du RBAC historique par `manager_role` : le middleware
 * `api.manager` accepte « manager_role autorisé OU grant explicite ».
 *
 * Extensible par nouvelle case uniquement (jamais d'entrée libre) : toute clé
 * hors registre est refusée en validation (fail-closed). Le registre vit dans
 * `Core` (consommé par le middleware HTTP, le modèle `Employee` et le module
 * HR) — le sens d'import autorisé est Modules→Core, jamais Core→Modules.
 */
enum ModuleKey: string
{
    case Marketing = 'marketing';
    case Accounting = 'accounting';
    case Support = 'support';
    case Crm = 'crm';
    case Showcase = 'showcase';
    case Hr = 'hr';
    case BillingView = 'billing_view';

    /**
     * Toutes les clés du registre (validation `Rule::in`, exposition front).
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }

    /** Une clé appartient-elle au registre ? (fail-closed sur l'inconnu) */
    public static function isValid(string $key): bool
    {
        return self::tryFrom($key) !== null;
    }
}
