<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Cameras\Domain\Camera;
use App\Modules\Cameras\Infrastructure\Services\CameraService;

class CreateCamera
{
    public function __construct(
        private readonly CameraService $cameras,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Company $company, Employee $actor, array $data): Camera
    {
        return $this->cameras->create($company, $actor, $data);
    }
}
