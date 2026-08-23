<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Contracts;

use App\Modules\Accounting\Domain\Enums\DocumentType;

/**
 * Génère le numéro de document selon la série paramétrable de l'entreprise
 * (AccountingSettings.number_series) — COMPTABILITE_CONCEPTION.md §4.
 * Implémentation dans l'issue #5223 (workflow documents + numérotation).
 */
interface DocumentNumberingInterface
{
    public function nextNumber(string $companyId, DocumentType $type): string;
}
