<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lookup public `share_token → company_id` des partages de documents (issue #5428).
 *
 * Table PUBLIQUE (pas tenant-scoped) : permet de résoudre un token de partage
 * en O(1) requête sans itérer toutes les entreprises actives
 * (`PublicDocumentShareController::resolveShare` était en O(N) tenants).
 * Ne porte AUCUNE donnée du document (RGPD) — uniquement le token + la
 * compagnie ; purgée avec le partage (accounting:purge-expired-shares, #5430).
 *
 * @property string $share_token
 * @property string $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 *
 * @mixin Builder<static>
 */
class DocumentShareLookup extends Model
{
    protected $table = 'document_share_lookup';

    public $incrementing = false;

    protected $primaryKey = 'share_token';

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'share_token',
        'company_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
