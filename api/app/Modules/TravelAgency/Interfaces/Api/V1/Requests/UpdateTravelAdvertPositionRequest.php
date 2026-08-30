<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

/**
 * TRAVEL-914 (#6422) — Mise à jour d'une position d'annonce.
 */
class UpdateTravelAdvertPositionRequest extends UpdateTravelAdvertReferenceRequest
{
    protected function referenceRouteParam(): string
    {
        return 'travelAdvertPosition';
    }

    protected function referenceTable(): string
    {
        return 'travel_advert_positions';
    }
}
