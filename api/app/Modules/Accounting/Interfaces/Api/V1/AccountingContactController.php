<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Enums\ContactType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\StoreContactRequest;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\UpdateContactRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD des tiers de facturation (client/fournisseur) — issue #5222.
 *
 * RBAC (matrice comptabilité, COMPTABILITE_CONCEPTION.md §5) :
 *   - `comptable` : CRUD complet ;
 *   - `principal` : lecture + paramétrage (accès CRUD conservé en Phase A,
 *     la validation métier n'existant pas encore pour les contacts).
 *   Les routes portent le middleware `api.manager:comptable,principal`.
 *
 * Isolation tenant : `AccountingContact` utilise le trait `BelongsToCompany`
 * (scope global + auto-remplissage `company_id`, fail-closed #3727). Un
 * contact d'un autre tenant est donc introuvable (404), jamais visible.
 */
class AccountingContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:'.implode(',', ContactType::values())],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = AccountingContact::query()
            ->withCount('documents')
            ->orderBy('name')
            ->orderBy('id');

        if (! empty($validated['type'])) {
            $query->where('type', (string) $validated['type']);
        }

        if (! empty($validated['search'])) {
            $needle = '%'.addcslashes((string) $validated['search'], '%_\\').'%';
            $query->where(function ($builder) use ($needle): void {
                $builder->where('name', 'like', $needle)
                    ->orWhere('email', 'like', $needle);
            });
        }

        $paginator = $query->paginate($perPage);

        $items = [];
        foreach ($paginator->items() as $contact) {
            // Le paginateur est typé sur le modèle : le garde instanceof était
            // toujours vrai (PHPStan Strict — regression main 2026-08-23).
            $items[] = $this->serialize($contact);
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = AccountingContact::query()->create($this->contactPayload($request->validated()));

        return response()->json([
            'data' => $this->serialize($contact),
        ], 201);
    }

    public function show(Request $request, AccountingContact $contact): JsonResponse
    {
        return response()->json([
            'data' => $this->serialize($contact),
        ]);
    }

    public function update(UpdateContactRequest $request, AccountingContact $contact): JsonResponse
    {
        $contact->update($this->contactPayload($request->validated()));

        return response()->json([
            'data' => $this->serialize($contact->refresh()),
        ]);
    }

    public function destroy(Request $request, AccountingContact $contact): JsonResponse
    {
        $contact->delete();

        return response()->json(null, 204);
    }

    /**
     * Filtre le payload validé sur les colonnes fillable du modèle.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function contactPayload(array $validated): array
    {
        $allowed = [
            'type',
            'name',
            'legal_name',
            'tax_id',
            'email',
            'phone',
            'address',
            'currency',
            'payment_terms',
            'language',
            'source',
            'marketing_lead_id',
            'metadata',
        ];

        return array_intersect_key($validated, array_flip($allowed));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AccountingContact $contact): array
    {
        return [
            'id' => $contact->id,
            'type' => $contact->type,
            'name' => $contact->name,
            'legal_name' => $contact->legal_name,
            'tax_id' => $contact->tax_id,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'address' => $contact->address,
            'currency' => $contact->currency,
            'payment_terms' => $contact->payment_terms,
            'language' => $contact->language,
            'source' => $contact->source,
            'marketing_lead_id' => $contact->marketing_lead_id,
            'metadata' => $contact->metadata,
            'documents_count' => $contact->documents_count ?? 0,
            'created_at' => $contact->created_at?->toISOString(),
            'updated_at' => $contact->updated_at?->toISOString(),
        ];
    }
}
