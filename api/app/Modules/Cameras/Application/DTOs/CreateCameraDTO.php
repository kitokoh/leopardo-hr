<?php

namespace App\Modules\Cameras\Application\DTOs;

use App\Modules\Cameras\Interfaces\Api\V1\Requests\StoreCameraRequest;
use App\Modules\Cameras\Interfaces\Api\V1\Requests\UpdateCameraRequest;

class CreateCameraDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $rtsp_url,
        public readonly ?string $location = null,
        public readonly int $sort_order = 0,
        public readonly ?string $stream_path_override = null,
        public readonly array $metadata = [],
    ) {}

    public static function fromRequest(StoreCameraRequest $request): self
    {
        return new self(
            name: $request->string('name'),
            rtsp_url: $request->string('rtsp_url'),
            location: $request->string('location'),
            sort_order: $request->integer('sort_order', 0),
            stream_path_override: $request->string('stream_path_override'),
            metadata: $request->input('metadata', []),
        );
    }
}
