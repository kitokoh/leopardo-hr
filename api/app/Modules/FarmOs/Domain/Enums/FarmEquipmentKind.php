<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T2.2 Type de materiel agricole.
 *
 * Registre FERME et VERSIONNE : extension par nouvelle case uniquement, jamais
 * de renommage ni de retrait (meme regle que `FarmActivity` / `FarmInputCategory`).
 *
 * Aucun libelle utilisateur ici (garde i18n PA2-I18N-007) : la traduction se
 * fait cote front via {@see translationKey()}.
 */
enum FarmEquipmentKind: string
{
    case Tractor = 'tractor';
    case Harvester = 'harvester';
    case Seeder = 'seeder';
    case Sprayer = 'sprayer';
    case Trailer = 'trailer';
    case Truck = 'truck';
    case Car = 'car';
    case IrrigationSystem = 'irrigation_system';
    case MilkingMachine = 'milking_machine';
    case Tool = 'tool';
    case Other = 'other';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.equipment_kind.'.$this->value;
    }

    /**
     * Type de compteur le plus courant pour ce materiel. Sert UNIQUEMENT de
     * valeur par defaut proposee a la saisie : Farm OS ne devine jamais un
     * compteur reel, et l'exploitant peut toujours le corriger.
     */
    public function defaultMeterType(): FarmEquipmentMeterType
    {
        return match ($this) {
            self::Tractor, self::Harvester, self::Seeder, self::Sprayer => FarmEquipmentMeterType::Hours,
            self::Truck, self::Car => FarmEquipmentMeterType::Kilometers,
            default => FarmEquipmentMeterType::None,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
