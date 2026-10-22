<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FarmOs;

use App\Modules\FarmOs\Domain\Enums\FarmAnimalSex;
use App\Modules\FarmOs\Domain\Enums\FarmAnimalStatus;
use App\Modules\FarmOs\Domain\Enums\FarmHealthEventType;
use App\Modules\FarmOs\Domain\Enums\FarmLivestockEventType;
use App\Modules\FarmOs\Domain\Enums\FarmProductionType;
use App\Modules\FarmOs\Domain\Enums\FarmReproductionEventType;
use App\Modules\FarmOs\Domain\Enums\FarmSpecies;
use App\Modules\FarmOs\Domain\Exceptions\FarmLivestockException;
use App\Modules\FarmOs\Domain\Models\FarmAnimal;
use PHPUnit\Framework\TestCase;

/**
 * Farm OS — T3.1 : contrats du registre de cheptel.
 *
 * Tests SANS base de donnees : ils verrouillent les invariants qui ne dependent
 * pas de PostgreSQL (registres fermes, sens des evenements, codes d'erreur
 * stables, absence de libelle en dur). Le comportement transactionnel complet
 * (effectif, refus de sortie excessive, idempotence) est couvert par le test
 * Feature, qui exige PostgreSQL et ne tourne donc qu'en CI.
 */
final class FarmLivestockContractTest extends TestCase
{
    public function test_species_registry_is_closed_and_stable(): void
    {
        $this->assertSame([
            'cattle',
            'sheep',
            'goat',
            'poultry',
            'pig',
            'bee',
            'fish',
            'rabbit',
            'horse',
            'camel',
            'other',
        ], FarmSpecies::values());
    }

    public function test_animal_sex_and_status_registries_are_closed_and_stable(): void
    {
        $this->assertSame(['male', 'female', 'unknown'], FarmAnimalSex::values());
        $this->assertSame(['active', 'sold', 'dead', 'transferred', 'culled'], FarmAnimalStatus::values());
    }

    public function test_livestock_event_registry_is_closed_and_stable(): void
    {
        $this->assertSame([
            'birth',
            'purchase',
            'sale',
            'transfer_in',
            'transfer_out',
            'death',
            'slaughter',
            'cull',
        ], FarmLivestockEventType::values());
    }

    public function test_health_reproduction_and_production_registries_are_closed(): void
    {
        $this->assertSame(['vaccination', 'treatment', 'deworming', 'checkup', 'other'], FarmHealthEventType::values());
        $this->assertSame(['heat', 'mating', 'pregnancy_check', 'birth', 'abortion', 'drying_off'], FarmReproductionEventType::values());
        $this->assertSame(['milk', 'eggs', 'wool', 'honey', 'meat', 'manure', 'other'], FarmProductionType::values());
    }

    public function test_animal_origin_registry_is_closed_and_stable(): void
    {
        $this->assertSame(['born', 'purchased', 'transferred', 'unknown'], FarmAnimal::ORIGINS);
    }

    /**
     * Le SENS d'un mouvement d'effectif est porte par le TYPE, jamais par le
     * signe d'une quantite : c'est ce qui garantit qu'une quantite d'evenement
     * est toujours positive et qu'aucune sortie ne peut etre saisie « en
     * negatif » pour contourner la garde d'effectif.
     */
    public function test_event_direction_is_carried_by_the_type(): void
    {
        $this->assertTrue(FarmLivestockEventType::Birth->increasesHerd());
        $this->assertTrue(FarmLivestockEventType::Purchase->increasesHerd());
        $this->assertTrue(FarmLivestockEventType::TransferIn->increasesHerd());

        $this->assertFalse(FarmLivestockEventType::Sale->increasesHerd());
        $this->assertFalse(FarmLivestockEventType::TransferOut->increasesHerd());
        $this->assertFalse(FarmLivestockEventType::Death->increasesHerd());
        $this->assertFalse(FarmLivestockEventType::Slaughter->increasesHerd());
        $this->assertFalse(FarmLivestockEventType::Cull->increasesHerd());
    }

    public function test_exit_events_map_to_a_terminal_animal_status(): void
    {
        $this->assertSame(FarmAnimalStatus::Sold, FarmLivestockEventType::Sale->resultingStatus());
        $this->assertSame(FarmAnimalStatus::Dead, FarmLivestockEventType::Death->resultingStatus());
        $this->assertSame(FarmAnimalStatus::Dead, FarmLivestockEventType::Slaughter->resultingStatus());
        $this->assertSame(FarmAnimalStatus::Transferred, FarmLivestockEventType::TransferOut->resultingStatus());
        $this->assertSame(FarmAnimalStatus::Culled, FarmLivestockEventType::Cull->resultingStatus());

        // Une entree ne change pas le statut d'un animal.
        $this->assertNull(FarmLivestockEventType::Birth->resultingStatus());
        $this->assertNull(FarmLivestockEventType::Purchase->resultingStatus());
    }

