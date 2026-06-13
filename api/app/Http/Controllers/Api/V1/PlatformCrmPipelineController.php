<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompanyRequest;
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
            $data = [
                'id' => $request->id,
                'company_name' => $request->company_name,
                'email' => $request->email,
                'sector' => $request->sector,
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

        return new JsonResponse([
            'data' => $pipeline,
        ]);
    }

    private function calculateDaysLeft(?string $endDate): ?int
    {
        if (!$endDate) return null;
        $diff = now()->diffInDays(\Carbon\Carbon::parse($endDate), false);
        return $diff > 0 ? (int) $diff : 0;
    }
}
