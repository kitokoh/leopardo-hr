<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCostAxis;
use App\Modules\FarmOs\Domain\Enums\FarmDataQuality;
use App\Modules\FarmOs\Domain\Enums\FarmExpenseCategory;
use App\Modules\FarmOs\Domain\Exceptions\FarmFinanceException;
use App\Modules\FarmOs\Domain\Models\FarmCampaign;
use App\Modules\FarmOs\Domain\Models\FarmExpense;
use App\Modules\FarmOs\Domain\Models\FarmHarvestLot;
use App\Modules\FarmOs\Domain\Models\FarmLivestockBatch;
use App\Modules\FarmOs\Domain\Models\FarmSaleLink;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T4.1 Achats, ventes, depenses et rentabilite.
 *
 * DEUX REGLES ABSOLUES portees par ce service :
 *
 * 1. AUCUN SYSTEME FINANCIER PARALLELE. Farm OS ne cree ni facture, ni paiement,
 *    ni ecriture comptable. Les documents commerciaux restent dans les services
 *    EXISTANTS de Leopardo ; Farm OS ne fait que les REFERENCER
 *    (`sale_document_id`, `purchase_document_id`, `supplier_id`) et stocke la
 *    quantite / le montant IMPUTES a un objet agricole. Un meme document ne peut
 *    pas etre impute deux fois au meme lot (garde anti-double-comptage du
 *    chiffre d'affaires).
 *
 * 2. HONNETETE DES CHIFFRES. Une rentabilite calculee sur des donnees
 *    incompletes ne doit JAMAIS etre presentee comme un resultat certain. Le
 *    service distingue donc TROIS etats et les expose separement :
 *      - `real`      : montant declare sur une piece reelle ;
 *      - `estimated` : montant estime par l'exploitant ;
 *      - `missing`   : donnee absente — jamais remplacee par zero.
 *    `is_reliable` n'est vrai que si AUCUNE donnee ne manque, et
 *    `missing_inputs` liste explicitement ce qui manque.
 *
 * Purete des couches (issue #6568) : aucune facade Laravel ici. La transaction
 * est portee par la connexion du modele (`getConnection()`).
 */
final class FarmProfitabilityService
{
    /**
     * Cree une campagne agricole.
     */
    public function createCampaign(
        Company $company,
        string $name,
        int $startYear,
        ?Carbon $startsOn = null,
        ?Carbon $endsOn = null,
        ?int $createdBy = null,
        ?string $clientCampaignId = null,
    ): FarmCampaign {
        $campaign = new FarmCampaign([
            'name' => $name,
            'start_year' => $startYear,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'status' => 'open',
            'client_campaign_id' => $clientCampaignId,
            'created_by' => $createdBy,
        ]);
        $campaign->company_id = $company->id;
        $campaign->save();

        return $campaign;
    }

    /**
     * Enregistre une depense agricole.
     *
     * `amount` est toujours un montant REEL declare : on refuse un montant nul ou
     * negatif plutot que d'ecrire un zero de remplissage qui fausserait la marge.
     * `isEstimated` distingue explicitement une estimation d'une piece reelle.
     *
     * @param  numeric-string|string  $amount
     */
    public function recordExpense(
        Company $company,
        FarmExpenseCategory $category,
        string $label,
        string $amount,
        string $currency,
        Carbon $spentOn,
        bool $isEstimated = false,
        ?int $farmSiteId = null,
        ?int $parcelId = null,
        ?int $cropCycleId = null,
        ?int $livestockBatchId = null,
        ?int $equipmentId = null,
        ?int $campaignId = null,
        ?int $supplierId = null,
        ?int $purchaseDocumentId = null,
        ?int $farmInputId = null,
        ?string $quantity = null,
        ?string $unit = null,
        ?int $fileId = null,
        ?string $notes = null,
        ?int $recordedBy = null,
        ?string $clientExpenseId = null,
    ): FarmExpense {
        $value = $this->assertPositiveAmount($amount);

        if ($campaignId !== null) {
            $campaign = FarmCampaign::query()
                ->where('company_id', $company->id)
                ->whereKey($campaignId)
                ->first();

            if ($campaign === null) {
                throw FarmFinanceException::campaignNotInCompany($campaignId);
            }

            // On n'ecrit pas dans un exercice clos.
            if ($campaign->isClosed()) {
                throw FarmFinanceException::campaignClosed($campaignId);
            }
        }

        $expense = new FarmExpense([
            'farm_site_id' => $farmSiteId,
            'parcel_id' => $parcelId,
            'crop_cycle_id' => $cropCycleId,
            'farm_livestock_batch_id' => $livestockBatchId,
            'farm_equipment_id' => $equipmentId,
            'farm_campaign_id' => $campaignId,
            'category' => $category->value,
            'label' => $label,
            'amount' => $value,
            'currency' => strtoupper($currency),
            'is_estimated' => $isEstimated,
            'spent_on' => $spentOn,
            'supplier_id' => $supplierId,
            'purchase_document_id' => $purchaseDocumentId,
            'farm_input_id' => $farmInputId,
            'quantity' => $quantity !== null ? number_format((float) $quantity, 3, '.', '') : null,
            'unit' => $unit,
            'file_id' => $fileId,
            'notes' => $notes,
            'recorded_by' => $recordedBy,
            'client_expense_id' => $clientExpenseId,
        ]);
        $expense->company_id = $company->id;
        $expense->save();

        return $expense;
    }

    /**
     * Rattache un document de vente EXISTANT a un objet agricole.
     *
     * Farm OS ne cree aucun document commercial : `saleDocumentId` doit designer
     * un document du service de ventes existant. Un meme document ne peut pas
     * etre impute deux fois au meme lot de recolte — c'est la garde qui empeche
     * de compter deux fois le chiffre d'affaires.
     *
     * @param  numeric-string|string  $amount
     */
    public function linkSale(
        Company $company,
        int $saleDocumentId,
        string $amount,
        string $currency,
        Carbon $soldOn,
        ?int $harvestLotId = null,
        ?int $livestockBatchId = null,
        ?int $saleLineId = null,
        ?string $quantity = null,
        ?string $unit = null,
        ?int $customerId = null,
        ?string $notes = null,
        ?int $recordedBy = null,
        ?string $clientSaleLinkId = null,
    ): FarmSaleLink {
        if ($saleDocumentId <= 0) {
            throw FarmFinanceException::saleDocumentRequired();
        }

        // Le chiffre d'affaires doit etre rattache a un objet agricole, sinon il
        // ne serait ventilable sur aucune parcelle ni aucun lot.
        if ($harvestLotId === null && $livestockBatchId === null) {
            throw FarmFinanceException::saleTargetRequired();
        }

        $value = $this->assertPositiveAmount($amount);

        // ISOLATION MULTI-TENANT — FAIL-CLOSED : le lot vise ne doit pas
        // appartenir a un AUTRE tenant. On interroge explicitement toute la
        // table (sans le scope courant) : si la ligne existe et porte un autre
        // `company_id`, l'imputation est refusee. Un identifiant qui n'existe
        // pas encore n'est pas rejete ici — la cle etrangere logique et les
        // controles metier du lot restent la source de verite.
        if ($harvestLotId !== null) {
            $owner = FarmHarvestLot::crossTenantForPlatformAdmin()
                ->whereKey($harvestLotId)
                ->value('company_id');

            if ($owner !== null && $owner !== $company->id) {
                throw FarmFinanceException::saleTargetNotInCompany('harvest_lot', $harvestLotId);
            }
        }

        if ($livestockBatchId !== null) {
            $owner = FarmLivestockBatch::crossTenantForPlatformAdmin()
                ->whereKey($livestockBatchId)
                ->value('company_id');

            if ($owner !== null && $owner !== $company->id) {
                throw FarmFinanceException::saleTargetNotInCompany('livestock_batch', $livestockBatchId);
            }
        }

        $connection = FarmSaleLink::query()->getConnection();

        return $connection->transaction(function () use (
            $company, $saleDocumentId, $value, $currency, $soldOn, $harvestLotId,
            $livestockBatchId, $saleLineId, $quantity, $unit, $customerId, $notes,
            $recordedBy, $clientSaleLinkId
        ): FarmSaleLink {
            // Idempotence terrain : un rejeu renvoie le lien deja ecrit.
            if ($clientSaleLinkId !== null) {
                /** @var FarmSaleLink|null $existing */
                $existing = FarmSaleLink::query()
                    ->where('company_id', $company->id)
                    ->where('client_sale_link_id', $clientSaleLinkId)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            if ($harvestLotId !== null) {
                $already = FarmSaleLink::query()
                    ->where('company_id', $company->id)
                    ->where('sale_document_id', $saleDocumentId)
                    ->where('farm_harvest_lot_id', $harvestLotId)
                    ->exists();

                if ($already) {
                    throw FarmFinanceException::saleAlreadyLinked($saleDocumentId, $harvestLotId);
                }
            }

            $link = new FarmSaleLink([
                'sale_document_id' => $saleDocumentId,
                'sale_line_id' => $saleLineId,
                'farm_harvest_lot_id' => $harvestLotId,
                'farm_livestock_batch_id' => $livestockBatchId,
                'quantity' => $quantity !== null ? number_format((float) $quantity, 3, '.', '') : null,
                'unit' => $unit,
                'amount' => $value,
                'currency' => strtoupper($currency),
                'customer_id' => $customerId,
                'sold_on' => $soldOn,
                'notes' => $notes,
                'recorded_by' => $recordedBy,
                'client_sale_link_id' => $clientSaleLinkId,
            ]);
            $link->company_id = $company->id;
            $link->save();

            return $link;
        });
    }

    /**
     * Rentabilite sur un axe d'analyse.
     *
     * HONNETETE : le resultat expose separement les couts REELS, les couts
     * ESTIMES, le chiffre d'affaires enregistre, et la liste de ce qui MANQUE.
     * `is_reliable` n'est vrai que si aucune donnee ne manque — une marge
     * calculee sur des donnees incompletes n'est jamais presentee comme certaine.
     *
     * @return array{
     *     axis: string,
     *     axis_id: int|null,
     *     currency: string|null,
     *     costs_real: string,
     *     costs_estimated: string,
     *     costs_total: string,
     *     revenue_recorded: string,
     *     margin_estimated: string,
     *     by_category: array<string, array{real: string, estimated: string}>,
     *     missing_inputs: list<string>,
     *     is_reliable: bool,
     *     data_quality: string
     * }
     */
    public function profitability(
        Company $company,
        FarmCostAxis $axis,
        ?int $axisId = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): array {
        $expenses = $this->expenseQuery($company, $axis, $axisId, $from, $to)->get();
        $links = $this->saleLinkQuery($company, $axis, $axisId, $from, $to)->get();

        $costsReal = 0.0;
        $costsEstimated = 0.0;
        /** @var array<string, array{real: float, estimated: float}> $byCategory */
        $byCategory = [];
        $currency = null;

        foreach ($expenses as $expense) {
            $amount = (float) $expense->amount;
            $currency ??= $expense->currency;
            $category = $expense->category;

            if (! isset($byCategory[$category])) {
                $byCategory[$category] = ['real' => 0.0, 'estimated' => 0.0];
            }

            if ($expense->dataQuality() === FarmDataQuality::Estimated) {
                $costsEstimated += $amount;
                $byCategory[$category]['estimated'] += $amount;
            } else {
                $costsReal += $amount;
                $byCategory[$category]['real'] += $amount;
            }
        }

        $revenue = 0.0;

        foreach ($links as $link) {
            $revenue += (float) $link->amount;
            $currency ??= $link->currency;
        }

        // Ce qui MANQUE est liste explicitement : on ne remplace jamais une
        // donnee absente par zero en la faisant passer pour un resultat.
        $missing = [];

        if ($expenses->isEmpty()) {
            $missing[] = 'expenses';
        }

        if ($links->isEmpty()) {
            $missing[] = 'revenue';
        }

        if ($costsEstimated > 0.0) {
            $missing[] = 'estimated_costs';
        }

        $costsTotal = $costsReal + $costsEstimated;
        $margin = $revenue - $costsTotal;

        $formatted = [];

        foreach ($byCategory as $category => $totals) {
            $formatted[$category] = [
                'real' => number_format($totals['real'], 2, '.', ''),
                'estimated' => number_format($totals['estimated'], 2, '.', ''),
            ];
        }

        return [
            'axis' => $axis->value,
            'axis_id' => $axisId,
            'currency' => $currency,
            'costs_real' => number_format($costsReal, 2, '.', ''),
            'costs_estimated' => number_format($costsEstimated, 2, '.', ''),
            'costs_total' => number_format($costsTotal, 2, '.', ''),
            'revenue_recorded' => number_format($revenue, 2, '.', ''),
            'margin_estimated' => number_format($margin, 2, '.', ''),
            'by_category' => $formatted,
            'missing_inputs' => $missing,
            // Une marge n'est fiable que si RIEN ne manque.
            'is_reliable' => $missing === [],
            'data_quality' => $missing === [] ? FarmDataQuality::Real->value : FarmDataQuality::Estimated->value,
        ];
    }

    /**
     * Rentabilite par campagne agricole.
     *
     * @return list<array<string, mixed>>
     */
    public function profitabilityByCampaign(Company $company): array
    {
        $campaigns = FarmCampaign::query()
            ->where('company_id', $company->id)
            ->orderByDesc('start_year')
            ->get();

        $rows = [];

        foreach ($campaigns as $campaign) {
            $rows[] = $this->profitability($company, FarmCostAxis::Campaign, $campaign->id) + [
                'campaign_name' => $campaign->name,
                'start_year' => $campaign->start_year,
                'status' => $campaign->status,
            ];
        }

        return $rows;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<FarmExpense>
     */
    private function expenseQuery(
        Company $company,
        FarmCostAxis $axis,
        ?int $axisId,
        ?Carbon $from,
        ?Carbon $to,
    ): \Illuminate\Database\Eloquent\Builder {
        $query = FarmExpense::query()->where('company_id', $company->id);

        $column = $this->axisColumn($axis);

        if ($column !== null && $axisId !== null) {
            $query->where($column, $axisId);
        }

        if ($from !== null) {
            $query->where('spent_on', '>=', $from);
        }

        if ($to !== null) {
            $query->where('spent_on', '<=', $to);
        }

        return $query;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<FarmSaleLink>
     */
    private function saleLinkQuery(
        Company $company,
        FarmCostAxis $axis,
        ?int $axisId,
        ?Carbon $from,
        ?Carbon $to,
    ): \Illuminate\Database\Eloquent\Builder {
        $query = FarmSaleLink::query()->where('company_id', $company->id);

        // Le chiffre d'affaires n'est ventilable que sur les axes auxquels un
        // lien de vente peut etre rattache : lot de recolte ou lot d'animaux.
        if ($axis === FarmCostAxis::LivestockBatch && $axisId !== null) {
            $query->where('farm_livestock_batch_id', $axisId);
        } elseif ($axis === FarmCostAxis::Crop && $axisId !== null) {
            $query->where('farm_harvest_lot_id', $axisId);
        } elseif ($axis === FarmCostAxis::Parcel && $axisId !== null) {
            // Une parcelle n'est pas portee par le lien : on ne pretend pas
            // ventiler un produit par parcelle sans donnee de rattachement.
            $query->whereRaw('1 = 0');
        }

        if ($from !== null) {
            $query->where('sold_on', '>=', $from);
        }

        if ($to !== null) {
            $query->where('sold_on', '<=', $to);
        }

        return $query;
    }

    private function axisColumn(FarmCostAxis $axis): ?string
    {
        return match ($axis) {
            FarmCostAxis::Parcel => 'parcel_id',
            FarmCostAxis::Crop => 'crop_cycle_id',
            FarmCostAxis::LivestockBatch => 'farm_livestock_batch_id',
            FarmCostAxis::Equipment => 'farm_equipment_id',
            FarmCostAxis::Campaign => 'farm_campaign_id',
            FarmCostAxis::Farm => null,
        };
    }

    /**
     * @return numeric-string
     */
    private function assertPositiveAmount(string $amount): string
    {
        if (is_numeric($amount) === false || (float) $amount <= 0) {
            throw FarmFinanceException::invalidAmount($amount);
        }

        return number_format((float) $amount, 2, '.', '');
    }
}
