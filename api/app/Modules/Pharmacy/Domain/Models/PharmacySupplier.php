<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Fournisseur d'officine — PHARMA-004 (#7801).
 *
 * Grossiste-répartiteur, laboratoire ou autre. Jamais supprimé (référencé
 * par les commandes et les lots) : archivage via `status`.
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $name
 * @property string $type
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PharmacySupplier extends Model
{
    use BelongsToCompany;

    public const TYPES = ['wholesaler', 'laboratory', 'other'];

    public const STATUSES = ['active', 'archived'];

    protected $table = 'pharmacy_suppliers';

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'contact_name',
        'phone',
        'email',
        'address',
        'status',
    ];
}
