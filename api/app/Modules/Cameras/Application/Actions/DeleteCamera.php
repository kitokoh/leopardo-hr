<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Cameras\Domain\Camera;
use App\Modules\Cameras\Infrastructure\Services\CameraService;

class DeleteCamera
{
    public function __construct(
        private readonly CameraService $cameras,
    ) {}

    public function execute(Camera $camera, Employee $actor): void
    {
        $this->cameras->softDelete($camera, $actor);
    }
}
