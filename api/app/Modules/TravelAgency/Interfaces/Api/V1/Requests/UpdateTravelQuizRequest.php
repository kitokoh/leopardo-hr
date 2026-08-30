<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

/**
 * TRAVEL-914 (#6422) — Mise à jour d'un quiz (gestion admin).
 * Mêmes règles que la création ; statut borné draft/active/closed.
 */
class UpdateTravelQuizRequest extends StoreTravelQuizRequest
{
}
