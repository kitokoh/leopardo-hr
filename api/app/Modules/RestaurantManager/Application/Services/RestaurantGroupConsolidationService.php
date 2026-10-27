<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantReportService;
use Illuminate\Support\Carbon;

/**
 * REST-OS-8 (#8610) — Vue consolidée GROUPE (propriétaire / responsable
 * multi-établissements).
 *
 * Objectif de l'issue : « vue consolidée propriétaire/responsable groupe SANS
 * MÉLANGE D'OPÉRATIONS ». Le point dur n'est pas l'agrégation — c'est
 * l'ISOLATION : un responsable de groupe doit voir le total du groupe ET le
 * détail par établissement, sans qu'une opération d'une succursale soit
 * attribuée à une autre.
 *
 * Décisions de conception :
 *
 * 1. **Lecture seule, strictement.** Aucune écriture, aucun effet de bord :
 *    l'issue l'exige (« consolidation en lecture seule »). Cette classe ne
 *    fait que lire et agréger.
 *
 * 2. **Le périmètre vient de l'ACTEUR, jamais de la requête.** La liste des
 *    établissements consolidés est dérivée des assignations de ressources de
 *    l'acteur (`restaurant_branch`), pas d'un `branch_ids[]` fourni par le
 *    client. Un responsable ne peut donc pas élargir son périmètre en
 *    modifiant la requête — c'est la faille classique d'une vue groupe.
 *
 * 3. **Aucun mélange d'opérations.** Chaque agrégat est calculé PAR
 *    ÉTABLISSEMENT puis additionné ; on n'exécute jamais une requête globale
 *    « toutes branches confondues » dont on déduirait ensuite le détail. La
 *    somme des lignes est donc, par construction, égale au total — et un
 *    écart entre les deux serait un bug détectable, pas un arrondi.
 *
 * 4. **Les établissements sans activité sont LISTÉS à zéro**, pas omis : un
 *    responsable doit voir qu'une succursale n'a rien vendu (c'est
 *    l'information la plus actionnable), pas la voir disparaître du rapport.
 *
 * 5. **Fail-closed.** Un acteur sans périmètre exploitable obtient une liste
 *    vide, jamais un total groupe implicite.
 */
final class RestaurantGroupConsolidationService
{
    public function __construct(
        private readonly RestaurantReportService $reports,
    ) {}

    /**
     * Consolide les rapports du périmètre de l'acteur.
     *
     * @return array{
     *     branches: list<array{branch_id: int, branch_name: string, revenue_minor: int, orders_count: int, avg_basket_minor: int, tax_minor: int, discount_minor: int}>,
     *     totals: array{revenue_minor: int, orders_count: int, avg_basket_minor: int, tax_minor: int, discount_minor: int, branches_count: int},
     *     period: array{from: string, to: string}
     * }
     */
    public function consolidate(Employee $actor, Carbon $from, Carbon $to): array
    {
        $branchIds = $this->scopeBranchIds($actor);

        // Périmètre vide (scoping actif, aucune assignation) : on renvoie un
        // total à zéro plutôt qu'un agrégat entreprise — fail-closed.
        if ($branchIds === []) {
            return [
                'branches' => [],
                'totals' => $this->emptyTotals(),
                'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            ];
        }

        $branches = RestaurantBranch::query()
            ->where('company_id', $actor->company_id)
            ->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = [];
        $totals = $this->emptyTotals();

        foreach ($branches as $branch) {
            // Agrégat PAR établissement : c'est ce qui garantit l'absence de
            // mélange d'opérations entre succursales.
            $sales = $this->reports->sales(
                (string) $actor->company_id,
                $from,
                $to,
                (int) $branch->id,
            );

            $rows[] = [
                'branch_id' => (int) $branch->id,
                'branch_name' => (string) $branch->name,
                'revenue_minor' => $sales['revenue_minor'],
                'orders_count' => $sales['orders_count'],
                'avg_basket_minor' => $sales['avg_basket_minor'],
                'tax_minor' => $sales['tax_minor'],
                'discount_minor' => $sales['discount_minor'],
            ];

            $totals['revenue_minor'] += $sales['revenue_minor'];
            $totals['orders_count'] += $sales['orders_count'];
            $totals['tax_minor'] += $sales['tax_minor'];
            $totals['discount_minor'] += $sales['discount_minor'];
        }

        // Panier moyen du groupe : recalculé sur les TOTAUX, jamais moyenné à
        // partir des paniers moyens par établissement (une moyenne de moyennes
        // pondère à tort une petite succursale comme une grande).
        $totals['avg_basket_minor'] = $totals['orders_count'] > 0
            ? intdiv($totals['revenue_minor'], $totals['orders_count'])
            : 0;
        $totals['branches_count'] = count($rows);

        return [
            'branches' => $rows,
            'totals' => $totals,
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
        ];
    }

    /**
     * Périmètre d'établissements de l'acteur.
     *
     * `null` = aucune restriction (principal, ou scoping non actif) → toutes
     * les succursales de l'entreprise. Une liste = scoping actif → uniquement
     * les succursales assignées. Une liste VIDE = scoping actif sans
     * assignation → aucun accès (fail-closed).
     *
     * @return list<int>|null
     */
    private function scopeBranchIds(Employee $actor): ?array
    {
        $accessible = $actor->accessibleResourceIds(
            RestaurantPermissions::RESOURCE_TYPE,
            EmployeeResourceAssignment::LEVEL_VIEW,
        );

        if ($accessible === null) {
            return null;
        }

        return array_map('intval', $accessible);
    }

    /**
     * @return array{revenue_minor: int, orders_count: int, avg_basket_minor: int, tax_minor: int, discount_minor: int, branches_count: int}
     */
    private function emptyTotals(): array
    {
        return [
            'revenue_minor' => 0,
            'orders_count' => 0,
            'avg_basket_minor' => 0,
            'tax_minor' => 0,
            'discount_minor' => 0,
            'branches_count' => 0,
        ];
    }
}
