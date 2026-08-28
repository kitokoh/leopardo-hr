<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Services\CrmEmailService;
use App\Modules\CRM\Domain\DTOs\EmailDeliveryResult;
use App\Modules\CRM\Domain\DTOs\EmailMessage;
use App\Modules\CRM\Domain\Exceptions\EmailRateLimitExceededException;
use App\Modules\CRM\Domain\Exceptions\InvalidUnsubscribeTokenException;
use App\Modules\CRM\Domain\Models\CrmEmailSuppression;
use App\Modules\CRM\Infrastructure\Services\UnsubscribeTokenService;
use App\Modules\CRM\Interfaces\Api\V1\Requests\EmailUnsubscribeRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\SendMarketingEmailRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\SendTransactionalEmailRequest;
use Illuminate\Http\JsonResponse;

/**
 * Canal email CRM — Issue #5726.
 *
 * RBAC : envoi transactionnel / marketing = `principal` / `marketing`
 * (middleware + Policy `CrmEmailPolicy`) ; désabonnement = jeton signé
 * (lien email, aucune session requise).
 */
class CrmEmailController extends Controller
{
    public function __construct(
        private readonly CrmEmailService $emails,
        private readonly UnsubscribeTokenService $unsubscribeTokens,
    ) {}

    public function sendTransactional(SendTransactionalEmailRequest $request): JsonResponse
    {
        $this->authorize('sendTransactional', CrmEmailSuppression::class);

        $companyId = $this->companyId();

        try {
            $result = $this->emails->sendTransactional(
                new EmailMessage(
                    $request->string('to')->toString(),
                    $request->string('subject')->toString(),
                    $request->string('body')->toString(),
                    $this->context($request),
                ),
                $companyId,
            );
        } catch (EmailRateLimitExceededException) {
            return response()->json([
                'error' => 'EMAIL_RATE_LIMITED',
                'message' => 'Quota email dépassé pour cette heure.',
            ], 429);
        }

        return response()->json(['data' => $this->serializeResult($result)]);
    }

    public function sendMarketing(SendMarketingEmailRequest $request): JsonResponse
    {
        $this->authorize('sendMarketing', CrmEmailSuppression::class);

        $companyId = $this->companyId();

        try {
            $result = $this->emails->sendMarketing(
                new EmailMessage(
                    $request->string('to')->toString(),
                    $request->string('subject')->toString(),
                    $request->string('body')->toString(),
                    $this->context($request),
                ),
                $companyId,
                $request->integer('contact_id'),
            );
        } catch (EmailRateLimitExceededException) {
            return response()->json([
                'error' => 'EMAIL_RATE_LIMITED',
                'message' => 'Quota email dépassé pour cette heure.',
            ], 429);
        }

        return response()->json(['data' => $this->serializeResult($result)]);
    }

    public function unsubscribe(EmailUnsubscribeRequest $request): JsonResponse
    {
        try {
            $data = $this->unsubscribeTokens->verify($request->string('token')->toString());
        } catch (InvalidUnsubscribeTokenException) {
            return response()->json([
                'error' => 'INVALID_UNSUBSCRIBE_TOKEN',
                'message' => 'Lien de désabonnement invalide ou expiré.',
            ], 422);
        }

        $this->emails->unsubscribe($data['company_id'], $data['contact_id'], $data['email']);

        return response()->json(['data' => ['status' => 'unsubscribed']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(SendTransactionalEmailRequest|SendMarketingEmailRequest $request): array
    {
        $context = [];
        if ($request->filled('contact_id')) {
            $context['contact_id'] = $request->integer('contact_id');
        }
        if ($request->filled('campaign_send_id')) {
            $context['campaign_send_id'] = $request->integer('campaign_send_id');
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResult(EmailDeliveryResult $result): array
    {
        return [
            'status' => $result->status,
            'message_id' => $result->messageId,
            'error' => $result->error,
        ];
    }

    private function companyId(): string
    {
        if (! app()->bound('current_company')) {
            abort(403, 'Tenant context missing.');
        }

        return (string) currentCompany()->id;
    }
}
