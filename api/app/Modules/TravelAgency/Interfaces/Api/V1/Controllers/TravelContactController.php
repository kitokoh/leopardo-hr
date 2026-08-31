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
 * Publie `travel.contact.submitted.v1` dans l'outbox (consommé par le BC
 * CRM — jamais d'import direct, garde d'isolation #5584). PII minimale :
 * l'email et le message sont transportés dans le payload, le consentement
 * est obligatoire.
 */
class TravelContactController extends Controller
{
    public function __construct(private readonly TravelOutboxPublisher $outbox) {}

    public function store(StoreTravelContactRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $companyId = (string) $actor->company_id;

        $this->outbox->publish($companyId, 'travel.contact.submitted.v1', [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'message' => $request->validated('message'),
            'consent' => true,
            'submitted_at' => now()->toIso8601String(),
        ]);

        return response()->json(['data' => ['accepted' => true]], 202);
    }
}
