<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Requests;

/**
 * TRAVEL-914 (#6422) — Mise à jour d'une question de quiz (gestion admin).
 * `correct_option_index` est accepté en entrée uniquement (jamais exposé
 * en lecture par les endpoints publics/participants).
 */
class UpdateTravelQuizQuestionRequest extends StoreTravelQuizQuestionRequest
{
}
