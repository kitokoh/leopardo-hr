<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Events\CatalogInquiryErased;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\CatalogInquiryStatus;
use App\Modules\Catalog\Domain\Models\CatalogInquiry;
use App\Modules\Catalog\Interfaces\Api\V1\Requests\UpdateCatalogInquiryStatusRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Back-office tenant des demandes de devis B2B (BC-28 CATALOG,
 * C-BACKOFFICE #6885).
 *
 * Endpoints PRIVÉS (groupe auth tenant + gate `b2b_catalog`) réservés aux
 * rôles gestion — principal/rh/manager (pattern TravelContact #6421) :
 *   GET   /catalog/inquiries            → liste (produit, société, statut,
 *                                        notes, dates) + filtres status/q
 *   PATCH /catalog/inquiries/{inquiry}/status → transition de statut
 *                                        (spec §7, matrice CatalogInquiryStatus)
 *                                        + note interne optionnelle
 *   GET   /catalog/inquiries/export     → export CSV des demandes (filtres
 *                                        identiques à la liste)
 *
 * Isolation : toute demande d'un autre tenant → 404 (contrôle company_id
 * explicite, leçon #3727). Les données acheteur restent privées au tenant
 * (jamais exposées publiquement — C-LEAD #6884 ne renvoie qu'un accusé).
 */
class CatalogInquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $this->managerOrAbort($request);

        $inquiries = CatalogInquiry::query()
            ->where('company_id', $actor->company_id)
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', (string) $request->input('status'));
            })
            ->when($request->filled('q'), function ($query) use ($request): void {
                $q = (string) $request->input('q');
                $query->where(function ($sub) use ($q): void {
                    $sub->where('company_name', 'ilike', '%'.$q.'%')
                        ->orWhere('email', 'ilike', '%'.$q.'%')
                        ->orWhere('product_name', 'ilike', '%'.$q.'%');
                });
            })
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($inquiries->items())
                ->map(fn (CatalogInquiry $inquiry): array => $this->payload($inquiry)),
            'meta' => [
                'current_page' => $inquiries->currentPage(),
                'last_page' => $inquiries->lastPage(),
                'total' => $inquiries->total(),
            ],
        ]);
    }

    public function updateStatus(UpdateCatalogInquiryStatusRequest $request, CatalogInquiry $inquiry): JsonResponse
    {
        $actor = $this->managerOrAbort($request);

        if ($inquiry->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $current = $inquiry->status;
        $target = CatalogInquiryStatus::tryFrom((string) $request->validated('status'));

        // Matrice de transition stricte (spec §7 ; terminal = immuable).
        if ($target === null
            || $current->isTerminal()
            || ! in_array($target, $current->allowedTransitions(), true)) {
            return new JsonResponse([
                'error' => 'INVALID_INQUIRY_STATUS_TRANSITION',
                'current' => $current->value,
            ], 422);
        }

        $note = $request->validated('note');

        $inquiry->status = $target;
        if (is_string($note) && trim($note) !== '') {
            $stamped = '['.$this->stamp().'] '.trim($note);
            $inquiry->notes = $inquiry->notes !== null && trim($inquiry->notes) !== ''
                ? $inquiry->notes."\n".$stamped
                : $stamped;
        }
        $inquiry->save();

        return response()->json(['data' => $this->payload($inquiry->refresh())]);
    }

    /**
     * DELETE /catalog/inquiries/{inquiry} — droit d'effacement RGPD des
     * données acheteur (C-RGPD #6889, canal : support tenant). Suppression
     * définitive de la demande + propagation aux leads CRM BC-11 du même
     * acheteur (événement catalog.inquiry_erased). Réservé
     * principal/rh/manager ; 404 cross-tenant.
     */
    public function destroy(Request $request, CatalogInquiry $inquiry): JsonResponse
    {
        $actor = $this->managerOrAbort($request);

        if ($inquiry->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $buyerEmail = (string) $inquiry->email;
        $inquiryId = (int) $inquiry->id;

        $inquiry->delete();

        event(new CatalogInquiryErased((string) $actor->company_id, $buyerEmail, $inquiryId));

        return response()->json(['data' => null], 200);
    }

    public function export(Request $request): StreamedResponse
    {
        $actor = $this->managerOrAbort($request);

        $inquiries = CatalogInquiry::query()
            ->where('company_id', $actor->company_id)
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', (string) $request->input('status'));
            })
            ->orderByDesc('id')
            ->limit(5000)
            ->get();

        $filename = sprintf('catalog-inquiries-%s-%s.csv', $actor->company_id, now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($inquiries): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            // BOM UTF-8 : compatibilité Excel.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'id',
                'created_at',
                'product_slug',
                'product_name',
                'quantity',
                'company_name',
                'email',
                'message',
                'status',
                'consent_at',
                'retention_until',
                'notes',
            ]);

            foreach ($inquiries as $inquiry) {
                /** @var CatalogInquiry $inquiry */
                fputcsv($out, [
                    $inquiry->id,
                    $inquiry->created_at?->toIso8601String(),
                    $inquiry->product_slug,
                    $inquiry->product_name,
                    $inquiry->quantity,
                    $inquiry->company_name,
                    $inquiry->email,
                    $inquiry->message,
                    $inquiry->status->value,
                    $inquiry->consent_at?->toIso8601String(),
                    $inquiry->retention_until?->toIso8601String(),
                    $inquiry->notes,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function managerOrAbort(Request $request): Employee
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        return $actor;
    }

    private function stamp(): string
    {
        return Carbon::now()->toIso8601String();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CatalogInquiry $inquiry): array
    {
        return [
            'id' => $inquiry->id,
            'product_slug' => $inquiry->product_slug,
            'product_name' => $inquiry->product_name,
            'quantity' => $inquiry->quantity,
            'company_name' => $inquiry->company_name,
            'email' => $inquiry->email,
            'message' => $inquiry->message,
            'status' => $inquiry->status->value,
            'notes' => $inquiry->notes,
            'consent_at' => $inquiry->consent_at?->toIso8601String(),
            'retention_until' => $inquiry->retention_until?->toIso8601String(),
            'created_at' => $inquiry->created_at?->toIso8601String(),
        ];
    }
}
