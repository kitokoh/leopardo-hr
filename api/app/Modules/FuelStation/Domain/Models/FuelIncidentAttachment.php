<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pièce jointe contrôlée d'un incident — FUEL-010, issue #5804.
 *
 * Mime/size validés au niveau Request (allowlist : images, PDF, textes —
 * aucune exécution possible). Le fichier est stocké en interne
 * (`storage_path`), jamais servi en clair sans contrôle d'accès tenant.
 *
 * @property int $id
 * @property string $company_id
 * @property int $incident_id
 * @property string $filename
 * @property string $storage_path
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelIncidentAttachment extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_incident_attachments';

    protected $fillable = [
        'company_id',
        'incident_id',
        'filename',
        'storage_path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'incident_id' => 'integer',
            'size_bytes' => 'integer',
        ];
    }
}
