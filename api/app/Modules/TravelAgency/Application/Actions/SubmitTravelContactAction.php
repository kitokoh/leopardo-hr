<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerContact;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-416 (#6068) / TRAVEL-913 (#6425) — Soumission d'une demande de
 * contact → événement lead CRM.
 *
 * Partage entre la route authentifiée (`POST /travel/contact`) et la route
 * publique signée (`POST /travel/public/contact`) : upsert idempotent du
 * registre de consentement (`travel_customer_contacts`, contrainte unique
 * (company_id, email)) puis publication `travel.contact.submitted.v1` via
 * l'outbox — aucune écriture directe dans les tables CRM (spec §8.5).
 *
 * La soumission avec consentement vaut opt-in email explicite (TRAVEL-415).
 */
class SubmitTravelContactAction
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    /**
     * @param  array{first_name?: string|null, last_name?: string|null, email: string, phone?: string|null, message: string}  $data
     */
    public function execute(string $companyId, array $data): void
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $message = trim((string) ($data['message'] ?? ''));

        /** @var Company|null $company */
        $company = Company::query()->find($companyId);
        if (! $company instanceof Company) {
            abort(404, 'Company introuvable.');
        }

        app(TenantManager::class)->withinTenant($company, function () use ($company, $data, $email): void {
            DB::transaction(function () use ($company, $data, $email): void {
                /** @var TravelCustomerContact|null $contact */
                $contact = TravelCustomerContact::query()->where('email', $email)->first();

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
                    'company_id' => $company->id,
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'email' => $email,
                    'phone' => $data['phone'] ?? null,
                    'email_consent_given' => true,
                    'email_consent_at' => now(),
                    'metadata_json' => null,
                ]);
            });
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
