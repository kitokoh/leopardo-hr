<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * #7496 — Ingestion des événements d'étape du funnel d'acquisition.
 *
 * Appelé serveur-à-serveur par la route Next.js de la vitrine
 * (`front/web/src/app/api/forms/funnel-event/route.ts`), elle-même alimentée
 * par `trackFunnelStep()` (consentement mesure d'audience requis, #7593).
 * Public et non authentifié par nature (le prospect n'a pas encore de
 * tenant) : protégé par le même secret partagé que les leads marketing
 * (`services.marketing_lead_webhook.secret`) et par `throttle:webhooks-inbound`.
 *
 * Même arbitrage que #7301 : secret NON configuré → l'événement est accepté
 * (perdre la mesure du funnel au moment des campagnes est le pire des deux
 * maux) avec un log d'avertissement ; secret configuré → fail-closed (400).
 *
 * Critère 4 de #7496 : liste FERMÉE d'événements, aucune PII — le contrôleur
 * ne persiste que des clés, des compteurs et l'attribution.
 */
class AcquisitionFunnelEventController extends Controller
{
    private const TABLE = 'acquisition_funnel_events';

    /** Miroir de FUNNEL_EVENTS (front/web/src/modules/vitrine/lib/funnel.ts). */
    public const EVENTS = [
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

    /** Clés de contexte autorisées — tout le reste est ignoré (jamais stocké). */
    private const CONTEXT_KEYS = ['page', 'step_key', 'question_index', 'resend'];

    public function store(Request $request): JsonResponse
    {
        $configuredSecret = (string) config('services.marketing_lead_webhook.secret', '');

        if ($configuredSecret === '') {
            Log::warning('Funnel event ingest: shared secret not configured — accepting unauthenticated event', [
                'endpoint' => 'POST /api/v1/funnel/events',
            ]);
        } elseif (! hash_equals($configuredSecret, $this->extractSecret($request))) {
            Log::warning('Funnel event ingest: invalid or missing shared secret');

            return new JsonResponse(['error' => 'Invalid signature'], 400);
        }

        $validator = Validator::make($request->all(), [
            'event' => ['required', 'string', 'in:'.implode(',', self::EVENTS)],
            'correlation_id' => ['required', 'string', 'min:4', 'max:64'],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
            'attribution' => ['sometimes', 'array'],
            'attribution.source' => ['sometimes', 'string', 'max:120'],
            'attribution.utm_source' => ['sometimes', 'string', 'max:120'],
            'attribution.utm_medium' => ['sometimes', 'string', 'max:120'],
            'attribution.utm_campaign' => ['sometimes', 'string', 'max:120'],
            'context' => ['sometimes', 'array'],
            'context.page' => ['sometimes', 'string', 'max:300'],
            'context.step_key' => ['sometimes', 'string', 'max:120'],
            'context.question_index' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'context.resend' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return new JsonResponse([
                'error' => 'VALIDATION_ERROR',
                'details' => $validator->errors()->toArray(),
            ], 422);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        /** @var array<string, mixed> $attribution */
        $attribution = is_array($validated['attribution'] ?? null) ? $validated['attribution'] : [];
        /** @var array<string, mixed> $rawContext */
        $rawContext = is_array($validated['context'] ?? null) ? $validated['context'] : [];
        $context = array_intersect_key($rawContext, array_flip(self::CONTEXT_KEYS));

        $occurredAt = is_string($validated['occurred_at'] ?? null)
            ? \Illuminate\Support\Carbon::parse($validated['occurred_at'])
            : now();

        DB::table(self::TABLE)->insert([
            'event' => (string) $validated['event'],
            'correlation_id' => (string) $validated['correlation_id'],
            'source' => $this->stringOrNull($attribution['source'] ?? null),
            'utm_source' => $this->stringOrNull($attribution['utm_source'] ?? null),
            'utm_medium' => $this->stringOrNull($attribution['utm_medium'] ?? null),
            'utm_campaign' => $this->stringOrNull($attribution['utm_campaign'] ?? null),
            'context' => $context === [] ? null : json_encode($context),
            'occurred_at' => $occurredAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new JsonResponse(['received' => true], 202);
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

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
