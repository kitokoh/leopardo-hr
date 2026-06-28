<?php

namespace App\Modules\Cabinet\Application\Actions;

use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Cabinet\Domain\Models\CabinetShare;
use App\Modules\Cabinet\Infrastructure\Services\CabinetService;

class ShareDocument
{
    public function __construct(
        private readonly CabinetService $cabinetService,
    ) {}

    public function handle(string $documentId, string $sharedWithId, string $permission = 'view'): CabinetShare
    {
        $document = CabinetDocument::query()->findOrFail($documentId);

        return $this->cabinetService->shareDocument($document, $sharedWithId, $permission);
    }
}
