<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Services;

use App\Modules\FarmOs\Domain\Enums\FarmActivity;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;

/**
 * Farm OS — Recommandation d'outils à l'étape 3 de l'onboarding (FR-1.4).
 *
 * PUR et DÉTERMINISTE : aucune donnée externe, aucune inférence opaque. Le
 * résultat est une PROPOSITION que l'utilisateur accepte, refuse ou modifie —
 * Farm OS n'active jamais un outil dans le dos de l'exploitant.
 *
 * Ne suppose jamais que toutes les activités nécessitent les mêmes outils :
 * l'élevage ne reçoit jamais l'irrigation, un profil végétal ne reçoit jamais le
 * cheptel.
 */
final class FarmCapabilityRecommender
{
    /**
     * @param  list<string>  $activities  Valeurs de {@see FarmActivity}
     * @return list<string> Valeurs de {@see FarmCapability}
     */
    public function forActivities(array $activities): array
    {
        $resolved = $this->resolveActivities($activities);
        $hasLivestock = $this->hasLivestock($resolved);
        $hasCrops = $this->hasCrops($resolved);
        $isMixed = $this->isMixed($resolved);
        $isServiceOrCoop = $this->isServiceOrCoop($resolved);

        $recommended = FarmCapability::coreValues();

        if ($hasCrops) {
            $recommended[] = FarmCapability::ParcelsCrops->value;
            $recommended[] = FarmCapability::Irrigation->value;
            $recommended[] = FarmCapability::HarvestStorage->value;
        }

        if ($hasLivestock) {
            $recommended[] = FarmCapability::Livestock->value;
        }

        if ($isMixed || $hasLivestock || $hasCrops) {
            $recommended[] = FarmCapability::StocksInputs->value;
            $recommended[] = FarmCapability::Equipment->value;
        }

        if ($isMixed || $isServiceOrCoop) {
            $recommended[] = FarmCapability::Workers->value;
        }

        $recommended[] = FarmCapability::PurchasesSuppliers->value;
        $recommended[] = FarmCapability::SalesCustomers->value;
        $recommended[] = FarmCapability::FinanceExpenses->value;

        if ($isMixed || $isServiceOrCoop) {
            $recommended[] = FarmCapability::ReportsKpis->value;
        }

        // Aucune capacité d'élevage pour un profil exclusivement végétal, et
        // inversement : l'irrigation n'est jamais proposée à un éleveur pur.
        return array_values(array_unique(array_filter(
            $recommended,
            static function (string $capability) use ($hasLivestock, $hasCrops): bool {
                $enum = FarmCapability::tryFrom($capability);

                if ($enum === null) {
                    return false;
                }

                if ($enum->isLivestockOnly() && ! $hasLivestock) {
                    return false;
                }

                if ($enum === FarmCapability::Irrigation && ! $hasCrops) {
                    return false;
                }

                return true;
            }
        )));
    }

    /**
     * Capacités disponibles sur l'exploitation mais non recommandées :
     * l'utilisateur peut les activer quand même.
     *
     * @param  list<string>  $activities
     * @return list<string>
     */
    public function optionalForActivities(array $activities): array
    {
        $recommended = $this->forActivities($activities);

        return array_values(array_diff(FarmCapability::values(), $recommended));
    }

    /**
     * @param  list<string>  $activities
     * @return list<FarmActivity>
     */
    private function resolveActivities(array $activities): array
    {
        $resolved = [];

        foreach ($activities as $activity) {
            $enum = FarmActivity::tryFrom((string) $activity);

            if ($enum !== null) {
                $resolved[] = $enum;
            }
        }

        return $resolved;
    }

    /**
     * @param  list<FarmActivity>  $activities
     */
    private function hasLivestock(array $activities): bool
    {
        foreach ($activities as $activity) {
            if ($activity->isLivestock()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<FarmActivity>  $activities
     */
    private function hasCrops(array $activities): bool
    {
        foreach ($activities as $activity) {
            if ($activity->isCropProduction()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<FarmActivity>  $activities
     */
    private function isMixed(array $activities): bool
    {
        return $this->hasLivestock($activities) && $this->hasCrops($activities);
    }

    /**
     * @param  list<FarmActivity>  $activities
     */
    private function isServiceOrCoop(array $activities): bool
    {
        foreach ($activities as $activity) {
            if ($activity->requiresCombinedSetup()) {
                return true;
            }
        }

        return false;
    }
}
