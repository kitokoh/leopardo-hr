<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Contracts;

use App\Modules\Billing\Domain\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvoiceRepositoryInterface
{
    public function findById(int $id): ?Invoice;

    /** @return LengthAwarePaginator<int, Invoice> */
    public function paginateByCompany(int $companyId, int $perPage = 15): LengthAwarePaginator;

    public function save(Invoice $invoice): Invoice;
}
