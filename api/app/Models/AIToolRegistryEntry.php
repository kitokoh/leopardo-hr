<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property array<mixed> $parameters
 * @property array<mixed> $required_permissions
 * @property string|null $required_role
 * @property string|null $module
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AIToolRegistryEntry extends Model
{
    protected $table = 'ai_tool_registry';

    protected $fillable = [
        'name',
        'description',
        'parameters',
        'required_permissions',
        'required_role',
        'module',
        'active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'required_permissions' => 'array',
            'active' => 'boolean',
        ];
    }
}
