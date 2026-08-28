<?php

declare(strict_types=1);

namespace Tests\Unit\CRM;

use App\Modules\CRM\Domain\Support\SegmentDefinitionValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Issue #5723 — grammaire de définition de segment (aucun SQL utilisateur).
 *
 * Le validateur est la frontière de sécurité du module segments : champ
 * inconnu, opérateur non autorisé, clé parasite ou valeur malformée = refus
 * (ValidationException `definition`).
 */
class SegmentDefinitionValidatorTest extends TestCase
{
    private SegmentDefinitionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SegmentDefinitionValidator();
    }

    public function test_valid_and_definition_passes(): void
    {
        $definition = [
            'operator' => 'and',
            'conditions' => [
                ['field' => 'crm_contacts.status', 'op' => 'eq', 'value' => 'active'],
                ['field' => 'crm_contacts.country', 'op' => 'in', 'value' => ['DZ', 'MA']],
            ],
        ];

        $normalized = $this->validator->validate($definition);

        $this->assertSame('and', $normalized['operator']);
        $this->assertCount(2, $normalized['conditions']);
    }

    public function test_valid_or_definition_with_consent_passes(): void
    {
        $definition = [
            'operator' => 'or',
            'conditions' => [
                ['field' => 'crm_contacts.source', 'op' => 'eq', 'value' => 'linkedin'],
                ['field' => 'crm_consents.has_consent', 'op' => 'eq', 'value' => 'email'],
            ],
        ];

        $normalized = $this->validator->validate($definition);

        $this->assertSame('or', $normalized['operator']);
    }

    public function test_unknown_field_is_rejected(): void
    {
        $definition = [
            'operator' => 'and',
            'conditions' => [
                ['field' => 'crm_contacts.secret_column', 'op' => 'eq', 'value' => 1],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($definition);
    }

    public function test_sql_fragment_as_field_is_rejected(): void
    {
        $definition = [
            'operator' => 'and',
            'conditions' => [
                ['field' => "1=1; DROP TABLE crm_segments; --", 'op' => 'eq', 'value' => 1],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($definition);
    }

    public function test_operator_not_allowed_for_field_is_rejected(): void
    {
        $definition = [
            'operator' => 'and',
            'conditions' => [
                // `contains` n'est pas autorisé sur status.
                ['field' => 'crm_contacts.status', 'op' => 'contains', 'value' => 'act'],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($definition);
    }

    public function test_extra_keys_in_condition_are_rejected(): void
    {
        $definition = [
            'operator' => 'and',
            'conditions' => [
                ['field' => 'crm_contacts.status', 'op' => 'eq', 'value' => 'active', 'evil' => true],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($definition);
    }

    public function test_extra_top_level_key_is_rejected(): void
    {
        $definition = [
            'operator' => 'and',
            'conditions' => [['field' => 'crm_contacts.status', 'op' => 'eq', 'value' => 'active']],
            'limit' => 1,
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($definition);
    }

    public function test_invalid_operator_is_rejected(): void
    {
        $definition = [
            'operator' => 'xor',
            'conditions' => [['field' => 'crm_contacts.status', 'op' => 'eq', 'value' => 'active']],
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($definition);
    }

    public function test_empty_conditions_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(['operator' => 'and', 'conditions' => []]);
    }

    public function test_too_many_conditions_are_rejected(): void
    {
        $conditions = [];
        for ($i = 0; $i < 21; $i++) {
            $conditions[] = ['field' => 'crm_contacts.status', 'op' => 'eq', 'value' => 'active'];
        }

        $this->expectException(ValidationException::class);
        $this->validator->validate(['operator' => 'and', 'conditions' => $conditions]);
    }

    public function test_consent_channel_must_be_known(): void
    {
        $definition = [
            'operator' => 'and',
            'conditions' => [
                ['field' => 'crm_consents.has_consent', 'op' => 'eq', 'value' => 'pigeon'],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($definition);
    }

    public function test_between_requires_two_values(): void
    {
        $definition = [
            'operator' => 'and',
            'conditions' => [
                ['field' => 'crm_contacts.created_at', 'op' => 'between', 'value' => ['2026-01-01']],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($definition);
    }

    public function test_in_accepts_up_to_50_values(): void
    {
        $values = range(1, 50);
        $definition = [
            'operator' => 'and',
            'conditions' => [
                ['field' => 'crm_contacts.status', 'op' => 'in', 'value' => $values],
            ],
        ];

        $this->assertCount(1, $this->validator->validate($definition)['conditions']);
    }

    public function test_in_rejects_more_than_50_values(): void
    {
        $values = range(1, 51);
        $definition = [
            'operator' => 'and',
            'conditions' => [
                ['field' => 'crm_contacts.status', 'op' => 'in', 'value' => $values],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($definition);
    }
}
