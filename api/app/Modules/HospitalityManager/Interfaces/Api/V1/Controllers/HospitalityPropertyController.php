<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\StoreHospitalityPropertyRequest;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\UpdateHospitalityPropertyRequest;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\BoundsPagination;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\ChecksHospitalitySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * API des établissements hôteliers / locatifs — HOSP-002 (#7944).
 *
 * CRUD + publication de la vitrine publique (`publish` génère le slug
 * public unique GLOBAL au passage `is_public=true`, `unpublish` le
 * conserve pour garder l'URL stable à la re-publication).
 */
class HospitalityPropertyController extends Controller
{
    use BoundsPagination;
    use ChecksHospitalitySolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HospitalityProperty::class);

        $query = HospitalityProperty::query()->where('company_id', $actor->company_id);

        // RBAC ressource-scopé (HOSP-003 #7945) : la liste est bornée aux
        // établissements accessibles en LECTURE — `null` = aucune
        // restriction (principal, rh en lecture, ou type pas encore assigné
        // → comportement historique).
        $accessible = $actor->accessibleResourceIds(
            'hospitality_property',
            \App\Core\Tenant\Domain\Models\EmployeeResourceAssignment::LEVEL_VIEW
        );
        if ($accessible !== null) {
            $query->whereIn('id', $accessible);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $properties = $query->orderBy('name')->paginate($this->boundedPerPage($request, 15));

        return response()->json([
            'data' => collect($properties->items())->map(fn (HospitalityProperty $property): array => $this->payload($property)),
            'meta' => [
                'current_page' => $properties->currentPage(),
                'per_page' => $properties->perPage(),
                'total' => $properties->total(),
            ],
        ]);
    }

    public function store(StoreHospitalityPropertyRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HospitalityProperty::class);

        /** @var HospitalityProperty $property */
        $property = HospitalityProperty::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
        ]));

        return response()->json(['data' => $this->payload($property->refresh())], 201);
    }

    public function show(Request $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->authorize('view', $property);

        return response()->json(['data' => $this->payload($property)]);
    }

    public function update(UpdateHospitalityPropertyRequest $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->authorize('update', $property);

        $property->update($request->validated());

        return response()->json(['data' => $this->payload($property->refresh())]);
    }

    public function destroy(Request $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->authorize('delete', $property);

        // Suppression bloquée tant que des types de chambres ou des unités
        // sont rattachés à l'établissement (même invariant que le référentiel
        // HealthManager — suppression en cascade silencieuse interdite).
        if ($property->roomTypes()->exists() || $property->units()->exists()) {
            abort(422, 'HOSPITALITY_PROPERTY_IN_USE');
        }

        $property->delete();

        return response()->json(null, 204);
    }

    public function publish(Request $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->authorize('publish', $property);

        if (! $property->is_public) {
            $property->is_public = true;
        }

        if ($property->public_slug === null) {
            $property->public_slug = $this->generateUniqueSlug($property);
        }

        $property->save();

        return response()->json(['data' => $this->payload($property->refresh())]);
    }

    public function unpublish(Request $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->authorize('publish', $property);

        // Le slug est conservé : une re-publication garde la même URL
        // (référencement, liens déjà diffusés).
        $property->is_public = false;
        $property->save();

        return response()->json(['data' => $this->payload($property->refresh())]);
    }

    /**
     * Slug public unique GLOBAL (tous tenants confondus) : base lisible
     * dérivée du nom + suffixe aléatoire, re-tiré jusqu'à unicité.
     */
    private function generateUniqueSlug(HospitalityProperty $property): string
    {
        $base = Str::slug($property->name) ?: 'etablissement';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (HospitalityProperty::query()
            ->withoutGlobalScopes()
            ->where('public_slug', $slug)
            ->exists());

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HospitalityProperty $property): array
    {
        return [
            'id' => (int) $property->getAttribute('id'),
            'name' => $property->name,
            'code' => $property->code,
            'type' => $property->type,
            'address' => $property->address,
            'city' => $property->city,
            'country' => $property->country,
            'timezone' => $property->timezone,
            'currency' => $property->currency,
            'phone' => $property->phone,
            'email' => $property->email,
            'star_rating' => $property->star_rating,
            'amenities' => $property->amenities,
            'latitude' => $property->latitude,
            'longitude' => $property->longitude,
            'status' => $property->status,
            'is_public' => (bool) $property->is_public,
            'public_slug' => $property->public_slug,
            'public_url' => $property->is_public && $property->public_slug !== null
                ? '/stay/'.$property->public_slug
                : null,
        ];
    }
}
