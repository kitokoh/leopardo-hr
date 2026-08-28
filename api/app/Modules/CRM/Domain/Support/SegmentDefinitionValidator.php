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
        $this->failIf(
            array_keys($definition) !== ['operator', 'conditions'],
            'La définition doit contenir uniquement `operator` et `conditions`.',
        );

        /** @var mixed $operator */
        $operator = $definition['operator'];
        $this->failIf(! is_string($operator) || ! in_array($operator, ['and', 'or'], true), 'operator doit être "and" ou "or".');

        /** @var mixed $conditions */
        $conditions = $definition['conditions'];
        $this->failIf(! is_array($conditions), 'conditions doit être un tableau.');
        $this->failIf($conditions === [], 'conditions ne peut pas être vide.');
        $this->failIf(count($conditions) > self::MAX_CONDITIONS, sprintf('Maximum %d conditions par segment.', self::MAX_CONDITIONS));

        $normalized = [];
        foreach ($conditions as $index => $condition) {
            $normalized[] = $this->validateCondition($condition, $index);
        }

        /** @var array{operator: string, conditions: list<array{field: string, op: string, value: mixed}>} $result */
        $result = ['operator' => $operator, 'conditions' => $normalized];

        return $result;
    }

    /**
     * @param  mixed  $condition
     * @return array{field: string, op: string, value: mixed}
     */
    private function validateCondition(mixed $condition, int $index): array
    {
        $this->failIf(! is_array($condition), sprintf('Condition %d doit être un objet.', $index + 1));
        $this->failIf(
            array_keys($condition) !== ['field', 'op', 'value'],
            sprintf('Condition %d : seuls `field`, `op` et `value` sont acceptés.', $index + 1),
        );

        /** @var mixed $field */
        $field = $condition['field'];
        /** @var mixed $op */
        $op = $condition['op'];

        $this->failIf(! is_string($field) || ! array_key_exists($field, self::ALLOWED_FIELDS), sprintf('Condition %d : champ inconnu.', $index + 1));
        $this->failIf(! is_string($op) || ! in_array($op, self::ALLOWED_FIELDS[$field], true), sprintf('Condition %d : opérateur non autorisé pour ce champ.', $index + 1));

        $value = $condition['value'];
        $this->validateValue($field, $op, $value, $index);

        /** @var array{field: string, op: string, value: mixed} $normalized */
        $normalized = ['field' => $field, 'op' => $op, 'value' => $value];

        return $normalized;
    }

    /**
     * @param  mixed  $value
     */
    private function validateValue(string $field, string $op, mixed $value, int $index): void
    {
        if ($op === SegmentOperator::In->value) {
            $this->failIf(! is_array($value) || $value === [] || count($value) > 50, sprintf('Condition %d : `in` attend 1 à 50 valeurs.', $index + 1));
            foreach ($value as $v) {
                $this->failIf(! is_scalar($v), sprintf('Condition %d : valeurs du `in` doivent être scalaires.', $index + 1));
            }

            return;
        }

        if ($op === SegmentOperator::Between->value) {
            $this->failIf(! is_array($value) || count($value) !== 2, sprintf('Condition %d : `between` attend exactement 2 valeurs.', $index + 1));

            return;
        }

        if ($op === SegmentOperator::IsNull->value) {
            $this->failIf(! is_bool($value), sprintf('Condition %d : `is_null` attend un booléen.', $index + 1));

            return;
        }

        $this->failIf(! is_scalar($value), sprintf('Condition %d : valeur scalaire requise.', $index + 1));

        if ($field === 'crm_consents.has_consent') {
            /** @var mixed $value */
            $this->failIf(
                ! is_string($value) || ! in_array($value, ConsentChannel::values(), true),
                sprintf('Condition %d : canal de consentement inconnu.', $index + 1),
            );
        }
    }

    private function failIf(bool $condition, string $message): void
    {
        if ($condition) {
            throw ValidationException::withMessages(['definition' => $message]);
        }
    }
}
