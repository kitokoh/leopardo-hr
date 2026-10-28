<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Application\Services\VitrineAnalyticsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * #7595 — Ingestion des pages vues de la vitrine (analytique first-party).
 *
 * Appelé serveur-à-serveur par la vitrine, protégé par le même secret partagé
 * que les leads marketing (`services.marketing_lead_webhook.secret`) et borné
 * par `throttle:webhooks-inbound`. Même arbitrage que #7301 / #7496 : secret
 * non configuré → accepté avec avertissement (perdre la mesure au moment des
 * campagnes serait pire), secret configuré → fail-closed.
 *
 * ## Le consentement est une condition d'écriture, pas un champ décoratif
 *
 * Le critère d'acceptation de #7595 est explicite : « aucune donnée collectée
 * sans consentement de l'utilisateur ». Le payload porte donc un booléen
 * `consent` ; s'il est faux ou absent, la requête répond **202 sans écrire**.
 *
 * Ce choix mérite d'être justifié, parce que la tentation inverse était
 * réelle : « la page vue n'est pas une donnée personnelle, on peut la compter
 * quand même ». C'est faux ici — une visite agrégée par jour, page, source et
 * campagne reste une mesure d'audience, soumise au consentement dans la
 * juridiction de référence du produit (et c'est la doctrine déjà retenue par
 * `trackFunnelStep()` côté vitrine, #7593). Écrire malgré un refus créerait
 * une divergence entre ce que la vitrine affiche à la personne et ce que le
 * serveur fait réellement — exactement ce qu'un audit relèverait.
 *
 * Un 202 (et non un 4xx) sur refus de consentement est volontaire : la vitrine
 * doit pouvoir envoyer son lot sans traiter un refus utilisateur comme une
 * erreur applicative, et sans réessayer indéfiniment.
 */
final class VitrineAnalyticsController extends Controller
{
    /** Longueur maximale d'un lot : un flush de page vue, pas un import. */
    private const MAX_EVENTS_PER_REQUEST = 25;

    public function __construct(private readonly VitrineAnalyticsRecorder $recorder) {}

    public function store(Request $request): JsonResponse
    {
        $configuredSecret = (string) config('services.marketing_lead_webhook.secret', '');

        if ($configuredSecret === '') {
            Log::warning('Vitrine analytics ingest: shared secret not configured — accepting unauthenticated view', [
                'endpoint' => 'POST /api/v1/vitrine/analytics/page-views',
            ]);
        } elseif (! hash_equals($configuredSecret, $this->extractSecret($request))) {
            Log::warning('Vitrine analytics ingest: invalid or missing shared secret');

            return new JsonResponse(['error' => 'Invalid signature'], 400);
        }

        $validated = $request->validate([
            // Consentement : requis, et refusé => aucune écriture (cf. docblock).
            'consent' => ['required', 'boolean'],
            'events' => ['required', 'array', 'min:1', 'max:'.self::MAX_EVENTS_PER_REQUEST],
            'events.*.path' => ['required', 'string', 'max:300'],
            'events.*.source' => ['sometimes', 'nullable', 'string', 'max:120'],
            'events.*.referrer' => ['sometimes', 'nullable', 'string', 'max:500'],
            'events.*.utm_medium' => ['sometimes', 'nullable', 'string', 'max:120'],
            'events.*.utm_campaign' => ['sometimes', 'nullable', 'string', 'max:120'],
            'events.*.occurred_at' => ['sometimes', 'nullable', 'date'],
        ]);

        if ($validated['consent'] !== true) {
            // Refus explicite : on acquitte, on n'écrit rien, et on ne trace
            // PAS les chemins refusés (une trace serait déjà une collecte).
            return new JsonResponse(['received' => true, 'stored' => 0, 'consent' => false], 202);
        }

        $appHost = $this->appHost($request);
        $stored = 0;

        /** @var array<int, array<string, mixed>> $events */
        $events = $validated['events'];

        foreach ($events as $event) {
            $occurredAt = is_string($event['occurred_at'] ?? null)
                ? \Illuminate\Support\Carbon::parse((string) $event['occurred_at'])
                : null;

            // Une source explicite (utm_source) prime sur le referrer : c'est
            // l'intention déclarée par la campagne, plus fiable que l'en-tête
            // navigateur, souvent absent en navigation privée ou depuis une app.
            $source = VitrineAnalyticsRecorder::normalizeAttribution($event['source'] ?? null)
                ?? VitrineAnalyticsRecorder::normalizeReferrer(
                    isset($event['referrer']) && is_string($event['referrer']) ? $event['referrer'] : null,
                    $appHost,
                );

            $this->recorder->record(
                pagePath: (string) $event['path'],
                source: $source,
                utmMedium: VitrineAnalyticsRecorder::normalizeAttribution($event['utm_medium'] ?? null),
                utmCampaign: VitrineAnalyticsRecorder::normalizeAttribution($event['utm_campaign'] ?? null),
                occurredAt: $occurredAt,
            );

            $stored++;
        }

        return new JsonResponse(['received' => true, 'stored' => $stored, 'consent' => true], 202);
    }

    /**
     * Hôte de la vitrine, pour ne pas compter ses propres pages comme source
     * externe. Lu depuis la configuration, jamais depuis l'en-tête `Host`
     * (qui est contrôlable par l'appelant).
     */
    private function appHost(Request $request): ?string
    {
        $configured = config('services.vitrine.host');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $referer = (string) $request->header('Referer', '');
        $host = $referer !== '' ? parse_url($referer, PHP_URL_HOST) : null;

        return is_string($host) && $host !== '' ? $host : null;
    }

    private function extractSecret(Request $request): string
    {
        $header = (string) $request->header('X-Marketing-Lead-Token', '');

        if ($header !== '') {
            return $header;
        }

        $authorization = (string) $request->header('Authorization', '');

        if (str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        return '';
    }
}
