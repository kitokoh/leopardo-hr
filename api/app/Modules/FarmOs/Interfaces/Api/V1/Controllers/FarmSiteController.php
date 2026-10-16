<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\AreaSource;
use App\Modules\FarmOs\Domain\Enums\AreaUnit;
use App\Modules\FarmOs\Domain\Exceptions\FarmAreaMeasurementException;
use App\Modules\FarmOs\Domain\Models\FarmSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Farm OS — Exploitations / sites.
 */
final class FarmSiteController
{
    public function index(Request $request): JsonResponse
    {
        $sites = FarmSite::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => $sites->getCollection()->map(fn (FarmSite $site): array => $this->payload($site))->all(),
            'meta' => ['current_page' => $sites->currentPage(), 'last_page' => $sites->lastPage(), 'total' => $sites->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:40', 'alpha_dash'],
            'kind' => ['nullable', Rule::in(['production', 'building', 'storage', 'other'])],
            'country_code' => ['nullable', 'string', 'size:2'],
            'region' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:240'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'total_area_value' => ['nullable', 'numeric', 'min:0'],
            'total_area_unit' => ['nullable', Rule::in(array_map(static fn (AreaUnit $u): string => $u->value, AreaUnit::cases()))],
            'total_area_source' => ['nullable', Rule::in(array_map(static fn (AreaSource $s): string => $s->value, AreaSource::cases()))],
        ]);

        try {
            $source = isset($validated['total_area_source']) ? AreaSource::from($validated['total_area_source']) : null;

            // Une superficie « mesurée » n'est JAMAIS acceptée sans géométrie
            // (ADR-FARM-0003) — y compris à l'échelle du site.
            if ($source !== null && $source->requiresGeometry()) {
                throw FarmAreaMeasurementException::withoutGeometry();
            }
        } catch (FarmAreaMeasurementException $exception) {
            return response()->json(['error' => 'AREA_SOURCE_REQUIRES_GEOMETRY', 'message' => $exception->getMessage()], 422);
        }

        $site = FarmSite::query()->create(array_merge($validated, [
            'company_id' => $this->companyId(),
            'kind' => $validated['kind'] ?? 'production',
            'timezone' => $validated['timezone'] ?? config('app.timezone', 'UTC'),
            'total_area_source' => $validated['total_area_source'] ?? (isset($validated['total_area_value']) ? AreaSource::Declared->value : null),
        ]));

        return response()->json(['data' => $this->payload($site)], 201);
    }

    public function show(FarmSite $site): JsonResponse
    {
        return response()->json(['data' => $this->payload($site)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FarmSite $site): array
    {
        return [
            'id' => $site->id,
            'name' => $site->name,
            'reference' => $site->reference,
            'kind' => $site->kind,
            'country_code' => $site->country_code,
            'region' => $site->region,
            'timezone' => $site->timezone,
            'status' => $site->status,
            'total_area' => $site->total_area_value === null ? null : [
                'value' => $site->total_area_value,
                'unit' => $site->total_area_unit,
                // Provenance affichée telle quelle : Farm OS ne prétend jamais
                // mesurer une superficie saisie par l'exploitant.
                'source' => $site->total_area_source,
            ],
            'created_at' => $site->created_at?->toIso8601String(),
        ];
    }

    private function companyId(): string
    {
        /** @var Company $company */
        $company = currentCompany();

        return (string) $company->id;
    }
}
