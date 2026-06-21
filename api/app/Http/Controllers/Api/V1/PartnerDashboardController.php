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

    private function resolveGlobalUser($authUser): \App\Models\User
    {
        if ($authUser instanceof \App\Models\User) {
            return $authUser;
        }

        if ($authUser instanceof \App\Models\Employee) {
            return \App\Models\User::firstOrCreate(
                ['email' => $authUser->email],
                [
                    'first_name' => $authUser->first_name,
                    'last_name' => $authUser->last_name,
                    'status' => 'active',
                ]
            );
        }

        abort(401, 'Unauthorized user type.');
    }

    /**
     * Appliquer pour devenir partenaire.
     */
    public function apply(Request $request): JsonResponse
    {
        $globalUser = $this->resolveGlobalUser(Auth::user());
        if (Partner::where('user_id', $globalUser->id)->exists()) {
            return new JsonResponse(['error' => 'ALREADY_EXISTS'], 400);
        }

        $validated = $request->validate([
            'type' => 'required|in:individual,agency,accountant',
            'payment_details' => 'nullable|string',
        ]);

        $partner = $this->partnerService->apply($globalUser->id, $validated);

        return new JsonResponse(['data' => $partner], 201);
    }

    /**
     * Demander un paiement.
     */
    public function requestPayout(Request $request): JsonResponse
    {
        $globalUser = $this->resolveGlobalUser(Auth::user());
        $partner = Partner::where('user_id', $globalUser->id)->first();

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
        $globalUser = $this->resolveGlobalUser(Auth::user());
        $partner = Partner::where('user_id', $globalUser->id)->first();

        if (!$partner) {
            return new JsonResponse(['error' => 'NOT_A_PARTNER', 'message' => 'Vous n\'êtes pas enregistré comme partenaire.'], 403);
        }

        // Optimized with SQL aggregations instead of memory loading
        $stats = Commission::where('partner_id', $partner->id)
            ->selectRaw("
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_earned,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_approval,
                SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_upcoming
            ")
            ->first();

        $recentCommissions = Commission::where('partner_id', $partner->id)
            ->latest()
            ->take(10)
            ->get();

        return new JsonResponse([
            'stats' => [
                'total_conversions' => $partner->referredCompanies()->count(),
                'total_earned' => (int) ($stats->total_earned ?? 0),
                'pending_approval' => (int) ($stats->pending_approval ?? 0),
                'approved_upcoming' => (int) ($stats->approved_upcoming ?? 0),
            ],
            'recent_commissions' => $recentCommissions,
        ]);
    }

    /**
     * List all companies referred by the partner.
     */
    public function referredCompanies(): JsonResponse
    {
        $globalUser = $this->resolveGlobalUser(Auth::user());
        $partner = Partner::where('user_id', $globalUser->id)->first();

        if (!$partner) {
            return new JsonResponse(['error' => 'NOT_A_PARTNER'], 403);
        }

        $companies = $partner->referredCompanies()
            ->select('id', 'name', 'status', 'created_at')
            ->get();

        return new JsonResponse(['data' => $companies]);
    }
}
