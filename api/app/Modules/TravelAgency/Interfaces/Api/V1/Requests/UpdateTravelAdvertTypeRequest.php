<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

/**
 * TRAVEL-914 (#6422) — Mise à jour d'un type d'annonce.
 */
class UpdateTravelAdvertTypeRequest extends UpdateTravelAdvertReferenceRequest
{
    protected function referenceRouteParam(): string
    {
        return 'travelAdvertType';
    }

    protected function referenceTable(): string
    {
        return 'travel_advert_types';
    }
}