    public function test_only_active_animals_count_in_the_present_herd(): void
    {
        $this->assertTrue(FarmAnimalStatus::Active->isPresent());
        $this->assertFalse(FarmAnimalStatus::Sold->isPresent());
        $this->assertFalse(FarmAnimalStatus::Dead->isPresent());
        $this->assertFalse(FarmAnimalStatus::Transferred->isPresent());
        $this->assertFalse(FarmAnimalStatus::Culled->isPresent());
    }

    public function test_gestation_is_opened_by_mating_and_closed_by_birth_or_abortion(): void
    {
        $this->assertTrue(FarmReproductionEventType::Mating->opensGestation());
        $this->assertFalse(FarmReproductionEventType::Heat->opensGestation());

        $this->assertTrue(FarmReproductionEventType::Birth->closesGestation());
        $this->assertTrue(FarmReproductionEventType::Abortion->closesGestation());
        $this->assertFalse(FarmReproductionEventType::PregnancyCheck->closesGestation());
    }

    public function test_withdrawal_period_is_only_relevant_for_treatments(): void
    {
        $this->assertTrue(FarmHealthEventType::Treatment->hasWithdrawalPeriod());
        $this->assertTrue(FarmHealthEventType::Deworming->hasWithdrawalPeriod());
        $this->assertFalse(FarmHealthEventType::Vaccination->hasWithdrawalPeriod());
        $this->assertFalse(FarmHealthEventType::Checkup->hasWithdrawalPeriod());
    }

    public function test_production_default_units_are_only_suggestions(): void
    {
        $this->assertSame('l', FarmProductionType::Milk->defaultUnit());
        $this->assertSame('unit', FarmProductionType::Eggs->defaultUnit());
        $this->assertSame('kg', FarmProductionType::Honey->defaultUnit());
    }

    public function test_typical_productions_are_only_suggestions(): void
    {
        $this->assertContains('milk', FarmSpecies::Cattle->typicalProductions());
        $this->assertContains('eggs', FarmSpecies::Poultry->typicalProductions());
        $this->assertContains('honey', FarmSpecies::Bee->typicalProductions());
        // Aucune production n'est IMPOSEE : une espece peut n'en proposer aucune.
        $this->assertSame([], FarmSpecies::Other->typicalProductions());
    }

    public function test_translation_keys_are_namespaced(): void
    {
        foreach (FarmSpecies::cases() as $case) {
            $this->assertStringStartsWith('farm.species.', $case->translationKey());
        }

        foreach (FarmAnimalSex::cases() as $case) {
            $this->assertStringStartsWith('farm.animal_sex.', $case->translationKey());
        }

        foreach (FarmAnimalStatus::cases() as $case) {
            $this->assertStringStartsWith('farm.animal_status.', $case->translationKey());
        }

        foreach (FarmLivestockEventType::cases() as $case) {
            $this->assertStringStartsWith('farm.livestock_event.', $case->translationKey());
        }

        foreach (FarmHealthEventType::cases() as $case) {
            $this->assertStringStartsWith('farm.health_event.', $case->translationKey());
        }

        foreach (FarmReproductionEventType::cases() as $case) {
            $this->assertStringStartsWith('farm.reproduction_event.', $case->translationKey());
        }

        foreach (FarmProductionType::cases() as $case) {
            $this->assertStringStartsWith('farm.production_type.', $case->translationKey());
        }
    }

    public function test_livestock_exception_exposes_stable_ascii_error_codes(): void
    {
        $cases = [
            [FarmLivestockException::batchNotInCompany(1), 'FARM_LIVESTOCK_BATCH_NOT_IN_COMPANY'],
            [FarmLivestockException::animalNotInCompany(2), 'FARM_LIVESTOCK_ANIMAL_NOT_IN_COMPANY'],
            [FarmLivestockException::quantityMustBePositive('0'), 'FARM_LIVESTOCK_QUANTITY_NOT_POSITIVE'],
            [FarmLivestockException::insufficientHeadCount(3, 5, 9), 'FARM_LIVESTOCK_INSUFFICIENT_HEAD'],
            [FarmLivestockException::animalAlreadyLeft(4), 'FARM_LIVESTOCK_ANIMAL_ALREADY_LEFT'],
            [FarmLivestockException::speciesMismatch('cattle', 'sheep'), 'FARM_LIVESTOCK_SPECIES_MISMATCH'],
            [FarmLivestockException::invalidQuantity('x'), 'FARM_LIVESTOCK_INVALID_QUANTITY'],
        ];

        foreach ($cases as [$exception, $expectedCode]) {
            $this->assertSame($expectedCode, $exception->errorCode());
            // Garde i18n (PA2-I18N-007 / #5432) : jamais de libelle accentue.
            $this->assertSame(0, preg_match('/[^\x00-\x7F]/', $exception->errorCode()));
        }
    }
}
