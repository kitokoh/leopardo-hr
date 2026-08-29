<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Support;

use App\Modules\CRM\Domain\Enums\ConsentChannel;
use App\Modules\CRM\Domain\Enums\SegmentOperator;
use Illuminate\Validation\ValidationException;

/**
 * Grammaire de définition de segment — Issue #5723.
 *
 * La définition est un JSONB strictement allowlisté (jamais de SQL
 * utilisateur, jamais de champ/opérateur inconnu) :
 *
 *     {
 *       "operator": "and|or",
 *       "conditions": [
 *         {"field": "crm_contacts.status", "op": "eq", "value": "active"},
 *         {"field": "crm_consents.has_consent", "op": "eq", "value": "email"}
 *       ]
 *     }
 *
 * Champs autorisés et opérateurs associés — toute autre combinaison est
 * refusée (ValidationException, message stable `definition`).
 */
final class SegmentDefinitionValidator
{
    public const MAX_CONDITIONS = 20;

    /** @var array<string, list<string>> */
    public const ALLOWED_FIELDS = [
        'crm_contacts.status' => ['eq', 'neq', 'in'],
        'crm_contacts.country' => ['eq', 'neq', 'in'],
        'crm_contacts.source' => ['eq', 'neq', 'in'],
        'crm_contacts.type' => ['eq', 'neq'],
        'crm_contacts.created_at' => ['gte', 'lte', 'between'],
        'crm_contacts.account_id' => ['eq', 'neq'],
        'crm_consents.has_consent' => ['eq'],
    ];

    /**
     * Valide et normalise la définition.
     *
     * @param  array<string, mixed>  $definition
     * @return array{operator: string, conditions: list<array{field: string, op: string, value: mixed}>}
     */
    public function validate(array $definition): array
    {
        if (array_keys($definition) !== ['operator', 'conditions']) {
            throw ValidationException::withMessages(['definition' => 'La définition doit contenir uniquement `operator` et `conditions`.']);
        }

        $operator = $definition['operator'];
        if (! is_string($operator) || ! in_array($operator, ['and', 'or'], true)) {
            throw ValidationException::withMessages(['definition' => 'operator doit être "and" ou "or".']);
        }

        $conditions = $definition['conditions'];
        if (! is_array($conditions)) {
            throw ValidationException::withMessages(['definition' => 'conditions doit être un tableau.']);
        }
        if ($conditions === []) {
            throw ValidationException::withMessages(['definition' => 'conditions ne peut pas être vide.']);
        }
        if (count($conditions) > self::MAX_CONDITIONS) {
            throw ValidationException::withMessages(['definition' => sprintf('Maximum %d conditions par segment.', self::MAX_CONDITIONS)]);
        }

        $normalized = [];
        foreach ($conditions as $index => $condition) {
            $normalized[] = $this->validateCondition($condition, (int) $index);
        }

        /** @var array{operator: string, conditions: list<array{field: string, op: string, value: mixed}>} $result */
        $result = ['operator' => $operator, 'conditions' => $normalized];

        return $result;
    }

    /**
     * @return array{field: string, op: string, value: mixed}
     */
    private function validateCondition(mixed $condition, int $index): array
    {
        if (! is_array($condition)) {
            throw ValidationException::withMessages(['definition' => sprintf('Condition %d doit être un objet.', $index + 1)]);
        }
        if (array_keys($condition) !== ['field', 'op', 'value']) {
            throw ValidationException::withMessages(['definition' => sprintf('Condition %d : seuls `field`, `op` et `value` sont acceptés.', $index + 1)]);
        }

        $field = $condition['field'];
        $op = $condition['op'];

        if (! is_string($field) || ! array_key_exists($field, self::ALLOWED_FIELDS)) {
            throw ValidationException::withMessages(['definition' => sprintf('Condition %d : champ inconnu.', $index + 1)]);
        }
        if (! is_string($op) || ! in_array($op, self::ALLOWED_FIELDS[$field], true)) {
            throw ValidationException::withMessages(['definition' => sprintf('Condition %d : opérateur non autorisé pour ce champ.', $index + 1)]);
        }

        $value = $condition['value'];
        $this->validateValue($field, $op, $value, $index);

        /** @var array{field: string, op: string, value: mixed} $normalized */
        $normalized = ['field' => $field, 'op' => $op, 'value' => $value];

        return $normalized;
    }

    private function validateValue(string $field, string $op, mixed $value, int $index): void
    {
        if ($op === SegmentOperator::In->value) {
            if (! is_array($value) || $value === [] || count($value) > 50) {
                throw ValidationException::withMessages(['definition' => sprintf('Condition %d : `in` attend 1 à 50 valeurs.', $index + 1)]);
            }
            foreach ($value as $v) {
                if (! is_scalar($v)) {
                    throw ValidationException::withMessages(['definition' => sprintf('Condition %d : valeurs du `in` doivent être scalaires.', $index + 1)]);
                }
            }

            return;
        }

        if ($op === SegmentOperator::Between->value) {
            if (! is_array($value) || count($value) !== 2) {
                throw ValidationException::withMessages(['definition' => sprintf('Condition %d : `between` attend exactement 2 valeurs.', $index + 1)]);
            }

            return;
        }

        if ($op === SegmentOperator::IsNull->value) {
            if (! is_bool($value)) {
                throw ValidationException::withMessages(['definition' => sprintf('Condition %d : `is_null` attend un booléen.', $index + 1)]);
            }

            return;
        }

        if (! is_scalar($value)) {
            throw ValidationException::withMessages(['definition' => sprintf('Condition %d : valeur scalaire requise.', $index + 1)]);
        }

        if ($field === 'crm_consents.has_consent') {
            if (! is_string($value) || ! in_array($value, ConsentChannel::values(), true)) {
                throw ValidationException::withMessages(['definition' => sprintf('Condition %d : canal de consentement inconnu.', $index + 1)]);
            }
        }
    }
}
