<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Application\Actions\DeleteShowcaseMediaAction;
use App\Modules\Showcase\Application\Actions\ListShowcaseMediaAction;
use App\Modules\Showcase\Application\Actions\UploadShowcaseMediaAction;
use App\Modules\Showcase\Domain\Enums\ShowcaseMediaKind;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use App\Modules\Showcase\Interfaces\Api\V1\Requests\StoreShowcaseMediaRequest;
use App\Modules\Showcase\Interfaces\Api\V1\Resources\ShowcaseMediaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * BC-27 SHOWCASE (V-MEDIA #6872) — API privée des médias de la vitrine du
 * tenant (verbes convention #4930).
 *
 * - `GET /showcase/media` (filtres `kind`, `section_id`) ;
 * - `POST /showcase/media` (multipart : `kind`, `file`, `section_id`) ;
 * - `DELETE /showcase/media/{id}` (id stable, 404 cross-tenant par le
 *   binding scopé BelongsToCompany + comparaison `showcase_id`) ;
 * - RBAC `api.manager:principal,rh` + CompanyShowcasePolicy (`view` pour la
 *   liste, `update` pour l'upload/suppression) — le groupe de routes porte
 *   déjà `module.showcase` (fail-closed) et le throttle ;
 * - le DTO privé ne fuit ni `disk`, ni `path`, ni `company_id`.
 */
final class ShowcaseMediaController extends Controller
{
    public function __construct(
        private readonly ListShowcaseMediaAction $listMedia,
        private readonly UploadShowcaseMediaAction $uploadMedia,
        private readonly DeleteShowcaseMediaAction $deleteMedia,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail();
        $this->authorizeView($request, $showcase);

        $kind = $this->requestedKind($request);
        $sectionId = $this->requestedSectionId($request);

        $media = $this->listMedia->execute($showcase, $kind, $sectionId);

        return ShowcaseMediaResource::collection($media)->response();
    }

    public function store(StoreShowcaseMediaRequest $request): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail();
        $this->authorizeUpdate($request, $showcase);

        /** @var string $kind */
        $kind = $request->validated('kind');

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $sectionId = $request->validated('section_id');

        /** @var Employee $actor */
        $actor = $request->user();

        $media = $this->uploadMedia->execute(
            $showcase,
            $kind,
            $file,
            is_numeric($sectionId) ? (int) $sectionId : null,
            $actor->id,
        );

        return (new ShowcaseMediaResource($media))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function destroy(Request $request, ShowcaseMedia $media): Response
    {
        $showcase = $this->currentShowcaseOrFail();

        if ($media->showcase_id !== $showcase->id) {
            abort(404);
        }

        $this->authorizeUpdate($request, $showcase);

        /** @var Employee $actor */
        $actor = $request->user();

        $this->deleteMedia->execute($showcase, $media, $actor->id);

        return response()->noContent();
    }

    /**
     * Filtre `kind` optionnel — valeur inconnue → 422 (jamais de liste
     * silencieusement vide).
     */
    private function requestedKind(Request $request): ?ShowcaseMediaKind
    {
        $kind = $request->query('kind');

        if (! is_string($kind) || $kind === '') {
            return null;
        }

        $resolved = ShowcaseMediaKind::tryFrom($kind);

        if (! $resolved instanceof ShowcaseMediaKind) {
            throw ValidationException::withMessages([
                'kind' => __('showcase.media_kind_unknown', ['kinds' => implode(', ', ShowcaseMediaKind::values())]),
            ]);
        }

        return $resolved;
    }

    private function requestedSectionId(Request $request): ?int
    {
        $sectionId = $request->query('section_id');

        return is_numeric($sectionId) ? (int) $sectionId : null;
    }

    private function currentShowcaseOrFail(): CompanyShowcase
    {
        /** @var CompanyShowcase|null $showcase */
        $showcase = CompanyShowcase::query()->first();

        if (! $showcase instanceof CompanyShowcase) {
            abort(404);
        }

        return $showcase;
    }

    private function authorizeView(Request $request, CompanyShowcase $showcase): void
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('view', $showcase)) {
            abort(403);
        }
    }

    private function authorizeUpdate(Request $request, CompanyShowcase $showcase): void
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('update', $showcase)) {
            abort(403);
        }
    }
}
