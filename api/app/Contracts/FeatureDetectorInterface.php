<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface FeatureDetectorInterface
{
    /**
     * @return Collection<array>
     */
    public function detectNewFeatures(): Collection;

    /**
     * @return array<string, mixed>
     */
    public function extractMetadata(string $controllerClass, string $method): array;

    /**
     * @return Collection<array>
     */
    public function scanRoutes(): Collection;

    /**
     * @return Collection<array>
     */
    public function detectChanges(): Collection;
}
