<?php

namespace App\Modules\Cabinet\Application\Actions;

use App\Models\CabinetDocument;
use App\Models\CabinetShare;
use App\Models\Employee;
use App\Modules\Cabinet\Infrastructure\Services\CabinetService;

class ShareDocument
{
    public function __construct(
        private readonly CabinetService $cabinetService,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Employee $owner, int $documentId, array $data): CabinetShare
    {
        CabinetDocument::query()->findOrFail($documentId);

        return $this->cabinetService->share($owner, 'document', $documentId, $data);
    }
}
