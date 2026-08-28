<?php

declare(strict_types=1);

namespace App\Core\Feature\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Interrupteur global d'une feature/module (MAT-010, #5868).
 *
 * Table `public.feature_kill_switches` : une ligne avec `is_active = true`
 * stoppe la feature pour TOUTE la plateforme (fail-closed dans
 * `App\Core\Tenant\Domain\Models\Company::hasFeature()`), sans suppression
 * de données. L'historique des bascules reste en base (`toggled_by`,
 * `toggled_at`, `reason`) + canal d'audit JSON.
 *
 * @property int $id
 * @property string $feature_key
 * @property bool $is_active
 * @property string|null $reason
 * @property string|null $toggled_by
 * @property Carbon|null $toggled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FeatureKillSwitch extends Model
{
    protected $table = 'feature_kill_switches';

    protected $fillable = [
        'feature_key',
        'is_active',
        'reason',
        'toggled_by',
        'toggled_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'toggled_at' => 'datetime',
        ];
    }
}
