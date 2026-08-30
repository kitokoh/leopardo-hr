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
<<<<<<< HEAD
 *
 * TRAVEL-913 (#6421) — Lecture et gestion des consentements pour l'écran
 * admin contacts (liste + opt-in/opt-out horodaté par canal).
=======
>>>>>>> origin/feat/travel-101-202-foundations
 */
class TravelCustomerContactController extends Controller
{
    public function __construct(private readonly TravelManualNotificationAction $notify) {}

    /**
<<<<<<< HEAD
     * TRAVEL-913 (#6421) — Liste admin des contacts voyageurs (tenant-scoped).
     * Réservé aux rôles gestion (principal/rh/manager) — cohérent avec notify().
=======
     * Liste des contacts voyageurs (gestion : rôles principal/rh/manager).
     * Expose les consentements par canal — nécessaire à l'UI admin
     * (TRAVEL-912/#6417).
>>>>>>> origin/feat/travel-101-202-foundations
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
<<<<<<< HEAD
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('email', 'ilike', '%'.$search.'%')
                        ->orWhere('first_name', 'ilike', '%'.$search.'%')
                        ->orWhere('last_name', 'ilike', '%'.$search.'%')
                        ->orWhere('phone', 'ilike', '%'.$search.'%');
                });
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => $contacts->map(fn (TravelCustomerContact $contact) => [
            'id' => $contact->id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'email_consent_given' => $contact->email_consent_given,
            'email_consent_at' => $contact->email_consent_at?->toIso8601String(),
            'sms_consent_given' => $contact->sms_consent_given,
            'sms_consent_at' => $contact->sms_consent_at?->toIso8601String(),
            'whatsapp_consent_given' => $contact->whatsapp_consent_given,
            'whatsapp_consent_at' => $contact->whatsapp_consent_at?->toIso8601String(),
            'created_at' => $contact->created_at?->toIso8601String(),
        ])]);
    }

    /**
     * TRAVEL-913 (#6421) — Opt-in/opt-out horodaté par canal.
     * Chaque canal fourni est mis à jour ; `consent_at` reflète le dernier
     * changement (traçabilité RGPD dans les deux sens).
=======
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
>>>>>>> origin/feat/travel-101-202-foundations
     */
    public function updateConsent(UpdateTravelContactConsentRequest $request, TravelCustomerContact $travelCustomerContact): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

<<<<<<< HEAD
        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        if ($travelCustomerContact->company_id !== $actor->company_id) {
            abort(404);
        }

        $changes = [
            'email_consent_given' => $request->validated('email_consent_given'),
            'sms_consent_given' => $request->validated('sms_consent_given'),
            'whatsapp_consent_given' => $request->validated('whatsapp_consent_given'),
        ];

        $fill = [];
        foreach ($changes as $channel => $value) {
            if ($value === null) {
                continue;
            }

            $fill[$channel] = (bool) $value;
            $fill[str_replace('_given', '_at', (string) $channel)] = now();
        }

        if ($fill !== []) {
            $travelCustomerContact->forceFill($fill)->save();
        }

        return response()->json(['data' => [
            'id' => $travelCustomerContact->id,
            'email_consent_given' => $travelCustomerContact->email_consent_given,
            'email_consent_at' => $travelCustomerContact->email_consent_at?->toIso8601String(),
            'sms_consent_given' => $travelCustomerContact->sms_consent_given,
            'sms_consent_at' => $travelCustomerContact->sms_consent_at?->toIso8601String(),
            'whatsapp_consent_given' => $travelCustomerContact->whatsapp_consent_given,
            'whatsapp_consent_at' => $travelCustomerContact->whatsapp_consent_at?->toIso8601String(),
        ]]);
=======
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
>>>>>>> origin/feat/travel-101-202-foundations
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
