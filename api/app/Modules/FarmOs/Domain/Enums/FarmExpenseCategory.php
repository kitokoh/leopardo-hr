<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T4.1 Categorie de depense agricole.
 *
 * Registre FERME et VERSIONNE. Aucun libelle utilisateur ici (garde i18n
 * PA2-I18N-007) : la traduction se fait cote front via {@see translationKey()}.
 */
enum FarmExpenseCategory: string
{
    case Seeds = 'seeds';
    case Fertilizer = 'fertilizer';
    case Phytosanitary = 'phytosanitary';
    case Feed = 'feed';
    case Veterinary = 'veterinary';
    case Labour = 'labour';
    case EquipmentMaintenance = 'equipment_maintenance';
    case Fuel = 'fuel';
    case Storage = 'storage';
    case Transport = 'transport';
    case Irrigation = 'irrigation';
    case Other = 'other';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.expense_category.'.$this->value;
    }

    /**
     * Categorie rattachee a un INTRANT du catalogue T2.1 ? Sert a proposer le
     * lien `farm_input_id` a la saisie — jamais a l'imposer.
     */
    public function isInputRelated(): bool
    {
        return in_array($this, [self::Seeds, self::Fertilizer, self::Phytosanitary, self::Feed, self::Veterinary], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
