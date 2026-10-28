<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\TravelManualNotificationAction;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\NotifyTravelContactRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelContactConsentChannelRequest;
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
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        // #7655 (tranche 3) : `?search[]=x` (tableau) était concaténé tel quel
        // dans les motifs `ilike` — PHP convertissait le tableau en "Array" et
        // la recherche filtrait sur une chaîne parasite au lieu d'être ignorée.
        // Seule une chaîne non vide est désormais un critère de recherche.
        $search = $request->query('search');
        $search = is_string($search) ? trim($search) : '';

        // TRAVEL-913 (#6421) : filtre par canal de consentement — `?consent=email`
        // ne doit remonter que les contacts ayant donne leur accord sur ce canal.
        $consent = $request->query('consent');
        $consent = is_string($consent) && in_array($consent, ['email', 'sms', 'whatsapp'], true)
            ? $consent
            : null;

        $perPage = max(1, min(200, (int) $request->query('per_page', 20)));

        $contacts = TravelCustomerContact::query()
            ->where('company_id', $actor->company_id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('email', 'ilike', '%'.$search.'%')
                        ->orWhere('first_name', 'ilike', '%'.$search.'%')
                        ->orWhere('last_name', 'ilike', '%'.$search.'%')
                        ->orWhere('phone', 'ilike', '%'.$search.'%');
                });
            })
            ->when($consent !== null, fn ($query) => $query->where("{$consent}_consent_given", true))
            ->orderByDesc('id');

        $paginator = $contacts->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (TravelCustomerContact $contact) => [
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
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * TRAVEL-913 (#6421) — Opt-in/opt-out horodaté par canal.
     * Chaque canal fourni est mis à jour ; `consent_at` reflète le dernier
     * changement (traçabilité RGPD dans les deux sens).
     */
    public function updateConsent(UpdateTravelContactConsentRequest $request, TravelCustomerContact $travelCustomerContact): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        if ($travelCustomerContact->company_id !== $actor->company_id) {
            abort(404);
        }

        $changes = $request->normalizedConsent();

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
    }

    public function notify(NotifyTravelContactRequest $request, TravelCustomerContact $travelCustomerContact): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($travelCustomerContact->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! $actor->hasManagerRole('principal', 'rh')) {
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

    public function updateConsentChannel(UpdateTravelContactConsentChannelRequest $request, TravelCustomerContact $travelCustomerContact): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($travelCustomerContact->company_id !== $actor->company_id) {
            abort(404);
        }

        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $channel = (string) $request->validated('channel');
        $given = $request->boolean('given');

        $travelCustomerContact->forceFill(["{$channel}_consent_given" => $given]);
        if ($given) {
            $travelCustomerContact->forceFill([
                "{$channel}_consent_at" => $travelCustomerContact->{"{$channel}_consent_at"} ?? now(),
            ]);
        }
        $travelCustomerContact->save();

        return response()->json(['data' => [
            'id' => $travelCustomerContact->id,
            'channel' => $channel,
            'given' => $given,
        ]]);
    }
}
