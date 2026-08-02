<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Application\Actions;

use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Cabinet\Infrastructure\Services\CabinetService;

class UploadDocument
{
    public function __construct(
        private readonly CabinetService $cabinetService,
    ) {}

    public function handle(string $folderId, array $fileData, string $uploadedById): CabinetDocument
    {
        return $this->cabinetService->uploadDocument($folderId, $fileData, $uploadedById);
    }
}
