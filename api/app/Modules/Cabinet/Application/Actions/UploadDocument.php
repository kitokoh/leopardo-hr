<?php

namespace App\Modules\Cabinet\Application\Actions;

use App\Models\CabinetDocument;
use App\Models\Employee;
use App\Modules\Cabinet\Infrastructure\Services\CabinetService;
use Illuminate\Http\UploadedFile;

class UploadDocument
{
    public function __construct(
        private readonly CabinetService $cabinetService,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Employee $owner, UploadedFile $file, array $data): CabinetDocument
    {
        return $this->cabinetService->uploadDocument($owner, $file, $data);
    }
}
