<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
