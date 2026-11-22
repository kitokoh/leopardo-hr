<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Actions\TransitionOpportunityStageAction;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use App\Modules\CRM\Interfaces\Api\V1\Requests\TransitionOpportunityStageRequest;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmOpportunityResource;
use Illuminate\Http\JsonResponse;

/**
 * Deplacement d'une opportunite dans le pipeline — CRM, #9780.
 *
 * ## Le trou que ce controleur ferme
 *
 * `GET /crm/opportunities` existait, alimentait le kanban par `stage`... et
 * il n'existait AUCUN chemin d'ECRITURE :
 *
 *   - pas de `POST /crm/opportunities` — seule `ConvertLeadAction` en creait,
 *     en `prospecting` ou a l'etape fournie a la conversion ;
 *   - pas de route de changement d'etape — le kanban etait en lecture seule.
 *
 * Un commercial pouvait donc VOIR son pipeline, jamais le FAIRE AVANCER.
 * Aucune affaire ne pouvait etre marquee gagnee ou perdue apres la conversion.
 * Et `CrmOpportunityStage` (whitelist) n'etait validee sur aucun chemin
 * d'ecriture : `stage` n'etait qu'un `string(80)`.
 *
 * ## Perimetre tenant
 *
 * `CrmOpportunity` porte `BelongsToCompany` : l'identifiant d'une opportunite
 * d'une autre entreprise ne resout pas, donc **404** — jamais 403, jamais un
 * 200 sur la ligne du voisin.
 */
class CrmOpportunityController extends Controller
{
    /**
     * PATCH /crm/opportunities/{opportunity}/stage — changer l'etape.
     *
     * Terminaux : `won` et `lost` ne se quittent pas (422). Perdre une affaire
     * exige un motif ecrit.
     */
    public function transitionStage(
        TransitionOpportunityStageRequest $request,
        CrmOpportunity $opportunity,
        TransitionOpportunityStageAction $action,
    ): JsonResponse {
        // Defense en profondeur : le model binding filtre deja par tenant ;
        // on le re-affirme ici parce qu'une ecriture ne doit pas dependre
        // d'un seul mecanisme.
        $this->assertTenantScope($opportunity);

        /** @var Employee $actor */
        $actor = $request->user();

        $updated = $action->execute($opportunity, $request->validated(), $actor);

        return (new CrmOpportunityResource($updated))->response();
    }
}
