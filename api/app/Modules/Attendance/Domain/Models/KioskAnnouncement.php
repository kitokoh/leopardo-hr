<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class KioskAnnouncement extends Model
{
    // Issue #7711 (suite #7646) — table `kiosk_announcements` du schéma partagé
    // shared_tenants. Le flux kiosque pré-tenant (#7651,
    // KioskController::announcements) n'est PAS impacté : il lit la table en
    // Query Builder brut (DB::table) avec filtre company_id explicite,
    // le scope Eloquent ne s'y applique pas.
    use BelongsToCompany;

    protected $fillable = [
        'title',
        'body',
        'priority',
        'is_active',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];
}

