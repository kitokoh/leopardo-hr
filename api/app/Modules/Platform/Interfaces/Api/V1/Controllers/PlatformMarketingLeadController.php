<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Lecture des leads d'acquisition de la vitrine — tranche de #7595.
 *
 * Contrat SPA admin-dashboard : `GET /admin/marketing/leads` (super-admin), et
 * son équivalent `GET /platform/marketing/leads`.
 *
 * ## Le défaut corrigé
 *
 * La table globale `marketing_leads` (schéma public) reçoit les 5 formulaires
 * de la vitrine (`POST /marketing/leads`) depuis des mois, mais **aucune route
 * ne la relisait** : pas un seul `GET`. Les leads étaient donc écrits et
 * invisibles à l'admin — un commercial ne pouvait pas rappeler une demande de
 * démo sans passer par la base. La seule lecture existante
 * (`PlatformSolutionSurveyStatsController`) est un agrégat restreint au type
 * `solution_survey`, qui ne rend aucun lead individuel exploitable. Le
 * sous-agrégat `MarketingLeadRepository::all()` existait même déjà côté
 * Marketing… sans aucun appelant.
 *
 * ## Isolation des modules
 *
 * La lecture passe par `DB::table('marketing_leads')`, **sans jamais importer
 * une classe du module Marketing** : `api/ARCHITECTURE.md` interdit tout
 * `use App\Modules\<X>\…` depuis un module ≠ X (garde #5584,
 * `dev-hub/tools/check-module-isolation.sh`, bloquante en CI). Même pattern que
 * `PlatformSolutionSurveyStatsController` pour les lectures cross-module en
 * lecture seule. Corollaire assumé : les listes de types/statuts sont
 * dupliquées ici, et non importées — c'est le prix de la garde.
 *
 * ## Ce que la réponse expose, et pourquoi
 *
 * Le lead est renvoyé **entier**, y compris `payload` (le corps du formulaire :
 * message de contact, réponses de survey) et `ip`. C'est un arbitrage explicite :
 * sans le message, un lead de contact n'est pas exploitable, et sans l'IP une
 * campagne de spam n'est pas traçable. Cette route est donc le seul endroit qui
 * relit des données personnelles de prospect, et elle est réservée à
 * `platform.permission:crm.view` (role plateforme `marketing` inclus).
 */
final class PlatformMarketingLeadController extends Controller
{
    /** Table globale des leads vitrine (schéma public), cf. MarketingLead. */
    private const LEADS_TABLE = 'marketing_leads';

    /**
     * Types de lead acceptés — miroir des constantes `MarketingLead::TYPE_*` et
     * de la contrainte CHECK de `marketing_leads_type_check`.
     *
     * @var list<string>
     */
    private const TYPES = ['signup', 'demo_request', 'newsletter', 'contact', 'solution_survey'];

    /**
     * Statuts de qualification — miroir de `MarketingLead::STATUS_*`.
     *
     * @var list<string>
     */
    private const STATUSES = ['new', 'contacted', 'qualified', 'converted', 'rejected'];

    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(self::TYPES)],
            'status' => ['nullable', 'string', Rule::in(self::STATUSES)],
            'source' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $query = DB::table(self::LEADS_TABLE)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (filled($validated['type'] ?? null)) {
            $query->where('type', $validated['type']);
        }

        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        if (filled($validated['source'] ?? null)) {
            $query->where('source', $validated['source']);
        }

        if (filled($validated['search'] ?? null)) {
            $search = trim((string) $validated['search']);
            $query->where(function ($inner) use ($search): void {
                $inner
                    ->where('email', 'like', "%{$search}%")
                    ->orWhere('external_id', 'like', "%{$search}%")
                    ->orWhere('page', 'like', "%{$search}%")
                    ->orWhere('campaign', 'like', "%{$search}%");
            });
        }

        if (filled($validated['from'] ?? null)) {
            $query->where('created_at', '>=', Carbon::parse($validated['from'])->startOfDay());
        }

        if (filled($validated['to'] ?? null)) {
            $query->where('created_at', '<=', $this->endOfPeriod((string) $validated['to']));
        }

        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);
        $leads = $query->paginate($perPage);

        return new JsonResponse([
            'data' => $leads->getCollection()
                ->map(fn (object $lead): array => $this->present($lead))
                ->all(),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'per_page' => $leads->perPage(),
                'total' => $leads->total(),
                // Volontairement GLOBAL (non filtré) : ce sont les compteurs
                // d'onglets de l'écran admin. Filtrer dessus les rendrait
                // inutilisables dès qu'un filtre de statut est actif.
                'status_counts' => $this->statusCounts(),
            ],
        ]);
    }

    /**
     * Borne haute d'une période.
     *
     * `to=2026-09-17` doit inclure la JOURNÉE du 17, pas s'arrêter à minuit :
     * `Carbon::parse()` seul placerait la borne à 00:00:00 et une journée
     * entière de leads disparaîtrait silencieusement du filtre. Une borne qui
     * porte déjà une heure (`2026-09-17T10:00:00Z`) est respectée telle quelle.
     */
    private function endOfPeriod(string $to): Carbon
    {
        $bound = Carbon::parse($to);

        return str_contains($to, ':') ? $bound : $bound->endOfDay();
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        return DB::table(self::LEADS_TABLE)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $lead): array
    {
        return [
            'id' => (int) $lead->id,
            'external_id' => $lead->external_id,
            'type' => $lead->type,
            'email' => $lead->email,
            'locale' => $lead->locale,
            'country' => $lead->country,
            'page' => $lead->page,
            'source' => $lead->source,
            'campaign' => $lead->campaign,
            'referrer' => $lead->referrer,
            'ip' => $lead->ip,
            'status' => $lead->status,
            'note' => $lead->note,
            'converted_company_id' => $lead->converted_company_id,
            'crm_forwarded' => (bool) $lead->crm_forwarded,
            'email_forwarded' => (bool) $lead->email_forwarded,
            'payload' => $this->decodePayload($lead->payload),
            'captured_at' => $this->iso($lead->captured_at),
            'created_at' => $this->iso($lead->created_at),
        ];
    }

    /**
     * `payload` est un `jsonb` : lu par le query builder (et non par Eloquent),
     * PDO le rend en **chaîne** — le cast `array` du modèle ne s'applique pas.
     * Sans ce décodage, la SPA recevrait le message de contact sous forme de
     * chaîne JSON échappée.
     */
    private function decodePayload(mixed $payload): mixed
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->toIso8601String();
    }
}
