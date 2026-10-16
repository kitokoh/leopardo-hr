<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

use App\Modules\FarmOs\Domain\Enums\FarmCapability;

/**
 * Farm OS (BC-37 AGRICULTURE) — Type d'opération agricole (intervention).
 *
 * Couvre la chaîne de travail exigée par la mission : préparation des sols,
 * semis/plantation, fertilisation, irrigation, désherbage, intervention
 * phytosanitaire, surveillance des cultures, récolte, stockage, mise en marché.
 *
 * Ce registre est un MODÈLE DE PLANIFICATION, jamais une prescription
 * agronomique : aucun rendu ne doit laisser croire à une recommandation
 * garantie pour une région, une culture ou une météo donnée (ADR-FARM-0003).
 */
enum FarmOperationType: string
{
    case SoilPreparation = 'soil_preparation';
    case SowingPlanting = 'sowing_planting';
    case Fertilization = 'fertilization';
    case Irrigation = 'irrigation';
    case Weeding = 'weeding';
    case CropProtection = 'crop_protection';
    case CropMonitoring = 'crop_monitoring';
    case Harvesting = 'harvesting';
    case StorageHandling = 'storage_handling';
    case MarketingSale = 'marketing_sale';

    public function translationKey(): string
    {
        return 'farm.operation_type.'.$this->value;
    }

    /**
     * Capacité requise en plus de `work_planning` pour planifier/consulter ce
     * type d'opération (gating serveur supplémentaire).
     */
    public function requiredCapability(): FarmCapability
    {
        return match ($this) {
            self::Fertilization, self::CropProtection => FarmCapability::StocksInputs,
            self::Irrigation => FarmCapability::Irrigation,
            self::Harvesting, self::StorageHandling => FarmCapability::HarvestStorage,
            self::MarketingSale => FarmCapability::SalesCustomers,
            self::SoilPreparation, self::SowingPlanting, self::Weeding, self::CropMonitoring => FarmCapability::ParcelsCrops,
        };
    }

    /**
     * Le type génère-t-il une consommation d'intrant (semence, engrais,
     * produit phytosanitaire, aliment) ?
     */
    public function consumesInputs(): bool
    {
        return in_array($this, [self::SowingPlanting, self::Fertilization, self::CropProtection], true);
    }

    /**
     * Le type est-il soumis à des exigences réglementaires particulières
     * (produits phytosanitaires) — permission `farm.regulated_inputs` ?
     */
    public function isRegulated(): bool
    {
        return $this === self::CropProtection;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
