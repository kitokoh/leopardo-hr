<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Actions\ConvertLeadAction;
use App\Modules\CRM\Domain\Exceptions\CrmLeadException;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Interfaces\Api\V1\Requests\ConvertLeadRequest;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmLeadResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #5717 — API de conversion lead → account/contact/opportunity.
 *
 * Route : POST /api/v1/crm/leads/{crmLead}/convert — protégée par
 * `CrmLeadPolicy` + contexte tenant (404 sûr cross-tenant).
 */
class CrmLeadController extends Controller
{
    public function __construct(private readonly ConvertLeadAction $convertLead)
    {
    }

    public function convert(ConvertLeadRequest $request, CrmLead $crmLead): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        // 404 sûr cross-tenant (binding {crmLead} précède le middleware tenant).
        if ($actor->company_id !== $crmLead->company_id) {
            abort(404);
        }

        if ($actor->cannot('convert', $crmLead)) {
            abort(403);
        }

        try {
            $converted = $this->convertLead->handle($crmLead->id, $actor, $request->validated());
        } catch (CrmLeadException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422);
        }

        return (new CrmLeadResource($converted))->response();
    }
}
