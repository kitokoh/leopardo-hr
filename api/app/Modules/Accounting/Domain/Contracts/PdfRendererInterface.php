<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Contracts;

use App\Modules\Accounting\Domain\Models\AccountingDocument;

/**
 * Rend un document comptable en PDF (fr + ar RTL, mentions légales) —
 * COMPTABILITE_CONCEPTION.md §4-5.
 * Implémentation dans l'issue #5224 (génération PDF multi-langues).
 */
interface PdfRendererInterface
{
    public function render(AccountingDocument $document, string $locale): string;
}
