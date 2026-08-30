<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

/**
 * TRAVEL-914 (#6422) — Mise à jour d'une grille tarifaire d'annonce.
 * Mêmes règles que la création (références tenant-scoped, montants en
 * unités mineures, devise cohérente avec le tenant).
 */
class UpdateTravelAdvertPriceRequest extends StoreTravelAdvertPriceRequest
{
}
