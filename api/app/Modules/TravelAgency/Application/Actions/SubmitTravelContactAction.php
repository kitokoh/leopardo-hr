<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-416 (#6068) / TRAVEL-913 (#6425) — Soumission d'un formulaire de
 * contact → registre de consentement + événement lead CRM.
 *
 * Logique partagée entre la route authentifiée `POST /travel/contact` et la
 * route publique `POST /public/travel/contact/{companySlug}` : upsert
 * idempotent du contact (consentement email = opt-in explicite, RGPD) et
 * publication `travel.contact.submitted.v1` via l'outbox (le BC CRM crée le
 * lead — jamais d'écriture directe CRM, spec §8.5).
 *
 * Le lookup du contact existant est scoped par `company_id` (la contrainte
 * unique du registre est (company_id, email) — un lookup global créerait un
 * doublon cross-tenant).
 */
final class SubmitTravelContactAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    /**
     * @param  array<string, mixed>  $data  (validé par StoreTravelContactRequest)
     */
    public function execute(string $companyId, array $data): void
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $message = trim((string) ($data['message'] ?? ''));

        DB::transaction(function () use ($companyId, $data, $email): void {
            /** @var TravelCustomerContact|null $contact */
            $contact = TravelCustomerContact::query()
                ->where('company_id', $companyId)
                ->where('email', $email)
                ->first();

            if ($contact instanceof TravelCustomerContact) {
                $contact->forceFill([
                    'first_name' => $data['first_name'] ?? $contact->first_name,
                    'last_name' => $data['last_name'] ?? $contact->last_name,
                    'phone' => $data['phone'] ?? $contact->phone,
                    'email_consent_given' => true,
                    'email_consent_at' => $contact->email_consent_at ?? now(),
                ])->save();

                return;
            }

            TravelCustomerContact::query()->create([
                'company_id' => $companyId,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'email_consent_given' => true,
                'email_consent_at' => now(),
                'metadata_json' => null,
            ]);
        });

        $this->outbox->publish($companyId, 'travel.contact.submitted.v1', [
            'email' => $email,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'message' => $message,
            'submitted_at' => now()->toIso8601String(),
            'consent_email' => true,
        ]);
    }
}
