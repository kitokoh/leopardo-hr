<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\PrintJobStatus;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPrintJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantPrintJob>
 */
class RestaurantPrintJobFactory extends Factory
{
    protected $model = RestaurantPrintJob::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'order_id' => RestaurantOrder::factory(),
            'ticket_kind' => PrintTicketKind::KITCHEN->value,
            'revision' => 1,
            'status' => PrintJobStatus::PENDING->value,
            'attempts' => 0,
            'printer_ref' => null,
            'last_error' => null,
            'payload_hash' => null,
            'claimed_at' => null,
            'printed_at' => null,
            'failed_at' => null,
        ];
    }

    public function printed(): static
    {
        return $this->state(fn (): array => [
            'status' => PrintJobStatus::PRINTED->value,
            'attempts' => 1,
            'printed_at' => now(),
        ]);
    }
}
