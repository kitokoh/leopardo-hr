<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Paramétrage comptable par entreprise — COMPTABILITE_CONCEPTION.md §4.
 * Une seule ligne par entreprise (unique company_id).
 *
 * @property int $id
 * @property string|null $company_id
 * @property array<string, mixed>|null $number_series
 * @property array<int, mixed>|null $tva_rates
 * @property string|null $legal_mentions
 * @property string|null $template_style
 * @property string|null $currency
 * @property string|null $payment_terms
 * @property string $document_language
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AccountingSettings extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_settings';

    protected $fillable = [
        'company_id',
        'number_series',
        'tva_rates',
        'legal_mentions',
        'template_style',
        'currency',
        'payment_terms',
        'document_language',
    ];

    protected $casts = [
        'number_series' => 'array',
        'tva_rates' => 'array',
    ];
}
