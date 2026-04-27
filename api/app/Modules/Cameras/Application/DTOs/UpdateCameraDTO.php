<?php

namespace App\Modules\Cameras\Application\DTOs;

use App\Modules\Cameras\Interfaces\Api\V1\Requests\UpdateCameraRequest;

class UpdateCameraDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $rtsp_url = null,
        public readonly ?string $location = null,
        public readonly ?int $sort_order = null,
        public readonly ?string $stream_path_override = null,
        public readonly ?array $metadata = null,
        public readonly ?bool $is_active = null,
    ) {}

    public static function fromRequest(UpdateCameraRequest $request): self
    {
        return new self(
            name: $request->has('name') ? $request->string('name') : null,
            rtsp_url: $request->has('rtsp_url') ? $request->string('rtsp_url') : null,
            location: $request->has('location') ? $request->string('location') : null,
            sort_order: $request->has('sort_order') ? $request->integer('sort_order') : null,
            stream_path_override: $request->has('stream_path_override') ? $request->string('stream_path_override') : null,
            metadata: $request->has('metadata') ? $request->input('metadata') : null,
            is_active: $request->has('is_active') ? $request->boolean('is_active') : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'rtsp_url' => $this->rtsp_url,
            'location' => $this->location,
            'sort_order' => $this->sort_order,
            'stream_path_override' => $this->stream_path_override,
            'metadata' => $this->metadata,
            'is_active' => $this->is_active,
        ], fn ($v) => $v !== null);
    }
}
