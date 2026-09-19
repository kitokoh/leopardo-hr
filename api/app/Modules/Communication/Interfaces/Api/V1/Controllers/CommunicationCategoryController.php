<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Domain\Models\CommunicationCategory;
use App\Modules\Communication\Infrastructure\Services\CommunicationTaxonomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Taxonomie email du tenant (BC-29 COMMUNICATION, R3 #7688, spec §3.3) —
 * categories PARAMETRABLES par tenant, defauts i18n FR/EN/AR/TR
 * materialises paresseusement (`CommunicationTaxonomyService`).
 *
 * Lecture : tout employe du tenant (les categories s'affichent sur ses
 * messages). Ecriture : managers principal/rh uniquement
 * (`CommunicationCategoryPolicy`). Les categories systeme se renomment ou
 * se desactivent mais ne se suppriment pas.
 */
class CommunicationCategoryController extends Controller
{
    public function __construct(private readonly CommunicationTaxonomyService $taxonomy) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommunicationCategory::class);

        /** @var Employee $employee */
        $employee = $request->user();

        $categories = $this->taxonomy->allCategories((string) $employee->company_id);

        return new JsonResponse([
            'data' => array_map(
                fn (CommunicationCategory $category): array => $this->present($category),
                $categories,
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CommunicationCategory::class);

        /** @var Employee $employee */
        $employee = $request->user();

        /** @var array{key: string, label?: string|null} $validated */
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]{1,63}$/'],
            'label' => ['nullable', 'string', 'max:128'],
        ]);

        $exists = CommunicationCategory::query()
            ->where('key', $validated['key'])
            ->exists();

        if ($exists) {
            return new JsonResponse([
                'message' => __('communication.category_key_taken'),
                'code' => 'CATEGORY_KEY_TAKEN',
            ], 422);
        }

        $category = new CommunicationCategory;
        $category->forceFill([
            'company_id' => (string) $employee->company_id,
            'key' => $validated['key'],
            'label' => $validated['label'] ?? null,
            'is_system' => false,
            'active' => true,
        ]);
        $category->save();

        return new JsonResponse(['data' => $this->present($category)], 201);
    }

    public function update(Request $request, CommunicationCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        /** @var array{label?: string|null, active?: bool} $validated */
        $validated = $request->validate([
            'label' => ['sometimes', 'nullable', 'string', 'max:128'],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('label', $validated)) {
            $category->label = $validated['label'];
        }

        if (array_key_exists('active', $validated)) {
            $category->active = (bool) $validated['active'];
        }

        $category->save();

        return new JsonResponse(['data' => $this->present($category)]);
    }

    public function destroy(CommunicationCategory $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CommunicationCategory $category): array
    {
        return [
            'id' => $category->id,
            'key' => $category->key,
            'label' => $category->displayLabel(),
            'custom_label' => $category->label,
            'is_system' => $category->is_system,
            'active' => $category->active,
        ];
    }
}
