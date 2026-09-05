<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelContactRequest;
use Illuminate\Http\JsonResponse;

/**
 * TRAVEL-416 (#6068) — Formulaire de contact → lead CRM.
 *
 * `POST /travel/contact` : valide la soumission (nom, email/tél, message
 * borné, consentement) et publie un événement `travel.contact.submitted.v1`
 * dans l'outbox TravelAgency — le BC CRM crée le lead à partir de l'événement
 * (jamais d'import direct, règle D7). Réponse 202 (traitement asynchrone) ;
 * aucune donnée sensible en log, `payload_redacted` uniquement dans l'outbox.
 */
class TravelContactController extends Controller
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    public function store(StoreTravelContactRequest $request): JsonResponse
    {
        /** @var Company $company */
        $company = currentCompany();

        $data = $request->validated();

        $this->outbox->publish(
            $company->id,
            'travel.contact.submitted.v1',
            [
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'message' => $data['message'],
                'consent_given' => true,
                'submitted_at' => now()->toIso8601String(),
                'source' => 'api.v1.contact',
            ],
            idempotencyKey: $data['idempotency_key'] ?? null,
        );

        return new JsonResponse(['message' => 'Demande envoyée.'], 202);
    }
}
