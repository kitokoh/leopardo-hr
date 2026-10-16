<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

use App\Modules\FarmOs\Domain\Enums\FarmActivity;

/**
 * Farm OS (BC-37 AGRICULTURE) — Outil métier activable (capacité).
 *
 * ADR-FARM-0001 : les 13 outils de la mission NE SONT PAS 13 feature flags.
 * Un seul flag de solution existe (`farm_os`, porté par le module) ; la
 * modularité interne est déclarée ici (registre FERMÉ, source unique) et son
 * activation par tenant est stockée dans `farm_capabilities`. Le gating réel
 * est serveur (`FarmCapabilityGate` + middleware `farm.capability`).
 *
 * `reusedModules()` documente ce qui est RÉUTILISÉ du socle Leopardo : Farm OS
 * ne recrée jamais un système existant (rôles, tâches, notifications, stock
 * comptable, facturation, comptabilité, dépenses, véhicules, géospatial).
 */
enum FarmCapability: string
{
    case ParcelsCrops = 'parcels_crops';
    case WorkPlanning = 'work_planning';
    case Workers = 'workers';
    case Equipment = 'equipment';
    case StocksInputs = 'stocks_inputs';
    case Irrigation = 'irrigation';
    case Livestock = 'livestock';
    case PurchasesSuppliers = 'purchases_suppliers';
    case HarvestStorage = 'harvest_storage';
    case SalesCustomers = 'sales_customers';
    case FinanceExpenses = 'finance_expenses';
    case ReportsKpis = 'reports_kpis';
    case NotificationsAssistant = 'notifications_assistant';

    public function translationKey(): string
    {
        return 'farm.capability.'.$this->value;
    }

    /**
     * Permission requise pour UTILISER la capacité (voir FarmOsManifest).
     */
    public function requiredPermission(): string
    {
        return match ($this) {
            self::StocksInputs => 'farm.stock',
            self::Equipment => 'farm.equipment',
            self::Livestock => 'farm.livestock',
            self::SalesCustomers, self::PurchasesSuppliers => 'farm.sales',
            self::FinanceExpenses, self::ReportsKpis => 'farm.accounting',
            self::Workers => 'farm.manager',
            default => 'farm.manager',
        };
    }

    /**
     * Modules / socles Leopardo réutilisés par la capacité — documentaire et
     * testable (aucun code de Farm OS ne réimplémente ces briques).
     *
     * @return list<string>
     */
    public function reusedModules(): array
    {
        return match ($this) {
            self::WorkPlanning => ['planning', 'notifications'],
            self::Workers => ['rh', 'hr'],
            self::Equipment => ['fleet'],
            self::SalesCustomers => ['crm', 'billing', 'catalog'],
            self::FinanceExpenses => ['expense', 'accounting'],
            self::ReportsKpis => ['accounting'],
            self::NotificationsAssistant => ['notifications', 'ai'],
            self::ParcelsCrops, self::Irrigation => ['geo'],
            default => [],
        };
    }

    /**
     * Une capacité « cœur » est proposée à TOUS les profils d'agriculteur.
     */
    public function isCore(): bool
    {
        return in_array($this, [
            self::ParcelsCrops,
            self::WorkPlanning,
            self::NotificationsAssistant,
        ], true);
    }

    /**
     * Capacité réservée à l'élevage (n'est jamais proposée à un profil
     * exclusivement végétal).
     */
    public function isLivestockOnly(): bool
    {
        return $this === self::Livestock;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /**
     * Capacités « cœur » proposées même sans activité connue (tenant frais).
     *
     * @return list<string>
     */
    public static function coreValues(): array
    {
        return array_values(array_map(
            static fn (self $c): string => $c->value,
            array_filter(self::cases(), static fn (self $c): bool => $c->isCore())
        ));
    }
}
