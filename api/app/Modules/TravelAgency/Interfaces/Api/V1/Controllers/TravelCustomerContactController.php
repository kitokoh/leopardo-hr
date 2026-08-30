<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\TravelManualNotificationAction;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\NotifyTravelContactRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelContactConsentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-910 (#6113) — Notifications manuelles (legacy gv-back) via les
 * canaux de la plateforme + consentement. Aucune table maison.
 *
 * TRAVEL-913 — Registre admin des contacts voyageurs : liste (avec
 * consentements horodatés par canal) et mise à jour du consentement
 * (opt-in / opt-out traçable RGPD). Règle d'or : AUCUNE notification sans
 * consentement explicite par canal (spéc §8.5).
 */
class TravelCustomerContactController extends Controller
{
    public function __construct(private readonly TravelManualNotificationAction $notify) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));
        $search = trim((string) $request->query('search'));

        $query = TravelCustomerContact::query()
            ->where('company_id', $actor->company_id);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('email', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        $contacts = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => $contacts->map(fn (TravelCustomerContact $c) => [
                'id' => $c->id,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'email' => $c->email,
                'phone' => $c->phone,
                'email_consent_given' => $c->email_consent_given,
                'email_consent_at' => $c->email_consent_at?->toIso8601String(),
                'sms_consent_given' => $c->sms_consent_given,
                'sms_consent_at' => $c->sms_consent_at?->toIso8601String(),
                'whatsapp_consent_given' => $c->whatsapp_consent_given,
                'whatsapp_consent_at' => $c->whatsapp_consent_at?->toIso8601String(),
                'created_at' => $c->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'total' => $contacts->total(),
            ],
        ]);
    }

    public function updateConsent(UpdateTravelContactConsentRequest $request, TravelCustomerContact $travelCustomerContact): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($travelCustomerContact->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        $channel = (string) $request->validated('channel');
        $given = $request->boolean('given');

        $columnGiven = "{$channel}_consent_given";
        $columnAt = "{$channel}_consent_at";

        $travelCustomerContact->forceFill([
            $columnGiven => $given,
            $columnAt => $given ? now() : null,
        ])->save();

        return response()->json(['data' => [
            'id' => $travelCustomerContact->id,
            'channel' => $channel,
            'given' => $given,
            'at' => $given ? $travelCustomerContact->fresh()->{$columnAt}?->toIso8601String() : null,
        ]]);
    }

    public function notify(NotifyTravelContactRequest $request, TravelCustomerContact $travelCustomerContact): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($travelCustomerContact->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        $result = $this->notify->execute(
            $travelCustomerContact,
            $actor,
            (string) $request->validated('message'),
            $request->validated('channels') ?? ['email'],
        );

        return response()->json(['data' => $result]);
    }
}
