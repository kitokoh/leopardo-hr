<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Infrastructure\Services\TravelOutboxPublisher;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelContactRequest;
use Illuminate\Http\JsonResponse;

/**
 * TRAVEL-416 (#6068) — Formulaire de contact → lead CRM.
 *
 * Captation d'une demande (nom, email/tél, message borné, consentement) :
 * la verticale ne fait AUCUNE écriture dans les tables CRM — elle publie
 * l'événement versionné `travel.contact.submitted.v1` que le BC CRM
 * consomme pour créer le lead (contrat, jamais d'import direct).
 * Le consentement est obligatoire (422 TRAVEL_CONSENT_REQUIRED).
 */
class TravelContactController extends Controller
{
    public const EVENT_CONTACT_SUBMITTED = 'travel.contact.submitted.v1';

    public function __construct(private readonly TravelOutboxPublisher $outbox)
    {
    }

    public function submit(StoreTravelContactRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $data = $request->validated();

        $this->outbox->publish((string) $actor->company_id, self::EVENT_CONTACT_SUBMITTED, [
            'submitted_at' => now()->toIso8601String(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'],
            'consent' => true,
        ], 'contact-'.hash('sha256', $data['email'].'|'.$data['message']));

        return response()->json([
            'data' => [
                'status' => 'received',
                'event' => self::EVENT_CONTACT_SUBMITTED,
            ],
        ], 202);
    }
}
