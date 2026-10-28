<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * #7595 — Analytique de la vitrine pour le webadmin.
 *
 * Contrat SPA : `GET /admin/vitrine/analytics` (super-admin,
 * `platform.permission:metrics.view`). Écran distinct de `AnalyticsView`
 * (route `/analytics`), qui ne montre que les KPI plateforme — c'est
 * explicitement demandé par l'issue (« ne pas dupliquer AnalyticsView »).
 *
 * ## Pourquoi tout est lu en SQL agrégé et non via les modèles
 *
 * `vitrine_page_views` et `acquisition_funnel_events` vivent dans le schéma
 * public, hors de tout tenant. Les charger en Eloquent pour les additionner en
 * PHP ramènerait 90 jours de lignes pour produire quatre nombres. Les trois
 * requêtes ci-dessous sont couvertes par les index posés en migration.
 *
 * Isolation des modules (ARCHITECTURE.md §2, garde #5584) : aucune classe du
 * module Marketing n'est importée — lecture par `DB::table()`, même pattern que
 * `PlatformAcquisitionFunnelController` (#7496).
 *
 * ## Pourquoi les taux de conversion valent `null` et pas `0`
 *
 * Quand une étape amont a zéro événement sur la période, le ratio n'existe pas.
 * Afficher `0 %` ferait croire à un effondrement du tunnel ; `null` permet à
 * l'écran d'afficher « — ». Le calcul reste fait côté serveur pour que les
 * deux surfaces (API, écran) ne puissent pas diverger sur cette règle.
 */
final class PlatformVitrineAnalyticsController extends Controller
{
    private const VIEWS_TABLE = 'vitrine_page_views';

    private const FUNNEL_TABLE = 'acquisition_funnel_events';

    private const LEADS_TABLE = 'marketing_leads';

    private const DEFAULT_DAYS = 30;

    private const MAX_DAYS = 90;

    /** Top pages / top sources renvoyés : au-delà, l'écran ne lit plus. */
    private const TOP_LIMIT = 10;

    /** Derniers leads consultables dans l'écran. */
    private const RECENT_LEADS_LIMIT = 20;

    /**
     * Étapes séquentielles du tunnel dont on calcule le taux de passage.
     * Miroir de `PlatformAcquisitionFunnelController::CONVERSION_STEPS` — la
     * liste est dupliquée, pas importée (frontière de module).
     */
    private const CONVERSION_STEPS = [
        'signup_view',
        'signup_email_submitted',
        'signup_otp_sent',
        'signup_otp_verified',
        'space_provisioned',
    ];

    public function index(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', self::DEFAULT_DAYS);
        $days = min(max($days, 1), self::MAX_DAYS);

        $from = now()->subDays($days)->startOfDay();

        return new JsonResponse([
            'data' => [
                'period' => [
                    'days' => $days,
                    'from' => $from->toIso8601String(),
                    'to' => now()->toIso8601String(),
                ],
                'traffic' => $this->traffic($from),
                'top_pages' => $this->topPages($from),
                'sources' => $this->sources($from),
                'conversion' => $this->conversion($from),
                'recent_leads' => $this->recentLeads($from),
            ],
        ]);
    }

    /**
     * Volumes globaux de la période.
     *
     * `visits` est la somme des vues : sans identifiant de session (choix de
     * confidentialité assumé côté collecte), on ne peut pas compter des
     * visiteurs uniques. Le contrat le dit explicitement pour que l'écran
     * n'affiche jamais « visiteurs uniques » à partir de ce nombre.
     *
     * @return array{views: int, pages: int, days_tracked: int}
     */
    private function traffic(\DateTimeInterface $from): array
    {
        /** @var object{views: int|string|null, pages: int|string|null, days_tracked: int|string|null}|null $row */
        $row = DB::table(self::VIEWS_TABLE)
            ->where('day', '>=', $from->format('Y-m-d'))
            ->selectRaw('COALESCE(SUM(views), 0) AS views, COUNT(DISTINCT page_path) AS pages, COUNT(DISTINCT day) AS days_tracked')
            ->first();

        return [
            'views' => (int) ($row->views ?? 0),
            'pages' => (int) ($row->pages ?? 0),
            'days_tracked' => (int) ($row->days_tracked ?? 0),
        ];
    }

    /**
     * @return list<array{path: string, views: int}>
     */
    private function topPages(\DateTimeInterface $from): array
    {
        $rows = DB::table(self::VIEWS_TABLE)
            ->where('day', '>=', $from->format('Y-m-d'))
            ->selectRaw('page_path, SUM(views) AS views')
            ->groupBy('page_path')
            ->orderByDesc('views')
            ->limit(self::TOP_LIMIT)
            ->get();

        $pages = [];
        foreach ($rows as $row) {
            $pages[] = [
                'path' => (string) $row->page_path,
                'views' => (int) $row->views,
            ];
        }

        return $pages;
    }

    /**
     * Répartition par source d'acquisition.
     *
     * `direct` regroupe les vues sans referrer ni `utm_source` : c'est un
     * groupe réel (accès direct, favoris, apps de messagerie qui suppriment
     * l'en-tête), pas une donnée manquante.
     *
     * @return list<array{source: string, views: int}>
     */
    private function sources(\DateTimeInterface $from): array
    {
        $rows = DB::table(self::VIEWS_TABLE)
            ->where('day', '>=', $from->format('Y-m-d'))
            ->selectRaw("COALESCE(source, 'direct') AS source, SUM(views) AS views")
            ->groupBy(DB::raw("COALESCE(source, 'direct')"))
            ->orderByDesc('views')
            ->limit(self::TOP_LIMIT)
            ->get();

        $sources = [];
        foreach ($rows as $row) {
            $sources[] = [
                'source' => (string) $row->source,
                'views' => (int) $row->views,
            ];
        }

        return $sources;
    }

    /**
     * Taux de passage par étape du tunnel (#7496), étape par étape.
     *
     * @return list<array{step: string, events: int, rate_from_previous: float|null, rate_from_start: float|null}>
     */
    private function conversion(\DateTimeInterface $from): array
    {
        $rows = DB::table(self::FUNNEL_TABLE)
            ->where('occurred_at', '>=', $from)
            ->whereIn('event', self::CONVERSION_STEPS)
            ->selectRaw('event, COUNT(DISTINCT correlation_id) AS journeys')
            ->groupBy('event')
            ->get()
            ->keyBy('event');

        $steps = [];
        $startCount = null;
        $previousCount = null;

        foreach (self::CONVERSION_STEPS as $step) {
            $row = $rows->get($step);
            $count = is_object($row) ? (int) $row->journeys : 0;

            if ($startCount === null) {
                $startCount = $count;
            }

            $steps[] = [
                'step' => $step,
                'events' => $count,
                // `null` quand le dénominateur est nul : le ratio n'existe pas
                // (cf. docblock de classe) — surtout pas `0.0`.
                'rate_from_previous' => $previousCount !== null && $previousCount > 0
                    ? round($count / $previousCount, 4)
                    : null,
                'rate_from_start' => $startCount > 0
                    ? round($count / $startCount, 4)
                    : null,
            ];

            $previousCount = $count;
        }

        return $steps;
    }

    /**
     * Derniers leads vitrine devenus consultables (#7595).
     *
     * Avant cette issue, `marketing_leads` était écrit par la vitrine et
     * JAMAIS relu : aucun `GET` n'existait côté tenant. Le webadmin peut
     * désormais les voir. Aucune PII au-delà de ce que le prospect a lui-même
     * soumis — pas de champ enrichi, pas d'IP.
     *
     * @return list<array<string, mixed>>
     */
    private function recentLeads(\DateTimeInterface $from): array
    {
        $rows = DB::table(self::LEADS_TABLE)
            ->where('created_at', '>=', $from)
            ->orderByDesc('created_at')
            ->limit(self::RECENT_LEADS_LIMIT)
            ->get();

        $leads = [];
        foreach ($rows as $row) {
            // Les champs libres du formulaire (nom, société) vivent dans
            // `payload` : la table ne normalise que `email`, `type`, `source`
            // et l'attribution. On ne les promeut pas en colonnes — ce serait
            // une migration de données pour un écran de consultation.
            $payload = $row->payload ?? null;
            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                $payload = is_array($decoded) ? $decoded : [];
            }
            $payload = is_array($payload) ? $payload : [];

            $leads[] = [
                'id' => $row->id ?? null,
                'type' => $row->type ?? null,
                'status' => $row->status ?? null,
                'email' => $row->email ?? null,
                'name' => is_string($payload['name'] ?? null) ? $payload['name'] : null,
                'company_name' => is_string($payload['company'] ?? $payload['company_name'] ?? null)
                    ? ($payload['company'] ?? $payload['company_name'])
                    : null,
                'source' => $row->source ?? null,
                'campaign' => $row->campaign ?? null,
                'page' => $row->page ?? null,
                'created_at' => isset($row->created_at) ? (string) $row->created_at : null,
            ];
        }

        return $leads;
    }
}
