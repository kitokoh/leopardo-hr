<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

/**
 * TRAVEL-905 (#6108) — Création d'une position d'annonce.
 */
class StoreTravelAdvertPositionRequest extends StoreTravelAdvertReferenceRequest
{
    protected function referenceTable(): string
    {
        return 'travel_advert_positions';
    }
}
