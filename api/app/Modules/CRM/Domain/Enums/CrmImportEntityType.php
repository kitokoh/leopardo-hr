<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Enums;

/**
 * #5714 — Types d'entités importables par l'import CSV CRM.
 *
 * Whitelist stricte (ADR-CRM-005) : l'import n'accepte que ces trois entités
 * tenant-scoped. Toute autre valeur est rejetée en 422 par la Request.
 */
enum CrmImportEntityType: string
{
    case Accounts = 'accounts';
    case Contacts = 'contacts';
    case Leads = 'leads';

    /**
     * Colonnes autorisées par entité (whitelist — toute colonne inconnue
     * dans le CSV produit une erreur par ligne, jamais un élargissement de
     * périmètre).
     *
     * @return array<string, bool> colonne => requise
     */
    public function allowedColumns(): array
    {
        return match ($this) {
            self::Accounts => [
                'name' => true,
                'email' => false,
                'phone' => false,
                'notes' => false,
            ],
            self::Contacts => [
                'first_name' => true,
                'last_name' => true,
                'email' => false,
                'phone' => false,
                'account_name' => false,
                'title' => false,
                'is_primary' => false,
                'notes' => false,
            ],
            self::Leads => [
                'first_name' => true,
                'last_name' => true,
                'company_name' => false,
                'email' => false,
                'phone' => false,
                'source' => false,
                'status' => false,
                'notes' => false,
            ],
        };
    }

    /**
     * Colonnes PII — masquées dans les réponses de preview (jamais exposées
     * en clair sans autorisation, cf. #5713).
     *
     * @return list<string>
     */
    public function sensitiveColumns(): array
    {
        return ['email', 'phone'];
    }

    /**
     * Libellé lisible (erreurs localisées, journal d'audit).
     */
    public function label(): string
    {
        return match ($this) {
            self::Accounts => 'accounts',
            self::Contacts => 'contacts',
            self::Leads => 'leads',
        };
    }
}
