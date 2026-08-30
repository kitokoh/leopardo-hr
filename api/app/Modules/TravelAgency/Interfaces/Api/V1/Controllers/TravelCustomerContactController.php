<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\TravelManualNotificationAction;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\NotifyTravelContactRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelCustomerContactConsentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-910 (#6113) — Notifications manuelles (legacy gv-back) via les
 * canaux de la plateforme + consentement. Aucune table maison.
 *
 * TRAVEL-913/914 (#6426/#6427) — Registre des contacts voyageurs : liste
 * admin et mise à jour du consentement par canal (RGPD, registre #6067).
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

        $search = trim((string) $request->query('search', ''));

        $contacts = TravelCustomerContact::query()
            ->where('company_id', $actor->company_id)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('email', 'ilike', '%'.$search.'%')
                    ->orWhere('phone', 'ilike', '%'.$search.'%')
                    ->orWhere('first_name', 'ilike', '%'.$search.'%')
                    ->orWhere('last_name', 'ilike', '%'.$search.'%');
            }))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $contacts->map(fn (TravelCustomerContact $contact) => [
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
            ]),
        ]);
    }

    public function updateConsent(UpdateTravelCustomerContactConsentRequest $request, TravelCustomerContact $travelCustomerContact): JsonResponse
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
        $consent = (bool) $request->validated('consent');

        $travelCustomerContact->forceFill([
            $channel.'_consent_given' => $consent,
            $channel.'_consent_at' => $consent ? now() : null,
        ])->save();

        return response()->json([
            'data' => [
                'id' => $travelCustomerContact->id,
                'email' => $travelCustomerContact->email,
                'email_consent_given' => $travelCustomerContact->email_consent_given,
                'email_consent_at' => $travelCustomerContact->email_consent_at?->toIso8601String(),
                'sms_consent_given' => $travelCustomerContact->sms_consent_given,
                'sms_consent_at' => $travelCustomerContact->sms_consent_at?->toIso8601String(),
                'whatsapp_consent_given' => $travelCustomerContact->whatsapp_consent_given,
                'whatsapp_consent_at' => $travelCustomerContact->whatsapp_consent_at?->toIso8601String(),
            ],
        ]);
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
