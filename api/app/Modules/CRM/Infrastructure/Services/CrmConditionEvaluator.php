<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Modules\CRM\Domain\Enums\CrmAutomationOperator;
use Illuminate\Support\Arr;

/**
 * Évaluateur de conditions d'automatisation CRM (issue #5728).
 *
 * Opérateurs allowlistés (equals, not_equals, contains, exists,
 * greater_than, less_than) appliqués à des chemins du contexte (dot
 * notation). Toute condition inconnue est évaluée false (fail-closed) et
 * journalisée — une règle ne s'exécute jamais sur une condition non
 * reconnue.
 */
final class CrmConditionEvaluator
{
    /**
     * @param  array<int, array<string, mixed>>  $conditions
     * @param  array<string, mixed>  $context
     */
    public function evaluate(array $conditions, array $context): bool
    {
        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateOne($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $context
     */
    private function evaluateOne(array $condition, array $context): bool
    {
        $field = isset($condition['field']) && is_string($condition['field']) ? $condition['field'] : '';
        $operator = isset($condition['operator']) && is_string($condition['operator']) ? $condition['operator'] : '';

        if ($field === '' || ! CrmAutomationOperator::isValid($operator)) {
            \Illuminate\Support\Facades\Log::warning('CRM automation: condition inconnue (fail-closed)', $condition);

            return false;
        }

        $actual = Arr::get($context, $field);
        $expected = $condition['value'] ?? null;

        return match ($operator) {
            CrmAutomationOperator::EQUALS => $actual == $expected,
            CrmAutomationOperator::NOT_EQUALS => $actual != $expected,
            CrmAutomationOperator::CONTAINS => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            CrmAutomationOperator::EXISTS => $actual !== null,
            CrmAutomationOperator::GREATER_THAN => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            CrmAutomationOperator::LESS_THAN => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            default => false,
        };
    }
}
