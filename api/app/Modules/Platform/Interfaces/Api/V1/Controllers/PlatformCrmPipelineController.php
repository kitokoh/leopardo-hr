<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class PlatformCrmPipelineController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $requests = CompanyRequest::with('approvedCompany:id,name,slug,status,subscription_start,subscription_end,plan_id')
            ->orderByDesc('created_at')
            ->get();

        $pipeline = [
            'leads' => [],     // pending
            'trials' => [],    // approved + company is trial
            'active' => [],    // approved + company is active
            'rejected' => [],  // rejected or suspended/expired
        ];

        foreach ($requests as $request) {
            // PA2-ADM-004: the pipeline card must surface the lead's
            // acquisition source and an admin-facing note, in addition to
            // status and conversion state, so the platform team can act
            // on a lead without opening the full detail view.
            $data = [
                'id' => $request->id,
                'company_name' => $request->company_name,
                'email' => $request->email,
                'sector' => $request->sector,
                'source' => $request->source,
                'note' => $request->note,
                'created_at' => $request->created_at,
                'status' => $request->status,
                'company' => $request->approvedCompany ? [
                    'id' => $request->approvedCompany->id,
                    'status' => $request->approvedCompany->status,
                    'days_left' => $this->calculateDaysLeft($request->approvedCompany->subscription_end),
                ] : null,
            ];

            if ($request->status === 'pending') {
                $pipeline['leads'][] = $data;
            } elseif ($request->status === 'rejected') {
                $pipeline['rejected'][] = $data;
            } elseif ($request->status === 'approved' && $request->approvedCompany) {
                if ($request->approvedCompany->status === 'trial') {
                    $pipeline['trials'][] = $data;
                } elseif ($request->approvedCompany->status === 'active') {
                    $pipeline['active'][] = $data;
                } else {
                    $pipeline['rejected'][] = $data; // suspended or expired
                }
            }
        }

        // PA2-ADM-004: expose an explicit conversion summary (lead -> trial
        // -> paying client) so the admin UI does not have to recompute
        // ratios client-side from the four raw buckets.
        $totalLeads = $requests->count();
        $convertedToTrial = count($pipeline['trials']) + count($pipeline['active']);
        $convertedToActive = count($pipeline['active']);

        return new JsonResponse([
            'data' => $pipeline,
            'meta' => [
                'total_leads' => $totalLeads,
                'conversion' => [
                    'lead_to_trial_rate' => $totalLeads > 0 ? round($convertedToTrial / $totalLeads, 4) : 0.0,
                    'lead_to_client_rate' => $totalLeads > 0 ? round($convertedToActive / $totalLeads, 4) : 0.0,
                ],
            ],
        ]);
    }

    private function calculateDaysLeft(string|Carbon|null $endDate): ?int
    {
        if (! $endDate) {
            return null;
        }
        $diff = now()->diffInDays(Carbon::parse($endDate), false);

        return $diff > 0 ? (int) $diff : 0;
    }
}

