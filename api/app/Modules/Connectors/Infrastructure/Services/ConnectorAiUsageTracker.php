<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Infrastructure\Services;

use App\Modules\Connectors\Domain\Exceptions\ConnectorAiBudgetExceededException;
use App\Modules\Connectors\Domain\Models\ConnectorAiUsage;
use App\Modules\Connectors\Domain\Models\ConnectorConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * #9925 (BC-14 INTEGRATION) — suivi et plafonnement des tokens d'une connexion IA.
 *
 * Deux responsabilités, volontairement séparées de la couche HTTP :
 *
 *  1. **Enregistrer** la consommation réelle d'un appel (`record`) : on
 *     incrémente la ligne du mois courant, jamais on ne la duplique.
 *
 *  2. **Décider** si un appel est encore autorisé (`assertWithinBudget`) :
 *     le budget mensuel est optionnel (`monthly_token_budget` de la config de
 *     la connexion). Absent ou vide ⇒ illimité. Dépassé ⇒ refus **fail-closed**
 *     (`ConnectorAiBudgetExceededException`), jamais un appel silencieux.
 *
 * Le service ne lit jamais l'utilisateur authentifié : le tenant est porté par
 * le scope `BelongsToCompany` de `ConnectorAiUsage`, ce qui le rend testable
 * sans requête HTTP.
 */
class ConnectorAiUsageTracker
{
    /**
     * Enregistre la consommation d'un appel et rend le cumul du mois.
     *
     * L'incrément est atomique (`increment` SQL) : deux appels concurrents ne
     * peuvent pas écraser mutuellement leur compteur.
     *
     * @return array{input_tokens: int, output_tokens: int, total_tokens: int}
     */
    public function record(ConnectorConnection $connection, int $inputTokens, int $outputTokens, ?Carbon $at = null): array
    {
        $inputTokens = max(0, $inputTokens);
        $outputTokens = max(0, $outputTokens);
        $period = ConnectorAiUsage::periodFor($at);

        // `firstOrCreate` puis `increment` : la ligne du mois existe ou est
        // créée, et l'incrément est fait en base (pas de read-modify-write en
        // PHP qui perdrait des tokens sous concurrence).
        $usage = ConnectorAiUsage::query()->firstOrCreate(
            [
                'connection_id' => $connection->id,
                'period' => $period,
            ],
            [
                'company_id' => $connection->company_id,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'total_tokens' => 0,
            ],
        );

        if ($inputTokens > 0) {
            $usage->increment('input_tokens', $inputTokens);
        }
        if ($outputTokens > 0) {
            $usage->increment('output_tokens', $outputTokens);
        }
        if ($inputTokens + $outputTokens > 0) {
            $usage->increment('total_tokens', $inputTokens + $outputTokens);
        }

        $usage->refresh();

        return [
            'input_tokens' => $usage->input_tokens,
            'output_tokens' => $usage->output_tokens,
            'total_tokens' => $usage->total_tokens,
        ];
    }

    /**
     * Cumul de tokens du mois courant pour une connexion.
     */
    public function monthlyTotal(ConnectorConnection $connection, ?Carbon $at = null): int
    {
        $usage = ConnectorAiUsage::query()
            ->where('connection_id', $connection->id)
            ->where('period', ConnectorAiUsage::periodFor($at))
            ->first();

        if ($usage === null) {
            return 0;
        }

        return $usage->total_tokens;
    }

    /**
     * Budget mensuel déclaré par la connexion, ou `null` si illimité.
     *
     * Le budget vit dans `config.monthly_token_budget` (champ non secret du
     * schéma `ai_*`). Une valeur non numérique ou négative est traitée comme
     * « illimité » : un budget mal saisi ne doit pas bloquer tous les appels.
     */
    public function monthlyBudget(ConnectorConnection $connection): ?int
    {
        $raw = $connection->config['monthly_token_budget'] ?? null;

        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $budget = (int) $raw;

        return $budget > 0 ? $budget : null;
    }

    /**
     * Refuse l'appel si le budget mensuel est déjà atteint.
     *
     * Fail-closed : on compare le cumul du mois au budget AVANT l'appel. Un
     * budget absent ⇒ aucun refus. Le message porte les chiffres réels, pour
     * que l'utilisateur sache combien il a consommé et quelle est sa limite.
     *
     * @throws ConnectorAiBudgetExceededException
     */
    public function assertWithinBudget(ConnectorConnection $connection, ?Carbon $at = null): void
    {
        $budget = $this->monthlyBudget($connection);

        if ($budget === null) {
            return;
        }

        $used = $this->monthlyTotal($connection, $at);

        if ($used >= $budget) {
            throw new ConnectorAiBudgetExceededException($used, $budget);
        }
    }

    /**
     * Consommation de toutes les connexions IA du tenant courant, indexée par
     * `connection_id`, pour le mois demandé (défaut : courant).
     *
     * @return array<int, array{input_tokens: int, output_tokens: int, total_tokens: int}>
     */
    public function usageByConnection(?string $period = null): array
    {
        $period ??= ConnectorAiUsage::periodFor();

        /** @var \Illuminate\Support\Collection<int, ConnectorAiUsage> $rows */
        $rows = ConnectorAiUsage::query()
            ->where('period', $period)
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[$row->connection_id] = [
                'input_tokens' => $row->input_tokens,
                'output_tokens' => $row->output_tokens,
                'total_tokens' => $row->total_tokens,
            ];
        }

        return $result;
    }

    /**
     * Réinitialise la consommation d'une connexion pour un mois (outil
     * d'administration / test). Rend le nombre de lignes supprimées.
     */
    public function reset(ConnectorConnection $connection, ?string $period = null): int
    {
        $query = ConnectorAiUsage::query()->where('connection_id', $connection->id);

        if ($period !== null) {
            $query->where('period', $period);
        }

        return (int) $query->delete();
    }
}