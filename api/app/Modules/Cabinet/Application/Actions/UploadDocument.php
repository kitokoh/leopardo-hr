<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Cabinet\Infrastructure\Services\CabinetService;
use Illuminate\Http\UploadedFile;

class UploadDocument
{
    public function __construct(
        private readonly CabinetService $cabinetService,
    ) {}

    public function execute(Employee $owner, UploadedFile $file, array $data): CabinetDocument
    {
        return $this->cabinetService->uploadDocument($owner, $file, $data);
    }
}
