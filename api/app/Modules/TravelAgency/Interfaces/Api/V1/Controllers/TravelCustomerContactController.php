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
 * TRAVEL-913 (#6421) — Lecture et gestion des consentements pour l'écran
 * admin contacts (liste + opt-in/opt-out horodaté par canal).
 */
class TravelCustomerContactController extends Controller
{
    public function __construct(private readonly TravelManualNotificationAction $notify) {}

    /**
     * TRAVEL-913 (#6421) — Liste admin des contacts voyageurs (tenant-scoped).
     * Réservé aux rôles gestion (principal/rh/manager) — cohérent avec notify().
     * Recherche `?search=` sur nom/email/téléphone ; consentements par canal
     * exposés avec leurs horodatages (nécessaire à l'UI admin, TRAVEL-912/#6417).
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
     * Contrat frontend (TRAVEL-914/#6427) : `POST /contacts/{id}/consent`
     * avec un booléen par canal (`{email,sms,whatsapp}_consent`).
     * Horodatage : `consent_at` = première capture de consentement (un
     * opt-out ne l'efface pas — historique conservé, traçabilité RGPD).
     */
    public function updateConsent(UpdateTravelContactConsentRequest $request, TravelCustomerContact $travelCustomerContact): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        if ($travelCustomerContact->company_id !== $actor->company_id) {
            abort(404);
        }

        $now = now();

        foreach (['email', 'sms', 'whatsapp'] as $channel) {
            if (! $request->has($channel.'_consent')) {
                continue;
            }

            $given = (bool) $request->validated($channel.'_consent');
            $givenField = $channel.'_consent_given';
            $atField = $channel.'_consent_at';

            $travelCustomerContact->forceFill([$givenField => $given]);

            if ($given && $travelCustomerContact->{$atField} === null) {
                $travelCustomerContact->forceFill([$atField => $now]);
            }
        }

        $travelCustomerContact->save();

        return response()->json(['data' => [
            'id' => $travelCustomerContact->id,
            'email_consent_given' => $travelCustomerContact->email_consent_given,
            'email_consent_at' => $travelCustomerContact->email_consent_at?->toIso8601String(),
            'sms_consent_given' => $travelCustomerContact->sms_consent_given,
            'sms_consent_at' => $travelCustomerContact->sms_consent_at?->toIso8601String(),
            'whatsapp_consent_given' => $travelCustomerContact->whatsapp_consent_given,
            'whatsapp_consent_at' => $travelCustomerContact->whatsapp_consent_at?->toIso8601String(),
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
