<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface FeatureDetectorInterface
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectNewFeatures(): Collection;

    /**
     * @return array<string, mixed>
     */
    public function extractMetadata(string $controllerClass, string $method): array;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function scanRoutes(): Collection;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectChanges(): Collection;
}
