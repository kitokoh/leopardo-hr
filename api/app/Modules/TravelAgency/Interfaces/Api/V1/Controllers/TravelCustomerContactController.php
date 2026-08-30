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
 */
class TravelCustomerContactController extends Controller
{
    public function __construct(private readonly TravelManualNotificationAction $notify) {}

    /**
     * Liste des contacts voyageurs (gestion : rôles principal/rh/manager).
     * Expose les consentements par canal — nécessaire à l'UI admin
     * (TRAVEL-912/#6417).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        $contacts = TravelCustomerContact::query()
            ->where('company_id', $actor->company_id)
            ->when($request->has('search'), fn ($query) => $query->where('email', 'ilike', '%'.$request->query('search').'%'))
            ->orderByDesc('id')
            ->get()
            ->map(fn (TravelCustomerContact $c) => [
                'id' => $c->id,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'email' => $c->email,
                'phone' => $c->phone,
                'email_consent_given' => $c->email_consent_given,
                'sms_consent_given' => $c->sms_consent_given,
                'whatsapp_consent_given' => $c->whatsapp_consent_given,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $contacts]);
    }

    /**
     * Mise à jour des consentements par canal (gestion). Horodatage
     * conservé à l'opt-in ; un opt-out n'efface pas l'historique (RGPD).
     */
    public function updateConsent(UpdateTravelContactConsentRequest $request, TravelCustomerContact $travelCustomerContact): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($travelCustomerContact->company_id !== $actor->company_id || ! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(404);
        }

        $now = now();

        foreach (['email', 'sms', 'whatsapp'] as $channel) {
            if (! $request->has($channel.'_consent')) {
                continue;
            }
            $given = (bool) $request->validated($channel.'_consent');
            $travelCustomerContact->forceFill([$channel.'_consent_given' => $given]);
            if ($given) {
                $travelCustomerContact->forceFill([$channel.'_consent_at' => $travelCustomerContact->{$channel.'_consent_at'} ?? $now]);
            }
        }

        $travelCustomerContact->save();

        return response()->json(['data' => ['id' => $travelCustomerContact->id]]);
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
