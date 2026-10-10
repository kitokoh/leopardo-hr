<?php

declare(strict_types=1);

namespace App\Modules\Geo\Interfaces\Api\V1\Resources;

use App\Shared\Geo\NearestResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un résultat « le plus proche » (GEO-05, issue #8354, BC-33 GEO).
 *
 * Enveloppe HTTP du value object partagé NearestResult : la représentation
 * (id, label, distance_meters, latitude, longitude) est définie UNE SEULE
 * FOIS dans le VO — les verticales et l'API exposent le même contrat.
 */
final class NearestResultResource extends JsonResource
{
    /**
     * @return array{id: int|string|null, label: string, distance_meters: int, latitude: float|null, longitude: float|null}
     */
    public function toArray(Request $request): array
    {
        /** @var NearestResult $result */
        $result = $this->resource;

        return $result->toArray();
    }
}
