<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Models\CommunicationPendingReply;
use App\Modules\Communication\Domain\Models\CommunicationReplyPolicy;
use App\Modules\Communication\Infrastructure\Services\CommunicationTaxonomyService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * Cas d'usage « définir la politique de réponse assistée » d'une boîte
 * pour une catégorie (BC-29 COMMUNICATION, R5 #7690) : upsert d'UNE ligne
 * par couple boîte × catégorie (`off` explicite conservé — auditable).
 *
 * Extrait de `CommunicationReplyPolicyController::store` (BOS-024e,
 * #8216). Garde-fous à l'écriture, reproduits à l'identique :
 * boîte d'un autre employé → 404 `MAILBOX_NOT_FOUND` ; catégorie inconnue
 * de la taxonomie du tenant → 422 `REPLY_CATEGORY_UNKNOWN` ; `auto` sur
 * une catégorie finance/RH/juridique (liste bloquée EN DUR, jamais
 * contournable) → 422 `REPLY_AUTO_CATEGORY_BLOCKED` ; `confirm`/`auto`
 * sans scope `gmail.send` → 422 `GMAIL_SEND_SCOPE_REQUIRED` ; `draft`
 * sans scope `gmail.compose` → 422 `GMAIL_COMPOSE_SCOPE_REQUIRED`.
 * La Policy (`viewAny`, côté interface) et la validation du payload
 * restent au niveau interface.
 */
final class UpsertReplyPolicyAction
{
    public function __construct(private readonly CommunicationTaxonomyService $taxonomy) {}

    /**
     * @param  array{integration_id: string, category_key: string, policy: string}  $validated
     * @return array{policy: CommunicationReplyPolicy, created: bool}
     *
     * @throws HttpResponseException 404 MAILBOX_NOT_FOUND · 422
     *                               REPLY_CATEGORY_UNKNOWN / REPLY_AUTO_CATEGORY_BLOCKED /
     *                               GMAIL_SEND_SCOPE_REQUIRED / GMAIL_COMPOSE_SCOPE_REQUIRED
     */
    public function execute(Employee $employee, array $validated): array
    {
        /** @var CommunicationIntegration|null $integration */
        $integration = CommunicationIntegration::query()
            ->where('employee_id', $employee->id)
            ->find($validated['integration_id']);

        if ($integration === null) {
            // Boîte inconnue OU appartenant à quelqu'un d'autre : 404 (ne
            // pas révéler l'existence des boîtes des autres).
            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.reply_mailbox_not_found'),
                'code' => 'MAILBOX_NOT_FOUND',
            ], 404));
        }

        $categoryKey = $validated['category_key'];

        if (! in_array($categoryKey, $this->taxonomy->activeCategoryKeys((string) $employee->company_id), true)) {
            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.reply_category_unknown'),
                'code' => 'REPLY_CATEGORY_UNKNOWN',
            ], 422));
        }

        $policyValue = $validated['policy'];

        // Liste bloquée EN DUR (spec §3.5) : jamais d'auto sur finance/RH/juridique.
        if ($policyValue === CommunicationReplyPolicy::POLICY_AUTO
            && CommunicationReplyPolicy::isAutoBlockedCategory($categoryKey)) {
            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.reply_auto_category_blocked'),
                'code' => 'REPLY_AUTO_CATEGORY_BLOCKED',
            ], 422));
        }

        if (in_array($policyValue, [CommunicationReplyPolicy::POLICY_CONFIRM, CommunicationReplyPolicy::POLICY_AUTO], true)
            && ! $integration->hasSendScope()) {
            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.reply_send_scope_required'),
                'code' => 'GMAIL_SEND_SCOPE_REQUIRED',
            ], 422));
        }

        if ($policyValue === CommunicationPendingReply::MODE_DRAFT && ! $integration->hasComposeScope()) {
            throw new HttpResponseException(new JsonResponse([
                'message' => __('communication.reply_compose_scope_required'),
                'code' => 'GMAIL_COMPOSE_SCOPE_REQUIRED',
            ], 422));
        }

        /** @var CommunicationReplyPolicy|null $policy */
        $policy = CommunicationReplyPolicy::query()
            ->where('integration_id', $integration->id)
            ->where('category_key', $categoryKey)
            ->first();

        $created = $policy === null;

        if ($policy === null) {
            $policy = new CommunicationReplyPolicy;
            $policy->forceFill([
                'company_id' => (string) $employee->company_id,
                'integration_id' => $integration->id,
                'category_key' => $categoryKey,
            ]);
        }

        $policy->forceFill(['policy' => $policyValue])->save();

        return ['policy' => $policy, 'created' => $created];
    }
}
