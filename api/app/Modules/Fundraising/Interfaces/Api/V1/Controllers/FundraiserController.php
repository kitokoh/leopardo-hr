<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Fundraising\Application\Actions\ConfirmManualContributionAction;
use App\Modules\Fundraising\Application\Actions\CreateFundraiserAction;
use App\Modules\Fundraising\Application\Actions\TransitionFundraiserAction;
use App\Modules\Fundraising\Application\Actions\UpdateFundraiserAction;
use App\Modules\Fundraising\Domain\Enums\FundraiserStatus;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraisingContribution;
use App\Modules\Fundraising\Interfaces\Api\V1\Requests\StoreFundraiserRequest;
use App\Modules\Fundraising\Interfaces\Api\V1\Requests\UpdateFundraiserRequest;
use App\Modules\Fundraising\Interfaces\Api\V1\Resources\ContributionResource;
use App\Modules\Fundraising\Interfaces\Api\V1\Resources\FundraiserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des cagnottes du tenant (verticale FUNDRAISING — spec §5.2).
 *
 * RBAC : groupe `api.manager:principal,rh` + FundraiserPolicy (gestion
 * réservée au responsable du tenant ; lecture aux membres). Verbes
 * convention #4930. Feature flag : middleware `module.fundraising`
 * (fail-closed) sur le groupe de routes.
 */
final class FundraiserController extends Controller
{
    public function __construct(
        private readonly CreateFundraiserAction $createFundraiser,
        private readonly UpdateFundraiserAction $updateFundraiser,
        private readonly TransitionFundraiserAction $transitions,
        private readonly ConfirmManualContributionAction $confirmManual,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Fundraiser::query()->latest('id');

        $status = $request->query('status');
        if (is_string($status) && $status !== '' && FundraiserStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        return FundraiserResource::collection(
            $query->paginate(min(50, max(1, (int) $request->query('per_page', 15))))
        )->response();
    }

    public function store(StoreFundraiserRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', Fundraiser::class)) {
            abort(403);
        }

        $fundraiser = $this->createFundraiser->execute(
            $request->validated(),
            is_numeric($actor->id ?? null) ? (int) $actor->id : null,
        );

        return (new FundraiserResource($fundraiser))->response()->setStatusCode(201);
    }

    public function show(Request $request, Fundraiser $fundraiser): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->ownTenantOr404($actor, $fundraiser->company_id);

        if ($actor->cannot('view', $fundraiser)) {
            abort(403);
        }

        return (new FundraiserResource($fundraiser))->response();
    }

    public function update(UpdateFundraiserRequest $request, Fundraiser $fundraiser): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->ownTenantOr404($actor, $fundraiser->company_id);

        if ($actor->cannot('update', $fundraiser)) {
            abort(403);
        }

        return (new FundraiserResource(
            $this->updateFundraiser->execute($fundraiser, $request->validated())
        ))->response();
    }

    public function publish(Request $request, Fundraiser $fundraiser): JsonResponse
    {
        return $this->transition($request, $fundraiser, 'publish');
    }

    public function pause(Request $request, Fundraiser $fundraiser): JsonResponse
    {
        return $this->transition($request, $fundraiser, 'pause');
    }

    public function close(Request $request, Fundraiser $fundraiser): JsonResponse
    {
        return $this->transition($request, $fundraiser, 'close');
    }

    public function cancel(Request $request, Fundraiser $fundraiser): JsonResponse
    {
        return $this->transition($request, $fundraiser, 'cancel');
    }

    public function contributions(Request $request, Fundraiser $fundraiser): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->ownTenantOr404($actor, $fundraiser->company_id);

        if ($actor->cannot('view', $fundraiser)) {
            abort(403);
        }

        $query = FundraisingContribution::query()
            ->where('fundraiser_id', $fundraiser->id)
            ->latest('id');

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        return ContributionResource::collection(
            $query->paginate(min(50, max(1, (int) $request->query('per_page', 15))))
        )->response();
    }

    /**
     * Confirmation d'une contribution manuelle (espèces/virement constaté).
     */
    public function confirmContribution(Request $request, FundraisingContribution $contribution): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->ownTenantOr404($actor, $contribution->company_id);

        /** @var Fundraiser|null $fundraiser */
        $fundraiser = Fundraiser::query()->find($contribution->fundraiser_id);

        if (! $fundraiser instanceof Fundraiser) {
            throw FundraisingException::fundraiserNotFound();
        }

        if ($actor->cannot('update', $fundraiser)) {
            abort(403);
        }

        return (new ContributionResource(
            $this->confirmManual->execute($contribution)
        ))->response();
    }

    private function transition(Request $request, Fundraiser $fundraiser, string $verb): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->ownTenantOr404($actor, $fundraiser->company_id);

        if ($actor->cannot('publish', $fundraiser)) {
            abort(403);
        }

        $fundraiser = match ($verb) {
            'publish' => $this->transitions->publish($fundraiser),
            'pause' => $this->transitions->pause($fundraiser),
            'close' => $this->transitions->close($fundraiser),
            'cancel' => $this->transitions->cancel($fundraiser),
            default => $fundraiser,
        };

        return (new FundraiserResource($fundraiser))->response();
    }

    /**
     * Garde cross-tenant — 404, convention plateforme (pattern
     * ShowcaseSectionController) : SubstituteBindings s'exécute AVANT le
     * middleware `tenant`, donc le scope global BelongsToCompany est inactif
     * lors de la résolution implicite du modèle lié à la route. Sans cette
     * re-vérification, une ressource d'un AUTRE tenant serait adressable et
     * la policy répondrait 403 — révélant son existence. Ici : 404 (la
     * cagnotte « n'existe pas » pour ce tenant) ; le 403 reste réservé au
     * refus de rôle à l'intérieur du tenant.
     *
     * @param  string|int|null  $resourceCompanyId  company_id de la ressource liée
     */
    private function ownTenantOr404(Employee $actor, string|int|null $resourceCompanyId): void
    {
        if ((string) $resourceCompanyId !== (string) $actor->company_id) {
            abort(404);
        }
    }
}
