<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Resources;

use App\Modules\Fundraising\Domain\Models\FundraisingSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sérialisation du paramétrage Fundraising (verticale FUNDRAISING).
 *
 * @mixin FundraisingSettings
 */
final class FundraisingSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FundraisingSettings $settings */
        $settings = $this->resource;

        return [
            'enabled_methods' => $settings->enabled_methods ?? [],
            'default_min_amount' => $settings->default_min_amount !== null ? (float) $settings->default_min_amount : null,
            'default_max_amount' => $settings->default_max_amount !== null ? (float) $settings->default_max_amount : null,
            'default_suggested_amounts' => $settings->default_suggested_amounts !== null
                ? array_map('floatval', $settings->default_suggested_amounts)
                : null,
            'allow_anonymous' => (bool) $settings->allow_anonymous,
            'allow_messages' => (bool) $settings->allow_messages,
            'show_supporters' => (bool) $settings->show_supporters,
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ];
    }
}
