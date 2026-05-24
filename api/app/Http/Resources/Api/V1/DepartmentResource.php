<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Department */
class DepartmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'manager_id' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager->id,
                'first_name' => $this->manager->first_name,
                'last_name' => $this->manager->last_name,
            ]),
            'positions' => $this->whenLoaded('positions', fn () => $this->positions->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
