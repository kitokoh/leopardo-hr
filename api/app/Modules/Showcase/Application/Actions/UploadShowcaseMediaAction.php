<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Enums\ShowcaseMediaKind;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use App\Modules\Showcase\Infrastructure\Services\ShowcaseMediaService;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * BC-27 SHOWCASE (V-MEDIA #6872) — upload d'un média de vitrine.
 *
 * Cas d'usage :
 *  - `kind = logo` : média d'identité de la vitrine — l'id stable est
 *    enregistré dans `settings.logo_id` (la vitrine est le lien, jamais un
 *    chemin absolu) ;
 *  - `kind = section` : média rattaché à une section EXISTANTE de la
 *    vitrine (`section_id` stable, vérifié dans le périmètre tenant).
 *
 * Le stockage, la sanitisation du nom et les gardes de type/poids/SVG sont
 * délégués à {@see ShowcaseMediaService} (Infrastructure, avec disk
 * existant). Le cache public est purgé si la vitrine est publiée ; l'upload
 * est journalisé (audit `showcase.media_uploaded`).
 */
final class UploadShowcaseMediaAction
{
    public function __construct(
        private readonly ShowcaseMediaService $mediaService,
        private readonly ShowcasePublicCache $publicCache,
    ) {}

    public function execute(
        CompanyShowcase $showcase,
        string $kindValue,
        UploadedFile $file,
        ?int $sectionId = null,
        ?int $actorId = null,
    ): ShowcaseMedia {
        $kind = ShowcaseMediaKind::tryFrom($kindValue);

        if (! $kind instanceof ShowcaseMediaKind) {
            throw ValidationException::withMessages([
                'kind' => __('showcase.media_kind_unknown', ['kinds' => implode(', ', ShowcaseMediaKind::values())]),
            ]);
        }

        $section = $this->resolveSection($showcase, $kind, $sectionId);

        $media = $this->mediaService->store($showcase, $kind, $file, $section);

        if ($kind === ShowcaseMediaKind::Logo) {
            $this->linkLogo($showcase, $media);
        }

        if ($showcase->status === CompanyShowcaseStatus::Published) {
            $this->publicCache->forget($showcase->slug);
        }

        AuditLog::create([
            'company_id' => $showcase->company_id,
            'user_id' => $actorId,
            'module' => 'showcase',
            'action' => 'showcase.media_uploaded',
            'auditable_type' => ShowcaseMedia::class,
            'auditable_id' => $media->id,
            'old_values' => [],
            'new_values' => [
                'kind' => $media->kind->value,
                'section_id' => $media->section_id,
                'size' => $media->size,
            ],
        ]);

        return $media;
    }

    /**
     * Enregistre l'id stable du logo dans les réglages de la vitrine.
     */
    private function linkLogo(CompanyShowcase $showcase, ShowcaseMedia $media): void
    {
        $settings = is_array($showcase->settings) ? $showcase->settings : [];
        $settings['logo_id'] = $media->id;

        $showcase->settings = $settings;
        $showcase->save();
    }

    /**
     * Vérifie que la cible du média est cohérente (logo sans section,
     * section existante de CETTE vitrine) — 422 sinon.
     */
    private function resolveSection(CompanyShowcase $showcase, ShowcaseMediaKind $kind, ?int $sectionId): ?CompanyShowcaseSection
    {
        if ($kind === ShowcaseMediaKind::Logo) {
            if ($sectionId !== null) {
                throw ValidationException::withMessages([
                    'section_id' => __('showcase.media_section_not_allowed'),
                ]);
            }

            return null;
        }

        if ($sectionId === null) {
            throw ValidationException::withMessages([
                'section_id' => __('showcase.media_section_required'),
            ]);
        }

        /** @var CompanyShowcaseSection|null $section */
        $section = CompanyShowcaseSection::query()
            ->where('showcase_id', $showcase->id)
            ->whereKey($sectionId)
            ->first();

        if (! $section instanceof CompanyShowcaseSection) {
            throw ValidationException::withMessages([
                'section_id' => __('showcase.media_section_unknown'),
            ]);
        }

        return $section;
    }
}
