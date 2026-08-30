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
 * TRAVEL-913 (#6425/#6421) — Registre admin des contacts voyageurs : liste
 * paginée (recherche email/nom/téléphone, filtre par consentement,
 * horodatages RGPD par canal) et gestion du consentement par canal
 * (opt-in / opt-out horodaté, historique conservé) — via `PATCH
 * {channel, given}` (par canal), `PUT` (bulk, rétrocompat) ou `POST`
 * (bulk, rétrocompat UI). Rôle principal/rh/manager requis, cross-tenant
 * → 404.
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

        $query = TravelCustomerContact::query()
            ->where('company_id', $actor->company_id);

        $search = trim((string) $request->query('search'));
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('email', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        $consent = (string) $request->query('consent');
        if (in_array($consent, ['email', 'sms', 'whatsapp'], true)) {
            $query->where("{$consent}_consent_given", true);
        }

        $contacts = $query->orderByDesc('created_at')->paginate($perPage);

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

    /**
     * Opt-in / opt-out horodaté d'UN canal (TRAVEL-913/#6425).
     * L'opt-in conserve l'horodatage existant ; l'opt-out ne l'efface pas
     * (historique conservé — RGPD).
     */
    public function updateConsentChannel(UpdateTravelContactConsentChannelRequest $request, TravelCustomerContact $travelCustomerContact): JsonResponse
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

    /**
     * Mise à jour bulk des consentements (rétrocompat UI — lot UI #6435).
     * Chaque canal fourni est mis à jour ; l'opt-in conserve l'horodatage
     * existant, l'opt-out ne l'efface pas (historique RGPD conservé).
     */
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

        $now = now();

        foreach (['email', 'sms', 'whatsapp'] as $channel) {
            if (! $request->has($channel.'_consent')) {
                continue;
            }
            $given = $request->boolean($channel.'_consent');
            $travelCustomerContact->forceFill(["{$channel}_consent_given" => $given]);
            if ($given) {
                $travelCustomerContact->forceFill([
                    "{$channel}_consent_at" => $travelCustomerContact->{"{$channel}_consent_at"} ?? $now,
                ]);
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
