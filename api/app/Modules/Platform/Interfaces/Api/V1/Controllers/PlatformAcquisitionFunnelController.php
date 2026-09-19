<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * #7496 — Conversions du funnel d'acquisition, par étape / jour / source.
 *
 * Contrat SPA admin-dashboard : GET /admin/funnel/stats (super-admin,
 * `platform.permission:metrics.view`). Agrège la table globale
 * `acquisition_funnel_events` (schéma public), alimentée par la vitrine via
 * `POST /funnel/events` (AcquisitionFunnelEventController).
 *
 * Isolation modules (ARCHITECTURE.md §2, garde #5584) : lecture via
 * `DB::table(...)`, aucun import du module Marketing — même pattern que
 * `PlatformSolutionSurveyStatsController`. Corollaire assumé : la liste des
 * étapes est dupliquée, pas importée.
 *
 * Alerte mailer (proposition 4 de #7496) : quand le taux
 * `signup_otp_verified / signup_otp_sent` du jour passe sous un seuil
 * (mailer en échec, déjà arrivé en prod), la réponse porte un drapeau
 * `alerts.otp_delivery.triggered` que la SPA affiche en bannière.
 */
final class PlatformAcquisitionFunnelController extends Controller
{
    private const TABLE = 'acquisition_funnel_events';

    /** Ordre canonique du plan de tracking (#7496). */
    private const FUNNEL_STEPS = [
        'signup_view',
        'signup_email_submitted',
        'signup_otp_sent',
        'signup_otp_verified',
        'space_provisioned',
        'welcome_seen',
        'interview_started',
        'interview_question_answered',
        'interview_skipped',
        'interview_completed',
        'interview_dismissed',
        'first_module_opened',
    ];

    /** Étapes séquentielles du tunnel pour les taux de passage. */
    private const CONVERSION_STEPS = [
        'signup_view',
        'signup_email_submitted',
        'signup_otp_sent',
        'signup_otp_verified',
        'space_provisioned',
    ];

    private const DEFAULT_DAYS = 30;

    private const MAX_DAYS = 90;

    private const MAX_SOURCES = 25;

    /**
     * Sous ce ratio vérifié/envoyé (et au moins OTP_ALERT_MIN_SENT envois du
     * jour), l'alerte « livraison OTP » se déclenche.
     */
    private const OTP_ALERT_RATIO = 0.5;

    private const OTP_ALERT_MIN_SENT = 5;

    public function index(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', self::DEFAULT_DAYS);
        $days = min(max($days, 1), self::MAX_DAYS);

        $from = now()->subDays($days)->startOfDay();

        // Comptages par étape : totaux + parcours distincts (une étape peut
        // être ré-émise dans un même parcours, ex. renvoi du code).
        /** @var array<string, object{total: int, journeys: int}> $byEvent */
        $byEvent = DB::table(self::TABLE)
            ->selectRaw('event, COUNT(*) AS total, COUNT(DISTINCT correlation_id) AS journeys')
            ->where('occurred_at', '>=', $from)
            ->groupBy('event')
            ->get()
            ->keyBy('event')
            ->all();

        $steps = [];
        $previousJourneys = null;
        foreach (self::FUNNEL_STEPS as $step) {
            $row = $byEvent[$step] ?? null;
            $journeys = $row !== null ? (int) $row->journeys : 0;
            $isConversionStep = in_array($step, self::CONVERSION_STEPS, true);
            $rate = null;
            if ($isConversionStep) {
                if ($previousJourneys !== null) {
                    $rate = $previousJourneys > 0 ? round($journeys / $previousJourneys, 4) : 0.0;
                }
                $previousJourneys = $journeys;
            }
            $steps[] = [
                'event' => $step,
                'events' => $row !== null ? (int) $row->total : 0,
                'journeys' => $journeys,
                'rate_from_previous' => $rate,
            ];
        }

        $viewJourneys = isset($byEvent['signup_view']) ? (int) $byEvent['signup_view']->journeys : 0;
        $provisionedJourneys = isset($byEvent['space_provisioned']) ? (int) $byEvent['space_provisioned']->journeys : 0;

        // Conversions par jour : visite → espace prêt (critère 2 de #7496).
        $byDayRows = DB::table(self::TABLE)
            ->selectRaw('DATE(occurred_at) AS day, event, COUNT(DISTINCT correlation_id) AS journeys')
            ->where('occurred_at', '>=', $from)
            ->whereIn('event', self::CONVERSION_STEPS)
            ->groupBy('day', 'event')
            ->orderBy('day')
            ->get();

        /** @var array<string, array<string, int>> $byDay */
        $byDay = [];
        foreach ($byDayRows as $row) {
            $day = (string) $row->day;
            $byDay[$day][(string) $row->event] = (int) $row->journeys;
        }
        $days_out = [];
        foreach ($byDay as $day => $events) {
            $views = $events['signup_view'] ?? 0;
            $provisioned = $events['space_provisioned'] ?? 0;
            $days_out[] = [
                'date' => $day,
                'steps' => $events,
                'conversion_rate' => $views > 0 ? round($provisioned / $views, 4) : 0.0,
            ];
        }

        // Conversions par source (attribution captée au premier écran).
        $bySourceRows = DB::table(self::TABLE)
            ->selectRaw("COALESCE(NULLIF(source, ''), 'direct') AS source_key, event, COUNT(DISTINCT correlation_id) AS journeys")
            ->where('occurred_at', '>=', $from)
            ->whereIn('event', self::CONVERSION_STEPS)
            ->groupBy('source_key', 'event')
            ->get();

        /** @var array<string, array<string, int>> $bySource */
        $bySource = [];
        foreach ($bySourceRows as $row) {
            $bySource[(string) $row->source_key][(string) $row->event] = (int) $row->journeys;
        }
        uasort($bySource, static fn (array $a, array $b): int => ($b['signup_view'] ?? 0) <=> ($a['signup_view'] ?? 0));
        $sources_out = [];
        foreach (array_slice($bySource, 0, self::MAX_SOURCES, true) as $source => $events) {
            $views = $events['signup_view'] ?? 0;
            $provisioned = $events['space_provisioned'] ?? 0;
            $sources_out[] = [
                'source' => $source,
                'steps' => $events,
                'conversion_rate' => $views > 0 ? round($provisioned / $views, 4) : 0.0,
            ];
        }

        // Alerte livraison OTP (jour courant uniquement : c'est un signal
        // d'exploitation, pas une statistique).
        /** @var array<string, int> $todayCounts */
        $todayCounts = DB::table(self::TABLE)
            ->selectRaw('event, COUNT(DISTINCT correlation_id) AS journeys')
            ->where('occurred_at', '>=', now()->startOfDay())
            ->whereIn('event', ['signup_otp_sent', 'signup_otp_verified'])
            ->groupBy('event')
            ->pluck('journeys', 'event')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        $otpSent = $todayCounts['signup_otp_sent'] ?? 0;
        $otpVerified = $todayCounts['signup_otp_verified'] ?? 0;
        $otpRatio = $otpSent > 0 ? round($otpVerified / $otpSent, 4) : null;
        $otpTriggered = $otpSent >= self::OTP_ALERT_MIN_SENT
            && $otpRatio !== null
            && $otpRatio < self::OTP_ALERT_RATIO;

        return new JsonResponse([
            'data' => [
                'window' => [
                    'days' => $days,
                    'from' => $from->toIso8601String(),
                ],
                'totals' => [
                    'journeys' => $viewJourneys,
                    'provisioned' => $provisionedJourneys,
                    'conversion_rate' => $viewJourneys > 0 ? round($provisionedJourneys / $viewJourneys, 4) : 0.0,
                ],
                'steps' => $steps,
                'by_day' => $days_out,
                'by_source' => $sources_out,
                'alerts' => [
                    'otp_delivery' => [
                        'sent_today' => $otpSent,
                        'verified_today' => $otpVerified,
                        'ratio' => $otpRatio,
                        'threshold' => self::OTP_ALERT_RATIO,
                        'triggered' => $otpTriggered,
                    ],
                ],
            ],
        ]);
    }
}
