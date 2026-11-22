<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Enums\CrmOpportunityStage;
use App\Modules\CRM\Domain\Models\CrmActivity;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCrmOpportunityStageRequest;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmOpportunityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * #9790 — Faire avancer une opportunité dans le pipeline.
 *
 * Avant cette route, `GET /crm/opportunities` alimentait le kanban et **aucun
 * chemin d'écriture** n'existait : une affaire convertie restait en
 * `prospecting` indéfiniment, et le CA gagné ne pouvait jamais être constaté.
 *
 * Trois décisions portées ici :
 *
 * 1. `status` est **dérivé** de `stage` (`won`/`lost` → `closed`, sinon `open`).
 *    Deux colonnes qui disent la même chose finissent toujours par se
 *    contredire ; une seule source de vérité évite la divergence.
 * 2. `won` et `lost` sont **terminaux** (422). Rouvrir une affaire close
 *    réécrirait l'historique commercial : le CA constaté changerait après coup.
 * 3. L'étape précédente est journalisée en **append-only** dans `notes` et dans
 *    la timeline (`CrmActivity`), avec l'auteur et l'horodatage.
 */
final class CrmOpportunityStageController
{
    public function update(UpdateCrmOpportunityStageRequest $request, int $opportunity): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var CrmOpportunity $model */
        $model = CrmOpportunity::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($opportunity)
            ->firstOrFail();

        $target = CrmOpportunityStage::from((string) $request->input('stage'));
        $previous = CrmOpportunityStage::tryFrom((string) $model->stage);

        if ($previous === $target) {
            throw ValidationException::withMessages([
                'stage' => 'L\'opportunité est déjà à cette étape.',
            ]);
        }

        if ($previous !== null && in_array($previous, [CrmOpportunityStage::Won, CrmOpportunityStage::Lost], true)) {
            throw ValidationException::withMessages([
                'stage' => 'Une affaire gagnée ou perdue est close : son étape ne peut plus changer.',
            ]);
        }

        $status = in_array($target, [CrmOpportunityStage::Won, CrmOpportunityStage::Lost], true)
            ? 'closed'
            : 'open';

        $lostReason = $target === CrmOpportunityStage::Lost
            ? trim((string) $request->input('lost_reason'))
            : null;

        $entry = sprintf(
            '[%s] Étape : %s → %s (par %s)%s',
            Carbon::now()->toIso8601String(),
            $previous?->value ?? 'inconnue',
            $target->value,
            $actor->email ?? ('#'.$actor->id),
            $lostReason !== null ? ' — motif : '.$lostReason : ''
        );

        DB::transaction(function () use ($model, $target, $status, $entry, $actor, $previous, $lostReason): void {
            $model->stage = $target->value;
            $model->status = $status;
            $model->notes = $model->notes === null || $model->notes === ''
                ? $entry
                : $model->notes."\n".$entry;
            $model->save();

            CrmActivity::query()->create([
                'company_id' => $actor->company_id,
                'opportunity_id' => $model->id,
                'type' => CrmActivity::TYPE_STAGE_CHANGED,
                'subject' => sprintf('%s → %s', $previous?->value ?? 'inconnue', $target->value),
                'description' => $lostReason,
                'occurred_at' => Carbon::now(),
                'created_by' => $actor->id,
            ]);
        });

        return (new CrmOpportunityResource($model->refresh()))
            ->response()
            ->setStatusCode(200);
    }
}
