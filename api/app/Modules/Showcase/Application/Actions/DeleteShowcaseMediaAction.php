<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use App\Modules\Showcase\Infrastructure\Services\ShowcaseMediaService;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;

/**
 * BC-27 SHOWCASE (V-MEDIA #6872) — suppression d'un média.
 *
 * Supprime le binaire (disk existant) puis la ligne ; si le média était le
 * logo référencé par `settings.logo_id`, la référence est retirée (aucun id
 * orphelin). Le cache public est purgé si la vitrine est publiée ; la
 * suppression est journalisée (audit `showcase.media_deleted`).
 *
 * L'appartenance du média à la vitrine (et au tenant) est vérifiée par le
 * contrôleur avant appel (binding scopé + comparaison `showcase_id`).
 */
final class DeleteShowcaseMediaAction
{
    public function __construct(
        private readonly ShowcaseMediaService $mediaService,
        private readonly ShowcasePublicCache $publicCache,
    ) {}

    public function execute(CompanyShowcase $showcase, ShowcaseMedia $media, ?int $actorId = null): void
    {
        $kind = $media->kind->value;
        $sectionId = $media->section_id;
        $size = $media->size;

        $this->mediaService->delete($media);

        $this->unlinkLogoIfNeeded($showcase, $media);

        if ($showcase->status === CompanyShowcaseStatus::Published) {
            $this->publicCache->forget($showcase->slug);
        }

        AuditLog::create([
            'company_id' => $showcase->company_id,
            'user_id' => $actorId,
            'module' => 'showcase',
            'action' => 'showcase.media_deleted',
            'auditable_type' => ShowcaseMedia::class,
            'auditable_id' => $media->id,
            'old_values' => [
                'kind' => $kind,
                'section_id' => $sectionId,
                'size' => $size,
            ],
            'new_values' => [],
        ]);
    }

    private function unlinkLogoIfNeeded(CompanyShowcase $showcase, ShowcaseMedia $media): void
    {
        $settings = is_array($showcase->settings) ? $showcase->settings : [];
        $logoId = $settings['logo_id'] ?? null;

        if (! is_int($logoId) || $logoId !== $media->id) {
            return;
        }

        unset($settings['logo_id']);

        $showcase->settings = $settings;
        $showcase->save();
    }
}
