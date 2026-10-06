<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Actions\CreateAccountAction;
use App\Modules\CRM\Application\Actions\CreateContactAction;
use App\Modules\CRM\Application\Actions\CreateLeadAction;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmAccountRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmContactRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmLeadRequest;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmAccountResource;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmContactResource;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmLeadResource;
use Illuminate\Http\JsonResponse;

/**
 * #8195 / #5731 — Endpoints de création et consultation unitaire CRM (leads, contacts, accounts).
 */
class CrmDirectoryWriteController extends Controller
{
    public function storeLead(StoreCrmLeadRequest $request, CreateLeadAction $action): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $lead = $action->execute($actor, $request->validated());

        return (new CrmLeadResource($lead))
            ->response()
            ->setStatusCode(201);
    }

    public function showLead(CrmLead $lead): JsonResponse
    {
        return (new CrmLeadResource($lead))->response();
    }

    public function updateLead(StoreCrmLeadRequest $request, CrmLead $lead): JsonResponse
    {
        $lead->update($request->validated());

        return (new CrmLeadResource($lead))->response();
    }

    public function storeContact(StoreCrmContactRequest $request, CreateContactAction $action): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $contact = $action->execute($actor, $request->validated());

        return (new CrmContactResource($contact))
            ->response()
            ->setStatusCode(201);
    }

    public function showContact(CrmContact $contact): JsonResponse
    {
        return (new CrmContactResource($contact))->response();
    }

    public function storeAccount(StoreCrmAccountRequest $request, CreateAccountAction $action): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $account = $action->execute($actor, $request->validated());

        return (new CrmAccountResource($account))
            ->response()
            ->setStatusCode(201);
    }

    public function showAccount(CrmAccount $account): JsonResponse
    {
        return (new CrmAccountResource($account))->response();
    }
}
