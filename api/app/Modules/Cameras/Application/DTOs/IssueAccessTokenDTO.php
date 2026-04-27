<?php

namespace App\Modules\Cameras\Application\DTOs;

use App\Modules\Cameras\Interfaces\Api\V1\Requests\StoreCameraAccessTokenRequest;

class IssueAccessTokenDTO
{
    public function __construct(
        public readonly int $expires_in_minutes,
        public readonly ?string $label = null,
        public readonly ?string $granted_to_email = null,
        public readonly ?string $granted_to_name = null,
        public readonly array $permissions = ['view' => true],
        public readonly ?array $ip_whitelist = null,
    ) {}

    public static function fromRequest(StoreCameraAccessTokenRequest $request): self
    {
        return new self(
            expires_in_minutes: $request->integer('expires_in_minutes', 60),
            label: $request->string('label'),
            granted_to_email: $request->string('granted_to_email'),
            granted_to_name: $request->string('granted_to_name'),
            permissions: $request->input('permissions', ['view' => true]),
            ip_whitelist: $request->input('ip_whitelist'),
        );
    }
}
