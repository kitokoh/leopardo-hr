<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelExportAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelExportAsset>
 */
class TravelExportAssetFactory extends Factory
{
    protected $model = TravelExportAsset::class;

    public function definition(): array
    {
        return [
            'report_type' => 'sales',
            'idempotency_key' => $this->faker->unique()->uuid(),
            'status' => TravelExportAsset::STATUS_PENDING,
            'file_path' => null,
            'expires_at' => null,
            'error_redacted' => null,
            'created_by_user_id' => null,
        ];
    }
}
