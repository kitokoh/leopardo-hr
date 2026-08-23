<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Events\MarketingLeadQualified;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Marketing\Domain\Models\MarketingLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Conversion lead marketing → contact comptabilité — issue #5231.
 *
 * RBAC : routes protégées par `api.manager:marketing,principal` (groupe
 * `marketing` de `routes/modules/marketing.php`).
 *
 * `qualify()` : transition `status → qualified` + événement
 * `MarketingLeadQualified` (le listener crée l'AccountingContact).
 * Garde anti-claim concurrent : `lockForUpdate` + refus si le lead est
 * déjà réclamé (`converted_company_id` non nul) ou dans un état terminal.
 *
 * `contact()` : lecture marketing (read-only) du contact créé pour un lead
 * réclamé par le tenant courant — 404 si le lead n'appartient pas au
 * tenant (scope `BelongsToCompany` sur `AccountingContact`).
 */
class MarketingLeadConversionController extends Controller
{
    public function qualify(Request $request, MarketingLead $lead): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return DB::transaction(function () use ($lead, $actor): JsonResponse {
            /** @var MarketingLead|null $locked */
            $locked = MarketingLead::query()->lockForUpdate()->find($lead->id);

            if (! $locked instanceof MarketingLead) {
                abort(404);
            }

            if ($locked->converted_company_id !== null || $locked->status === MarketingLead::STATUS_CONVERTED || $locked->status === MarketingLead::STATUS_REJECTED) {
                return response()->json([
                    'error' => 'LEAD_ALREADY_CLAIMED',
                    'message' => 'This lead has already been claimed by a company.',
                ], 409);
            }

            $locked->status = MarketingLead::STATUS_QUALIFIED;
            $locked->save();

            // Listener synchrone : crée l'AccountingContact dans la même
            // transaction (source=marketing_lead, marketing_lead_id).
            event(new MarketingLeadQualified($locked, (string) $actor->company_id));

            /** @var AccountingContact|null $contact */
            $contact = AccountingContact::query()
                ->where('marketing_lead_id', $locked->id)
                ->first();

            return response()->json([
                'data' => [
                    'lead' => [
                        'id' => $locked->id,
                        'external_id' => $locked->external_id,
                        'status' => $locked->status,
                        'converted_company_id' => $locked->converted_company_id,
                    ],
                    'contact' => $contact instanceof AccountingContact ? [
                        'id' => $contact->id,
                        'name' => $contact->name,
                        'email' => $contact->email,
                        'source' => $contact->source,
                        'marketing_lead_id' => $contact->marketing_lead_id,
                    ] : null,
                ],
            ], 201);
        });
    }

    public function contact(Request $request, MarketingLead $lead): JsonResponse
    {
        /** @var AccountingContact|null $contact */
        $contact = AccountingContact::query()
            ->where('marketing_lead_id', $lead->id)
            ->first();

        if (! $contact instanceof AccountingContact) {
            abort(404, 'CONTACT_NOT_FOUND');
        }

        return response()->json([
            'data' => [
                'id' => $contact->id,
                'type' => $contact->type,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'source' => $contact->source,
                'marketing_lead_id' => $contact->marketing_lead_id,
            ],
        ]);
    }
}
