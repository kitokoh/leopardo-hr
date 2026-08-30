<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelContactRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-416 (#6068) — Formulaire de contact → lead CRM.
 *
 * Capte une demande (nom, email/tél, message borné, consentement) et
 * publie l'événement `travel.contact.submitted.v1` via l'outbox. Le BC
 * CRM (BC-11) consomme l'événement pour créer le lead — jamais d'écriture
 * directe dans les tables CRM depuis la verticale (spec §8.5).
 *
 * Le consentement email est également enregistré dans le registre
 * `travel_customer_contacts` (TRAVEL-415/#6067) : la soumission du
 * formulaire avec consentement vaut opt-in email explicite.
 */
class TravelContactController extends Controller
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    public function store(StoreTravelContactRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $email = strtolower(trim((string) $request->validated('email')));
        $message = trim((string) $request->validated('message'));

        // Registre de consentement de la verticale (upsert idempotent par
        // email — contrainte unique (company_id, email)).
        DB::transaction(function () use ($actor, $request, $email): void {
            /** @var TravelCustomerContact|null $contact */
            $contact = TravelCustomerContact::query()->where('email', $email)->first();

            if ($contact instanceof TravelCustomerContact) {
                $contact->forceFill([
                    'first_name' => $request->validated('first_name') ?? $contact->first_name,
                    'last_name' => $request->validated('last_name') ?? $contact->last_name,
                    'phone' => $request->validated('phone') ?? $contact->phone,
                    'email_consent_given' => true,
                    'email_consent_at' => $contact->email_consent_at ?? now(),
                ])->save();

                return;
            }

            TravelCustomerContact::query()->create([
                'company_id' => $actor->company_id,
                'first_name' => $request->validated('first_name'),
                'last_name' => $request->validated('last_name'),
                'email' => $email,
                'phone' => $request->validated('phone'),
                'email_consent_given' => true,
                'email_consent_at' => now(),
                'metadata_json' => null,
            ]);
        });

        $this->outbox->publish($actor->company_id, 'travel.contact.submitted.v1', [
            'email' => $email,
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'phone' => $request->validated('phone'),
            'message' => $message,
            'submitted_at' => now()->toIso8601String(),
            'consent_email' => true,
        ]);

        return response()->json(['status' => 'received'], 202);
    }
}
