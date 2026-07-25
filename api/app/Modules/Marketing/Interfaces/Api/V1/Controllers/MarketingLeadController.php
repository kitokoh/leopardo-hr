<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Application\Actions\CaptureMarketingLead;
use App\Modules\Marketing\Application\DTOs\CreateMarketingLeadDTO;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreMarketingLeadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PA2-MKT-007 — Funnel CRM marketing.
 *
 * `store()` is called server-to-server by the public vitrine's Next.js API
 * routes (`front/web/src/app/api/forms/{signup,demo,contact,newsletter}`)
 * right after `captureMarketingLead()` logs the event and best-effort
 * forwards it to the external CRM/email webhooks — it persists the same
 * lead durably so nothing is lost if the external forwarders are down, and
 * so the platform CRM pipeline (PA2-ADM-004) has a real data source
 * instead of only `company_requests` (which only covers `signup`, not
 * demo/contact/newsletter).
 *
 * Public and unauthenticated by nature (called before any tenant exists),
 * protected by the same shared secret already documented for
 * `MARKETING_LEAD_WEBHOOK_TOKEN` in
 * `docs/validation/LAUNCH_OBSERVABILITY_DASHBOARD.md` — mirrors
 * `EmailBounceWebhookController`.
 *
 * The admin-facing listing/status endpoints for the platform CRM pipeline
 * live in PA2-ADM-004 (`PlatformCrmPipelineController` /
 * `PlatformMarketingLeadController`), which depends on this ticket.
 */
class MarketingLeadController extends Controller
{
    public function __construct(
        private readonly CaptureMarketingLead $captureMarketingLead,
    ) {}

    public function store(StoreMarketingLeadRequest $request): JsonResponse
    {
        $configuredSecret = (string) config('services.marketing_lead_webhook.secret', '');

        if ($configuredSecret !== '') {
            $providedSecret = (string) $request->header('X-Marketing-Lead-Token', '');

            if (! hash_equals($configuredSecret, $this->extractBearerOrHeader($request, $providedSecret))) {
                Log::warning('Marketing lead ingest: invalid or missing shared secret');

                return new JsonResponse(['error' => 'Invalid signature'], 400);
            }
        }

        $dto = CreateMarketingLeadDTO::fromArray($request->validated());
        $lead = $this->captureMarketingLead->execute($dto);

        return new JsonResponse([
            'data' => [
                'id' => $lead->id,
                'external_id' => $lead->external_id,
                'status' => $lead->status,
            ],
        ], 201);
    }

    private function extractBearerOrHeader(Request $request, string $headerValue): string
    {
        if ($headerValue !== '') {
            return $headerValue;
        }

        $authorizationHeader = $request->header('Authorization');
        $authorization = is_string($authorizationHeader) ? $authorizationHeader : '';

        return str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';
    }
}
