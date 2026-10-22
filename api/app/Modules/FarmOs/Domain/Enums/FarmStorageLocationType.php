<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T3.2 Type d'emplacement de stockage.
 *
 * Registre FERME et VERSIONNE. Aucun libelle utilisateur ici (garde i18n
 * PA2-I18N-007) : la traduction se fait cote front via {@see translationKey()}.
 */
enum FarmStorageLocationType: string
{
    case Barn = 'barn';
    case Silo = 'silo';
    case Warehouse = 'warehouse';
    case ColdRoom = 'cold_room';
    case Tank = 'tank';
    case FieldStack = 'field_stack';
    case Other = 'other';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.storage_location_type.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
