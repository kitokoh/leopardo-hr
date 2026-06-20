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
