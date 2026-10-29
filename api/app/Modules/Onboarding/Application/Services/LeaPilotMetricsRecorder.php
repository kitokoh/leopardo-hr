<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Throwable;

/**
 * #8232 (BOS-044) — enregistrement des événements du pilote Léa.
 *
 * ## Pourquoi un service dédié plutôt qu'un `Log::info` de plus
 *
 * Le parcours Léa journalise déjà chaque étape. Ces logs ne sont pas
 * interrogeables en SQL : mesurer « taux de fallback < 30 % » sur la fenêtre
 * du pilote demanderait d'aspirer des fichiers. Ce service écrit la même
 * information dans une table d'événements, en ÉNUMÉRATIONS seulement.
 *
 * ## Jamais bloquant
 *
 * L'instrumentation ne doit pas pouvoir casser le parcours d'inscription :
 * une erreur d'écriture est avalée et rapportée. Un pilote qui perd une
 * mesure est un pilote imparfait ; un pilote qui empêche un client de
 * s'inscrire est un incident de production.
 *
 * ## Ce qui n'est jamais stocké
 *
 * Aucune description libre, aucune réponse d'entretien, aucun texte généré.
 * Uniquement `stage`, `outcome`, `reason_code`, `correlation_id` et des
 * compteurs — c'est la revue RGPD du critère d'acceptation.
 */
final class LeaPilotMetricsRecorder
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public const STAGE_INTERPRET = 'interpret';

    public const STAGE_CLARIFY = 'clarify';

    public const STAGE_CONFIRM = 'confirm';

    /**
     * @param  array{clarification_rounds?: int, answers_modified?: bool, via_lea?: bool}  $context
     */
    public function record(
        string $companyId,
        string $stage,
        string $outcome,
        ?string $reasonCode = null,
        ?string $correlationId = null,
        array $context = [],
    ): void {
        try {
            if (! schemaTableExists('lea_pilot_events')) {
                return;
            }

            $this->db->table('lea_pilot_events')->insert([
                'company_id' => $companyId,
                'stage' => $stage,
                'outcome' => $outcome,
                'reason_code' => $reasonCode,
                'correlation_id' => $correlationId,
                'via_lea' => $context['via_lea'] ?? true,
                'clarification_rounds' => max(0, (int) ($context['clarification_rounds'] ?? 0)),
                'answers_modified' => (bool) ($context['answers_modified'] ?? false),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // La mesure ne doit jamais empêcher l'inscription.
            report($exception);
        }
    }
}
