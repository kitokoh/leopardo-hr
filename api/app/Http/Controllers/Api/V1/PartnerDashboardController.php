<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Commission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PartnerDashboardController extends Controller
{
    public function __construct(private \App\Services\PartnerService $partnerService)
    {}

    /**
     * Appliquer pour devenir partenaire.
     */
    public function apply(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (Partner::where('user_id', $user->id)->exists()) {
            return new JsonResponse(['error' => 'ALREADY_EXISTS'], 400);
        }

        $validated = $request->validate([
            'type' => 'required|in:individual,agency,accountant',
            'payment_details' => 'nullable|string',
        ]);

        $partner = $this->partnerService->apply($user->id, $validated);

        return new JsonResponse(['data' => $partner], 201);
    }

    /**
     * Demander un paiement.
     */
    public function requestPayout(Request $request): JsonResponse
    {
        $user = Auth::user();
        $partner = Partner::where('user_id', $user->id)->first();

        if (!$partner) {
            return new JsonResponse(['error' => 'NOT_A_PARTNER'], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|integer|min:100',
            'currency' => 'required|string|size:3',
        ]);

        try {
            $payout = $this->partnerService->requestPayout($partner, $validated['amount'], $validated['currency']);
            return new JsonResponse(['data' => $payout], 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get statistics for the authenticated partner.
     */
    public function stats(): JsonResponse
    {
        $user = Auth::user();
        $partner = Partner::where('user_id', $user->id)->first();

        if (!$partner) {
            return new JsonResponse(['error' => 'NOT_A_PARTNER', 'message' => 'Vous n\'êtes pas enregistré comme partenaire.'], 403);
        }

        $commissions = Commission::where('partner_id', $partner->id)->get();

        return new JsonResponse([
            'stats' => [
                'total_conversions' => $partner->referredCompanies()->count(),
                'total_earned' => $commissions->where('status', 'paid')->sum('amount'),
                'pending_approval' => $commissions->where('status', 'pending')->sum('amount'),
                'approved_upcoming' => $commissions->where('status', 'approved')->sum('amount'),
            ],
            'recent_commissions' => $commissions->take(10),
        ]);
    }

    /**
     * List all companies referred by the partner.
     */
    public function referredCompanies(): JsonResponse
    {
        $user = Auth::user();
        $partner = Partner::where('user_id', $user->id)->first();

        if (!$partner) {
            return new JsonResponse(['error' => 'NOT_A_PARTNER'], 403);
        }

        $companies = $partner->referredCompanies()
            ->select('id', 'name', 'status', 'created_at')
            ->get();

        return new JsonResponse(['data' => $companies]);
    }
}
