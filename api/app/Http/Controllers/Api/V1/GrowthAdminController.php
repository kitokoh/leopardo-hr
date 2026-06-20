<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Commission;
use App\Models\PartnerAuditLog;
use App\Services\PartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GrowthAdminController extends Controller
{
    public function __construct(private PartnerService $partnerService)
    {}

    /**
     * List all partners for the platform.
     */
    public function partners(): JsonResponse
    {
        $partners = Partner::with('user:id,first_name,last_name,email')
            ->withCount('referredCompanies')
            ->get();

        return new JsonResponse(['data' => $partners]);
    }

    /**
     * Update partner commission rate with audit log.
     */
    public function updateRate(Request $request, Partner $partner): JsonResponse
    {
        $validated = $request->validate([
            'rate' => 'required|integer|min:0|max:10000',
            'reason' => 'required|string|min:5',
        ]);

        $this->partnerService->updatePartnerRate(
            $partner,
            $validated['rate'],
            Auth::id(),
            $validated['reason']
        );

        return new JsonResponse(['success' => true]);
    }

    /**
     * List recent commissions and audits.
     */
    public function history(): JsonResponse
    {
        return new JsonResponse([
            'commissions' => Commission::with(['partner.user', 'company'])->latest()->take(50)->get(),
            'audit_logs' => PartnerAuditLog::latest()->take(50)->get(),
        ]);
    }
}
