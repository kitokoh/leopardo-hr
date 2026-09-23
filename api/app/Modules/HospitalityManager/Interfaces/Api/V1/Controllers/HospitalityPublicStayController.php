<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityReservation;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRoomType;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityPublicPropertyResolver;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityReservationService;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\StoreHospitalityPublicReservationRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * HOSP-006 (#7948, spec §6) — vitrine publique `/stay` : surface SANS auth
 * `/public/hospitality/*` (throttle dédié `hospitality-public`).
 *
 *  - GET    /public/hospitality/properties/{slug}                    → fiche
 *  - GET    /public/hospitality/properties/{slug}/availability       → dispos
 *  - POST   /public/hospitality/properties/{slug}/reservations       → réserver
 *  - GET    /public/hospitality/reservations/{reference}?code=       → suivi
 *  - POST   /public/hospitality/reservations/{reference}/cancel      → annulation
 *
 * Garde-fous : résolution fail-closed par `HospitalityPublicPropertyResolver`
 * (établissement publié + actif + société saine + verticale activée → 404
 * uniforme) ; montant calculé SERVEUR ; tracking_code hashé (jamais stocké
 * en clair, présenté une seule fois) ; AUCUNE PII au-delà du nom du
 * voyageur dans le suivi (pattern RestaurantPublicSlugOrder RESTO-902).
 */
class HospitalityPublicStayController extends Controller
{
    public function __construct(
        private readonly HospitalityPublicPropertyResolver $resolver,
        private readonly HospitalityReservationService $reservations,
    ) {}

    /**
     * Fiche publique d'un établissement publié (consommée en SSR par
     * `/stay/{slug}` — HOSP-008 #7950).
     */
    public function show(string $slug): JsonResponse
    {
        return $this->resolver->within($slug, function (HospitalityProperty $property): JsonResponse {
            $roomTypes = HospitalityRoomType::query()
                ->where('property_id', $property->getKey())
                ->where('status', HospitalityRoomType::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'description', 'base_price_minor', 'currency'])
                ->map(fn (HospitalityRoomType $roomType): array => [
                    'id' => (int) $roomType->getKey(),
                    'code' => $roomType->code,
                    'name' => $roomType->name,
                    'description' => $roomType->description,
                    'base_price_minor' => $roomType->base_price_minor,
                    'currency' => $roomType->currency,
                ])
                ->values()
                ->all();

            // DTO public strict : aucun ID interne, aucune donnée tenant.
            return response()->json(['data' => [
                'slug' => $property->public_slug,
                'name' => $property->name,
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
                'room_types' => $roomTypes,
            ]]);
        });
    }

    /**
     * Disponibilités publiques par type de chambre sur [from, to) — même
     * calcul que l'endpoint interne HOSP-004 (capacité − immobilisations,
     * pending expirées exclues).
     */
    public function availability(Request $request, string $slug): JsonResponse
    {
        /** @var array{from: string, to: string, adults?: int} $validated */
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after:from'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);

        return $this->resolver->within(
            $slug,
            fn (HospitalityProperty $property): JsonResponse => response()->json([
                'data' => $this->reservations->availability(
                    (string) $property->company_id,
                    (int) $property->getKey(),
                    $from,
                    $to
                ),
            ])
        );
    }

    /**
     * Réservation en ligne : idempotente, lock d'inventaire, `pending` +
     * `expires_at=+30 min`, montant SERVEUR. 201 à la création (avec
     * tracking_code, une seule fois), 200 au rejeu (sans le code).
     */
    public function store(StoreHospitalityPublicReservationRequest $request, string $slug): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return $this->resolver->within($slug, function (HospitalityProperty $property) use ($validated): JsonResponse {
            $roomTypeId = (int) $validated['room_type_id'];

            // Le type demandé doit appartenir à CET établissement et être
            // actif — sinon 422 (jamais une réservation sur l'inventaire
            // d'un autre établissement du même tenant).
            $roomTypeBelongsToProperty = HospitalityRoomType::query()
                ->where('property_id', $property->getKey())
                ->whereKey($roomTypeId)
                ->where('status', HospitalityRoomType::STATUS_ACTIVE)
                ->exists();

            if (! $roomTypeBelongsToProperty) {
                throw ValidationException::withMessages([
                    'room_type_id' => ['Ce type de chambre n\'est pas disponible pour cet établissement.'],
                ]);
            }

            $result = $this->reservations->createOnlineReservation((string) $property->company_id, [
                'property_id' => (int) $property->getKey(),
                'room_type_id' => $roomTypeId,
                'guest_name' => $validated['guest_name'],
                'contact_email' => $validated['contact_email'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'check_in' => $validated['check_in'],
                'check_out' => $validated['check_out'],
                'adults' => $validated['adults'] ?? 1,
                'children' => $validated['children'] ?? 0,
                'idempotency_key' => $validated['idempotency_key'] ?? null,
            ]);

            /** @var HospitalityReservation $reservation */
            $reservation = $result['reservation'];

            $data = [
                'reference' => $reservation->reference,
                'status' => $reservation->status,
                'check_in' => $reservation->check_in->toDateString(),
                'check_out' => $reservation->check_out->toDateString(),
                'expires_at' => $reservation->expires_at?->toIso8601String(),
                'total_amount_minor' => $reservation->total_amount_minor,
                'currency' => $reservation->currency,
                'created' => $result['created'],
            ];

            // Le code de suivi n'est présenté QU'à la création (jamais au
            // rejeu : le serveur n'en stocke que le hash).
            if ($result['created'] && $result['tracking_code'] !== null) {
                $data['tracking_code'] = $result['tracking_code'];
            }

            return response()->json(['data' => $data], $result['created'] ? 201 : 200);
        });
    }

    /**
     * Suivi sans compte : référence + code de suivi (hash comparé
     * timing-safe). 404 uniforme anti-énumération.
     */
    public function track(Request $request, string $reference): JsonResponse
    {
        $code = $request->query('code', '');
        $code = is_string($code) ? $code : '';

        return $this->resolver->withinReservation(
            $reference,
            $code,
            fn (HospitalityReservation $reservation): JsonResponse => response()->json([
                'data' => $this->trackingDto($reservation),
            ])
        );
    }

    /**
     * Annulation par le voyageur porteur du code : pending | confirmed →
     * cancelled (la machine à états refuse le reste en 409
     * INVALID_RESERVATION_TRANSITION). Libère l'inventaire (le statut
     * terminal sort la réservation du comptage de disponibilité).
     */
    public function cancel(Request $request, string $reference): JsonResponse
    {
        $code = $request->input('code', '');
        $code = is_string($code) ? $code : '';

        return $this->resolver->withinReservation($reference, $code, function (HospitalityReservation $reservation): JsonResponse {
            $cancelled = $this->reservations->transition($reservation, HospitalityReservation::STATUS_CANCELLED);

            return response()->json(['data' => [
                'reference' => $cancelled->reference,
                'status' => $cancelled->status,
            ]]);
        });
    }

    /**
     * DTO de suivi public : strict minimum — AUCUNE PII au-delà du nom du
     * voyageur porteur du code (ni email, ni téléphone, ni IDs internes).
     *
     * @return array<string, mixed>
     */
    private function trackingDto(HospitalityReservation $reservation): array
    {
        $reservation->loadMissing(['property', 'roomType']);

        return [
            'reference' => $reservation->reference,
            'status' => $reservation->status,
            'guest_name' => $reservation->guest_name,
            'check_in' => $reservation->check_in->toDateString(),
            'check_out' => $reservation->check_out->toDateString(),
            'expires_at' => $reservation->expires_at?->toIso8601String(),
            'total_amount_minor' => $reservation->total_amount_minor,
            'currency' => $reservation->currency,
            'property' => [
                'slug' => $reservation->property?->public_slug,
                'name' => $reservation->property?->name,
            ],
            'room_type' => [
                'code' => $reservation->roomType?->code,
                'name' => $reservation->roomType?->name,
            ],
        ];
    }
}
