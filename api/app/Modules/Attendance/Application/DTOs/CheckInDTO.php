<?php

namespace App\DTOs;

use App\Http\Requests\Api\V1\Attendance\CheckInRequest;
use App\Http\Requests\Api\V1\Attendance\CheckOutRequest;

final readonly class CheckInDTO
{
    public function __construct(
        public ?float $gps_lat = null,
        public ?float $gps_lng = null,
        public ?float $gps_accuracy = null,
        public string $method = 'mobile',
        public ?string $occurred_at = null,
        public ?string $external_event_id = null,
        public ?string $biometric_type = null,
        public bool $synced_from_offline = false,
        public ?string $action = 'check_in',
        public ?string $source_device_code = null,
        public string $work_type = 'normal',
        public ?string $punch_note = null,
        public ?string $device_timezone = null,
    ) {}

    public static function fromRequest(CheckInRequest|CheckOutRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            gps_lat: $validated['gps_lat'] ?? null,
            gps_lng: $validated['gps_lng'] ?? null,
            gps_accuracy: $validated['gps_accuracy'] ?? null,
            method: 'mobile',
            work_type: $validated['work_type'] ?? 'normal',
            punch_note: $validated['punch_note'] ?? null,
            device_timezone: $validated['device_timezone'] ?? null,
        );
    }
}
