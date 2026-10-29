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
     * @param  array<string, mixed>  $data
     */
    public function execute(string $companyId, array $data): void
    {
        // TRAVEL-416 admet un contact « telephone seul » : l'email est donc
        // nullable, contrairement au formulaire public (TRAVEL-913) ou il est
        // la cle du registre de consentement.
        $rawEmail = $data['email'] ?? null;
        $email = is_string($rawEmail) && $rawEmail !== ''
            ? strtolower(trim($rawEmail))
            : null;
        $phone = trim((string) ($data['phone'] ?? ''));
        $phone = $phone === '' ? null : $phone;
        $message = trim((string) ($data['message'] ?? ''));

        // TRAVEL-416 (#6068) : le formulaire expose un `name` unique cote
        // authentifie ; le formulaire public (TRAVEL-913) expose first/last.
        // Le payload d'evenement conserve `name` pour le BC CRM, qui l'attend.
        $firstName = isset($data['first_name']) ? trim((string) $data['first_name']) : '';
        $lastName = isset($data['last_name']) ? trim((string) $data['last_name']) : '';
        $name = trim((string) ($data['name'] ?? trim($firstName.' '.$lastName)));

        // `source` distingue les deux surfaces (formulaire authentifie vs
        // formulaire public signe) : sans elle, le CRM ne peut pas router ni
        // mesurer les leads. Defaut aligne sur le contrat TRAVEL-416.
        $source = (string) ($data['source'] ?? 'api.v1.contact');

        // `consent` (TRAVEL-416) et `consent_email` (TRAVEL-913) portent le
        // meme engagement : la soumission vaut opt-in email explicite.
        $consentGiven = (bool) ($data['consent_email'] ?? $data['consent'] ?? false);

        /** @var Company|null $company */
        $company = Company::query()->find($companyId);
        if (! $company instanceof Company) {
            abort(404, 'Company introuvable.');
        }

        // Garde de verticale (#8128) : la route publique signée est HORS du
        // groupe `module.travelagency` — sans ce contrôle, un tenant sans la
        // feature Travel verrait quand même ses soumissions acceptées. 404
        // (pas 403) : l'état d'activation de la verticale ne se divulgue
        // pas sur une surface publique.
        if (! $company->hasFeature('travelagency')) {
            abort(404, 'Company introuvable.');
        }

        app(TenantManager::class)->withinTenant($company, function () use ($company, $data, $email, $firstName, $lastName, $consentGiven): void {
            DB::transaction(function () use ($company, $data, $email, $firstName, $lastName, $consentGiven): void {
                // Sans email, il n'y a pas de cle de registre de consentement
                // (contrainte unique (company_id, email)) : un contact
                // « telephone seul » produit un lead CRM sans ecriture RGPD,
                // ce qui est le comportement attendu (TRAVEL-416).
                if ($email === null || $email === '') {
                    return;
                }

                /** @var TravelCustomerContact|null $contact */
                $contact = TravelCustomerContact::query()->where('email', $email)->first();

                if ($contact instanceof TravelCustomerContact) {
                    $contact->forceFill([
                        'first_name' => $firstName !== '' ? $firstName : ($data['name'] ?? $contact->first_name),
                        'last_name' => $lastName !== '' ? $lastName : $contact->last_name,
                        'phone' => $data['phone'] ?? $contact->phone,
                        'email_consent_given' => $consentGiven,
                        'email_consent_at' => $contact->email_consent_at ?? now(),
                    ])->save();

                    return;
                }

                TravelCustomerContact::query()->create([
                    'company_id' => $company->id,
                    'first_name' => $firstName !== '' ? $firstName : ($data['name'] ?? null),
                    'last_name' => $lastName !== '' ? $lastName : null,
                    'email' => $email,
                    'phone' => $data['phone'] ?? null,
                    'email_consent_given' => $consentGiven,
                    'email_consent_at' => now(),
                    'metadata_json' => null,
                ]);
            });
        });

        // Idempotence de rejeu : un client qui retente (ou double-clique)
        // envoie le meme `idempotency_key` ; la contrainte unique
        // (company_id, idempotency_key) garantit un seul evenement.
        $rawKey = $data['idempotency_key'] ?? null;
        $idempotencyKey = is_string($rawKey) && $rawKey !== '' ? $rawKey : null;

        $this->outbox->publish($companyId, 'travel.contact.submitted.v1', [
            'name' => $name !== '' ? $name : null,
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'message' => $message,
            'submitted_at' => now()->toIso8601String(),
            // Deux cles pour le meme engagement : `consent_email` est le nom
            // historique lu par le BC CRM, `consent_given` le nom generique
            // introduit par TRAVEL-416. Les deux sont emises pour ne casser
            // aucun consommateur existant.
            'consent_email' => $consentGiven,
            'consent_given' => $consentGiven,
            'source' => $source,
        ], $idempotencyKey);
    }
}
