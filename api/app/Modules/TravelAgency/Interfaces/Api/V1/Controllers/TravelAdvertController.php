<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use App\Modules\TravelAgency\Infrastructure\Services\TravelAdvertPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-905..908 (#6108..#6111) — Annonces : référentiels, tarifs, cycle
 * de vie (submit → paid → validated → published → expired|archived).
 *
 * Une annonce n'est VISIBLE (liste publique) qu'une fois payée ET validée
 * ET non expirée (critère d'acceptation). Prix calculé serveur en unités
 * mineures, devise du tenant.
 */
class TravelAdvertController extends Controller
{
    // ── Référentiels (types / positions) ────────────────────────────────────

    public function indexTypes(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json(['data' => TravelAdvertType::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get()]);
    }

    public function storeType(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:160'],
        ]);

        return response()->json(['data' => TravelAdvertType::query()->create(
            array_merge($data, ['company_id' => $actor->company_id]),
        )])->setStatusCode(201);
    }

    public function indexPositions(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json(['data' => TravelAdvertPosition::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get()]);
    }

    public function storePosition(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:160'],
        ]);

        return response()->json(['data' => TravelAdvertPosition::query()->create(
            array_merge($data, ['company_id' => $actor->company_id]),
        )])->setStatusCode(201);
    }

    // ── Tarifs ──────────────────────────────────────────────────────────────

    public function indexPrices(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json(['data' => TravelAdvertPrice::query()
            ->where('company_id', $actor->company_id)
            ->with(['type', 'position'])
            ->get()]);
    }

    public function storePrice(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'type_id' => ['required', 'integer', 'exists:travel_advert_types,id'],
            'position_id' => ['required', 'integer', 'exists:travel_advert_positions,id'],
            'price_per_image_minor' => ['sometimes', 'integer', 'min:0'],
            'price_per_character_minor' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        // Cohérence devise tenant (critère d'acceptation).
        $data['currency'] = $data['currency'] ?? $actor->company?->currency ?? 'XAF';

        return response()->json(['data' => TravelAdvertPrice::query()->create(
            array_merge($data, ['company_id' => $actor->company_id]),
        )])->setStatusCode(201);
    }

    // ── Annonces ────────────────────────────────────────────────────────────

    /**
     * Soumission d'une annonce : prix calculé SERVEUR (jamais du client).
     */
    public function submit(Request $request, TravelAdvertPricingService $pricing): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $data = $request->validate([
            'type_id' => ['required', 'integer', 'exists:travel_advert_types,id'],
            'position_id' => ['required', 'integer', 'exists:travel_advert_positions,id'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'image_path' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var TravelAdvertType $type */
        $type = TravelAdvertType::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($data['type_id']);

        $quote = $pricing->computePrice(
            companyId: $actor->company_id,
            type: $type,
            positionId: (int) $data['position_id'],
            characterCount: mb_strlen($data['body']),
            hasImage: ! empty($data['image_path']),
        );

        $advert = TravelAdvert::query()->create([
            'company_id' => $actor->company_id,
            'type_id' => $type->id,
            'position_id' => (int) $data['position_id'],
            'title' => $data['title'],
            'body_redacted' => $data['body'],
            'image_path' => $data['image_path'] ?? null,
            'character_count' => $quote['character_count'],
            'price_minor' => $quote['price_minor'],
            'currency' => $quote['currency'],
            'status' => 'submitted',
        ]);

        return response()->json(['data' => $this->payload($advert)])->setStatusCode(201);
    }

    /**
     * Paiement (cash guichet) : submitted → paid. Idempotent.
     */
    public function pay(Request $request, TravelAdvert $advert): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($advert->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! in_array($advert->status, ['submitted', 'paid'], true)) {
            abort(422, 'Cette annonce ne peut pas être payée (statut '.$advert->status.').');
        }

        if ($advert->status !== 'paid') {
            $advert->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();
        }

        return response()->json(['data' => $this->payload($advert->refresh())]);
    }

    /**
     * Validation par travel.manage : paid → validated (et publiée si payée).
     */
    public function validateAd(Request $request, TravelAdvert $advert): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($advert->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        if ($advert->status !== 'paid') {
            abort(422, 'Seule une annonce payée peut être validée.');
        }

        $days = (int) $request->validate(['valid_days' => ['sometimes', 'integer', 'min:1', 'max:90']])['valid_days'] ?? 30;

        $advert->forceFill([
            'status' => 'published',
            'validated_at' => now(),
            'validated_by_user_id' => $actor->id,
            'published_at' => now(),
            'valid_until' => now()->addDays($days),
        ])->save();

        return response()->json(['data' => $this->payload($advert->refresh())]);
    }

    /**
     * Annonces VISIBLES (payées + validées + non expirées) — lecture publique tenant.
     */
    public function indexVisible(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $adverts = TravelAdvert::query()
            ->where('company_id', $actor->company_id)
            ->where('status', 'published')
            ->where(function ($q): void {
                $q->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $adverts->map(fn (TravelAdvert $a): array => $this->payload($a))]);
    }

    /**
     * Toutes les annonces (gestion) — travel.manage.
     */
    public function indexManage(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $adverts = TravelAdvert::query()
            ->where('company_id', $actor->company_id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $adverts->map(fn (TravelAdvert $a): array => $this->payload($a))]);
    }

    /**
     * Renouvellement : nouvelle soumission payée (requalifie published).
     */
    public function renew(Request $request, TravelAdvert $advert): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($advert->company_id !== $actor->company_id) {
            abort(404);
        }

        $data = $request->validate(['valid_days' => ['sometimes', 'integer', 'min:1', 'max:90']]);
        $days = (int) ($data['valid_days'] ?? 30);

        if (! in_array($advert->status, ['published', 'expired'], true)) {
            abort(422, 'Seule une annonce publiée ou expirée peut être renouvelée.');
        }

        // Renouvellement = nouveau paiement + re-validation (requalifie).
        $advert->forceFill([
            'status' => 'published',
            'paid_at' => now(),
            'validated_at' => now(),
            'published_at' => now(),
            'valid_until' => now()->addDays($days),
        ])->save();

        return response()->json(['data' => $this->payload($advert->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TravelAdvert $advert): array
    {
        return [
            'id' => $advert->id,
            'type_id' => $advert->type_id,
            'position_id' => $advert->position_id,
            'title' => $advert->title,
            'body' => $advert->body_redacted,
            'character_count' => $advert->character_count,
            'price_minor' => $advert->price_minor,
            'currency' => $advert->currency,
            'status' => $advert->status,
            'paid_at' => $advert->paid_at?->toIso8601String(),
            'validated_at' => $advert->validated_at?->toIso8601String(),
            'published_at' => $advert->published_at?->toIso8601String(),
            'valid_until' => $advert->valid_until?->toIso8601String(),
            'moderation_note' => $advert->moderation_note,
        ];
    }

    private function denyUnlessManager(Employee $actor): void
    {
        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }
    }
}
