<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FarmOs;

use App\Modules\FarmOs\Domain\Enums\FarmActivity;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityRecommender;
use PHPUnit\Framework\TestCase;

/**
 * Farm OS — Recommandation d'outils selon les activités déclarées (FR-1.4).
 *
 * Vérifie qu'un profil végétal ne reçoit JAMAIS le cheptel, qu'un éleveur pur
 * ne reçoit jamais l'irrigation, et que les outils de socle sont toujours
 * proposés.
 */
final class FarmCapabilityRecommenderTest extends TestCase
{
    private FarmCapabilityRecommender $recommender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recommender = new FarmCapabilityRecommender;
    }

    public function test_crop_profile_never_receives_livestock_tools(): void
    {
        $recommended = $this->recommender->forActivities([FarmActivity::CerealCrops->value]);

        $this->assertNotContains(FarmCapability::Livestock->value, $recommended);
        $this->assertContains(FarmCapability::ParcelsCrops->value, $recommended);
        $this->assertContains(FarmCapability::Irrigation->value, $recommended);
    }

    public function test_livestock_profile_never_receives_irrigation(): void
    {
        $recommended = $this->recommender->forActivities([FarmActivity::Cattle->value]);

        $this->assertContains(FarmCapability::Livestock->value, $recommended);
        $this->assertNotContains(FarmCapability::Irrigation->value, $recommended);
    }

    public function test_mixed_farming_receives_both_crop_and_livestock_tools(): void
    {
        $recommended = $this->recommender->forActivities([
            FarmActivity::CerealCrops->value,
            FarmActivity::Poultry->value,
        ]);

        $this->assertContains(FarmCapability::ParcelsCrops->value, $recommended);
        $this->assertContains(FarmCapability::Livestock->value, $recommended);
        $this->assertContains(FarmCapability::Workers->value, $recommended);
    }

    public function test_core_capabilities_are_always_recommended(): void
    {
        $recommended = $this->recommender->forActivities([FarmActivity::Aquaculture->value]);

        foreach (FarmCapability::coreValues() as $core) {
            $this->assertContains($core, $recommended);
        }
    }

    public function test_unknown_activity_values_are_ignored_not_trusted(): void
    {
        $recommended = $this->recommender->forActivities(['not_a_real_activity']);

        $this->assertNotContains(FarmCapability::Livestock->value, $recommended);
        $this->assertSame($this->recommender->forActivities([]), $recommended);
    }

    public function test_optional_tools_are_the_complement_of_recommended_ones(): void
    {
        $activities = [FarmActivity::MarketGardening->value];

        $recommended = $this->recommender->forActivities($activities);
        $optional = $this->recommender->optionalForActivities($activities);

        $this->assertSame([], array_values(array_intersect($recommended, $optional)));
        $this->assertCount(count(FarmCapability::values()), array_merge($recommended, $optional));
    }
}
