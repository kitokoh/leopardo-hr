<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Contracts\SegmentContactSourceInterface;
use App\Modules\CRM\Domain\Enums\SegmentOperator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Source de contacts par défaut — Issue #5723.
 *
 * Évalue la définition validée d'un segment contre `crm_contacts` (+
 * `crm_consents` pour la condition `has_consent`), toujours tenant-scopée
 * (company_id courant) et via le query builder (valeurs liées — aucune
 * interpolation SQL ; la grammaire est déjà allowlistée par le validateur).
 *
 * Tant que les tables CRM (livrées par #5708/#5722) n'existent pas, la
 * source se déclare indisponible : le rebuild est un no-op documenté
 * (SegmentService), jamais un crash.
 */
final class CrmContactSegmentSource implements SegmentContactSourceInterface
{
    public function matchingContactIds(array $definition): array
    {
        if (! $this->supports($definition)) {
            return [];
        }

        if (! app()->bound('current_company')) {
            return [];
        }

        $company = currentCompany();

        $builder = DB::table('crm_contacts')
            ->select('id')
            ->where('company_id', $company->id);

        $this->applyConditions($builder, $definition);

        $ids = $builder->pluck('id');

        $result = [];
        foreach ($ids as $id) {
            $result[] = (int) $id;
        }

        return $result;
    }

    public function supports(array $definition): bool
    {
        if (! Schema::hasTable('crm_contacts')) {
            return false;
        }

        foreach ($definition['conditions'] as $condition) {
            if ($condition['field'] === 'crm_consents.has_consent' && ! Schema::hasTable('crm_consents')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{operator: string, conditions: list<array{field: string, op: string, value: mixed}>}  $definition
     */
    private function applyConditions(Builder $builder, array $definition): void
    {
        $conditions = $definition['conditions'];
        $operator = $definition['operator'];

        if ($operator === 'or') {
            $builder->where(function (Builder $group) use ($conditions): void {
                $first = true;
                foreach ($conditions as $condition) {
                    if ($first) {
                        $this->applyCondition($group, $condition);
                        $first = false;
                    } else {
                        $group->orWhere(function (Builder $nested) use ($condition): void {
                            $this->applyCondition($nested, $condition);
                        });
                    }
                }
            });

            return;
        }

        foreach ($conditions as $condition) {
            $this->applyCondition($builder, $condition);
        }
    }

    /**
     * @param  array{field: string, op: string, value: mixed}  $condition
     */
    private function applyCondition(Builder $builder, array $condition): void
    {
        /** @var string $field */
        $field = $condition['field'];
        /** @var string $op */
        $op = $condition['op'];
        $value = $condition['value'];

        if ($field === 'crm_consents.has_consent') {
            $this->applyConsentCondition($builder, (string) $value);

            return;
        }

        // Colonnes de crm_contacts — la grammaire est allowlistée par le
        // validateur : le champ est forcément dans la liste blanche.
        $column = str_replace('crm_contacts.', '', $field);

        switch ($op) {
            case SegmentOperator::Eq->value:
                $builder->where($column, '=', $value);
                break;
            case SegmentOperator::Neq->value:
                $builder->where($column, '!=', $value);
                break;
            case SegmentOperator::In->value:
                $builder->whereIn($column, is_array($value) ? $value : []);
                break;
            case SegmentOperator::Gte->value:
                $builder->where($column, '>=', $value);
                break;
            case SegmentOperator::Lte->value:
                $builder->where($column, '<=', $value);
                break;
            case SegmentOperator::Between->value:
                $builder->whereBetween($column, is_array($value) ? array_values($value) : []);
                break;
            case SegmentOperator::IsNull->value:
                if ($value === true) {
                    $builder->whereNull($column);
                } else {
                    $builder->whereNotNull($column);
                }
                break;
        }
    }

    /**
     * Condition de consentement : le contact doit avoir un consentement
     * `granted` actif sur le canal donné (finalité marketing).
     */
    private function applyConsentCondition(Builder $builder, string $channel): void
    {
        $company = currentCompany();

        $builder->whereExists(function (Builder $sub) use ($channel, $company): void {
            $sub->select(DB::raw(1))
                ->from('crm_consents')
                ->whereColumn('crm_consents.contact_id', 'crm_contacts.id')
                ->where('crm_consents.company_id', $company->id)
                ->where('crm_consents.channel', $channel)
                ->where('crm_consents.purpose', 'marketing')
                ->where('crm_consents.status', 'granted');
        });
    }
}
