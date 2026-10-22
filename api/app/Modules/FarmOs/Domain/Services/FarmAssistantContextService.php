<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Models\FarmAnimalHealthEvent;
use App\Modules\FarmOs\Domain\Models\FarmEquipmentMaintenance;
use App\Modules\FarmOs\Domain\Models\FarmHarvestLot;
use App\Modules\FarmOs\Domain\Models\FarmInput;
use App\Modules\FarmOs\Domain\Models\FarmInputStockMovement;
use App\Modules\FarmOs\Domain\Models\FarmLivestockBatch;
use App\Modules\FarmOs\Domain\Models\FarmOperation;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T6.1 Assistant agricole : fournisseur de CONTEXTE.
 *
 * PRINCIPE DIRECTEUR : l'assistant n'utilise QUE les donnees auxquelles
 * l'utilisateur a reellement acces, et il ne fabrique AUCUNE donnee externe.
 *
 * Ce service ne parle a aucun modele de langage : il prepare un contexte
 * STRICTEMENT interne, derive des tables Farm OS du tenant courant. C'est
 * l'orchestrateur IA EXISTANT de Leopardo ({@see \App\AI\Orchestrator} via
 * {@see \App\AI\AgentRunner}) qui repond, avec ses mecanismes de validation et
 * de confirmation deja en place — Farm OS n'introduit AUCUN nouveau fournisseur
 * IA et AUCUN moteur SQL libre.
 *
 * CE QUI N'EST JAMAIS FOURNI :
 *   - aucune observation meteo (aucune integration meteo n'existe) ;
 *   - aucun prix de marche (aucune source de prix n'est branchee) ;
 *   - aucune recommandation agronomique presentee comme certaine.
 *
 * Les seules donnees externes admises sont celles DECLAREES par l'exploitant,
 * et elles portent alors une source identifiable et un etat de fraicheur
 * ({@see self::externalDataPolicy()}).
 */
final class FarmAssistantContextService
{
    /**
     * Contexte interne complet, borne aux modules ACTIVES.
     *
     * @return array<string, mixed>
     */
    public function buildContext(Company $company, ?Carbon $at = null): array
    {
        $now = $at ?? Carbon::now();
        $active = $this->activeCapabilities($company);

        $context = [
            'generated_at' => $now->toIso8601String(),
            'active_capabilities' => $active,
            'external_data_policy' => $this->externalDataPolicy(),
            'sections' => [],
        ];

        if ($this->has($active, FarmCapability::WorkPlanning)) {
            $context['sections']['tasks'] = $this->taskSummary($company, $now);
        }

        if ($this->has($active, FarmCapability::StocksInputs)) {
            $context['sections']['inputs_to_reorder'] = $this->inputsToReorder($company);
        }

        if ($this->has($active, FarmCapability::Equipment)) {
            $context['sections']['equipment_maintenance_due'] = $this->maintenanceDue($company, $now);
        }

        if ($this->has($active, FarmCapability::Livestock)) {
            $context['sections']['livestock'] = $this->livestockSummary($company, $now);
        }

        if ($this->has($active, FarmCapability::HarvestStorage)) {
            $context['sections']['harvest_stock'] = $this->harvestStockSummary($company);
        }

        return $context;
    }

    /**
     * Politique de donnees externes, exposee TELLE QUELLE a l'assistant pour
     * qu'il ne puisse pas pretendre disposer d'une donnee qu'il n'a pas.
     *
     * @return array<string, array{available:bool, source:string|null, freshness:string|null}>
     */
    public function externalDataPolicy(): array
    {
        return [
            'weather' => ['available' => false, 'source' => null, 'freshness' => null],
            'market_prices' => ['available' => false, 'source' => null, 'freshness' => null],
            'satellite_imagery' => ['available' => false, 'source' => null, 'freshness' => null],
            'sensor_telemetry' => ['available' => false, 'source' => null, 'freshness' => null],
        ];
    }

    /**
     * Questions a poser a un conseiller agricole, derivees des donnees REELLES
     * de l'exploitation. Ce sont des QUESTIONS, jamais des recommandations :
     * Farm OS ne se substitue pas a un professionnel qualifie.
     *
     * @return list<string>
     */
    public function advisorQuestions(Company $company, ?Carbon $at = null): array
    {
        $now = $at ?? Carbon::now();
        $questions = [];

        $overdue = $this->overdueOperations($company, $now);

        if ($overdue > 0) {
            $questions[] = 'farm.advisor_question.overdue_operations';
        }

        if ($this->inputsToReorder($company) !== []) {
            $questions[] = 'farm.advisor_question.inputs_to_reorder';
        }

        if ($this->maintenanceDue($company, $now) !== []) {
            $questions[] = 'farm.advisor_question.maintenance_due';
        }

        if ($this->healthRemindersDue($company, $now) !== []) {
            $questions[] = 'farm.advisor_question.health_reminders';
        }

        return $questions;
    }

    /**
     * @return array{total:int, overdue:int, due_today:int}
     */
    private function taskSummary(Company $company, Carbon $now): array
    {
        $base = FarmOperation::query()->where('company_id', $company->id);

        return [
            'total' => (clone $base)->count(),
            'overdue' => $this->overdueOperations($company, $now),
            'due_today' => (clone $base)
                ->whereDate('planned_start_at', $now->toDateString())
                ->count(),
        ];
    }

    private function overdueOperations(Company $company, Carbon $now): int
    {
        return FarmOperation::query()
            ->where('company_id', $company->id)
            ->whereNotNull('planned_start_at')
            ->where('planned_start_at', '<', $now->copy()->startOfDay())
            ->whereNull('finished_at')
            ->count();
    }

    /**
     * Intrants sous leur seuil d'alerte. Un intrant SANS seuil declare n'y
     * apparait jamais : Farm OS n'invente pas de seuil.
     *
     * @return list<array{input_id:int, name:string, quantity:string, threshold:string}>
     */
    private function inputsToReorder(Company $company): array
    {
        $rows = [];

        $inputs = FarmInput::query()
            ->where('company_id', $company->id)
            ->whereNotNull('alert_threshold')
            ->get();

        foreach ($inputs as $input) {
            $quantity = $this->inputQuantity($company, (int) $input->id);

            if ((float) $quantity < (float) $input->alert_threshold) {
                $rows[] = [
                    'input_id' => (int) $input->id,
                    'name' => (string) $input->name,
                    'quantity' => $quantity,
                    'threshold' => (string) $input->alert_threshold,
                ];
            }
        }

        return $rows;
    }

    private function inputQuantity(Company $company, int $inputId): string
    {
        $connection = (new FarmInputStockMovement)->getConnection();
        $table = (new FarmInputStockMovement)->getTable();

        // La quantite disponible est DERIVEE du journal des mouvements (dernier
        // `quantity_after` connu) : Farm OS ne tient pas un compteur separe qui
        // pourrait diverger du journal.
        $value = $connection->table($table)
            ->where('company_id', $company->id)
            ->where('farm_input_id', $inputId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->value('quantity_after');

        return is_numeric($value) ? number_format((float) $value, 3, '.', '') : '0.000';
    }

    /**
     * @return list<array{equipment_id:int, title:string, planned_at:string|null}>
     */
    private function maintenanceDue(Company $company, Carbon $now): array
    {
        $rows = [];

        $maintenances = FarmEquipmentMaintenance::query()
            ->where('company_id', $company->id)
            ->where('status', 'planned')
            ->whereNotNull('planned_at')
            ->where('planned_at', '<=', $now)
            ->orderBy('planned_at')
            ->get();

        foreach ($maintenances as $maintenance) {
            $rows[] = [
                'equipment_id' => (int) $maintenance->farm_equipment_id,
                'title' => (string) $maintenance->title,
                'planned_at' => $maintenance->planned_at?->toIso8601String(),
            ];
        }

        return $rows;
    }

    /**
     * @return array{batches:int, present_head:int, health_reminders_due:int}
     */
    private function livestockSummary(Company $company, Carbon $now): array
    {
        $batches = FarmLivestockBatch::query()->where('company_id', $company->id)->get();
        $present = 0;

        foreach ($batches as $batch) {
            $present += (int) $batch->head_count;
        }

        return [
            'batches' => $batches->count(),
            'present_head' => $present,
            'health_reminders_due' => count($this->healthRemindersDue($company, $now)),
        ];
    }

    /**
     * @return list<array{title:string, next_due_at:string|null}>
     */
    private function healthRemindersDue(Company $company, Carbon $now): array
    {
        $rows = [];

        $events = FarmAnimalHealthEvent::query()
            ->where('company_id', $company->id)
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', $now)
            ->orderBy('next_due_at')
            ->get();

        foreach ($events as $event) {
            $rows[] = [
                'title' => (string) $event->title,
                'next_due_at' => $event->next_due_at?->toIso8601String(),
            ];
        }

        return $rows;
    }

    /**
     * @return array{open_lots:int, remaining_by_crop:list<array{crop:string, remaining:string, unit:string}>}
     */
    private function harvestStockSummary(Company $company): array
    {
        $lots = FarmHarvestLot::query()
            ->where('company_id', $company->id)
            ->where('status', 'open')
            ->get();

        $byCrop = [];

        foreach ($lots as $lot) {
            $crop = (string) ($lot->crop_name ?? 'unknown');
            $unit = (string) $lot->unit;
            $key = $crop.'|'.$unit;

            if (! isset($byCrop[$key])) {
                $byCrop[$key] = ['crop' => $crop, 'remaining' => 0.0, 'unit' => $unit];
            }

            $byCrop[$key]['remaining'] += (float) $lot->quantity_remaining;
        }

        $rows = [];

        foreach ($byCrop as $row) {
            $rows[] = [
                'crop' => $row['crop'],
                'remaining' => number_format($row['remaining'], 3, '.', ''),
                'unit' => $row['unit'],
            ];
        }

        return [
            'open_lots' => $lots->count(),
            'remaining_by_crop' => $rows,
        ];
    }

    /**
     * @param  list<string>  $active
     */
    private function has(array $active, FarmCapability $capability): bool
    {
        return in_array($capability->value, $active, true);
    }

    /**
     * @return list<string>
     */
    private function activeCapabilities(Company $company): array
    {
        // Reutilise la porte serveur EXISTANTE : l'assistant ne voit jamais un
        // outil que l'utilisateur n'a pas active.
        return app(FarmCapabilityGate::class)->enabled($company);
    }
}
