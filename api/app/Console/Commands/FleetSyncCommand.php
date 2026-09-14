<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Fleet\Infrastructure\Services\FleetTrackingSyncService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * #7401 — `leopardo:fleet:sync` : la synchronisation Traccar de la flotte
 * (devices → positions → trajets) existait uniquement sous forme d'endpoints
 * manuels, sans aucune tâche planifiée. Résultat en production : sans un
 * humain qui clique, aucun appareil n'était appairé, aucune position n'était
 * relevée, aucun trajet n'entrait en base — alors que
 * `config('tracking.sync_interval_minutes')` (TRACCAR_SYNC_INTERVAL) était
 * défini et jamais lu.
 *
 * La commande est **bornée** :
 * - fenêtre glissante `--days` (1 à 90 jours, jamais dans le futur) ;
 * - `--limit` véhicules traités par tenant (1 à 500) ;
 * - `--tenant` pour rejouer un seul tenant (défaut : tous ceux qui ont des
 *   véhicules liés ou appariables) — l'itération passe par
 *   `TenantManager::withinTenant()`, donc l'isolation par `company_id` et le
 *   `search_path` du tenant sont respectés.
 *
 * Elle est **idempotente** : chaque étape s'appuie sur les index uniques
 * (positions Traccar, trajets Traccar) et un rejeu de la même fenêtre n'écrit
 * aucun doublon. Elle ne touche ni aux routes, ni aux autorisations existantes.
 *
 * Usage : php artisan leopardo:fleet:sync [--tenant=uuid] [--days=1] [--limit=50]
 * Planification : `bootstrap/app.php`, toutes les `TRACCAR_SYNC_INTERVAL`
 * minutes, avec `withoutOverlapping()`.
 */
class FleetSyncCommand extends Command
{
    /** Plafond de véhicules traités par tenant et par passe. */
    public const MAX_VEHICLES_PER_TENANT = 500;

    protected $signature = 'leopardo:fleet:sync
        {--tenant= : identifiant du tenant à synchroniser (défaut : tous ceux qui ont des véhicules Traccar)}
        {--days=1 : profondeur de la fenêtre synchronisée, en jours (1 à 90)}
        {--limit=50 : nombre maximal de véhicules traités par tenant (1 à 500)}';

    protected $description = 'Synchronise Traccar pour la flotte : appairage des appareils, historique des positions puis trajets (borné, idempotent).';

    public function handle(TenantManager $tenants, FleetTrackingSyncService $sync): int
    {
        $tenantOption = $this->option('tenant');

        $companies = is_string($tenantOption) && $tenantOption !== ''
            ? Company::query()->whereKey($tenantOption)->get()
            : $this->companiesWithTrackedVehicles();

        if ($companies->isEmpty()) {
            $this->info('Aucun tenant avec véhicule Traccar — rien à synchroniser.');

            return self::SUCCESS;
        }

        $days = max(1, min(FleetTrackingSyncService::MAX_WINDOW_DAYS, (int) $this->option('days')));
        $limit = max(1, min(self::MAX_VEHICLES_PER_TENANT, (int) $this->option('limit')));
        $to = now();
        $from = $to->copy()->subDays($days);

        // Un seul appel Traccar pour la liste des appareils (partagée entre
        // tous les tenants) : l'appairage se fait ensuite par
        // `traccar_unique_id`, tenant par tenant.
        $devices = $sync->devices();

        $linked = 0;
        $writtenPositions = 0;
        $writtenTrips = 0;
        $checkedVehicles = 0;

        foreach ($companies as $company) {
            $result = $tenants->withinTenant(
                $company,
                fn (): array => $this->syncCompany((string) $company->id, $sync, $devices, $from, $to, $limit),
            );

            $linked += $result['linked'];
            $writtenPositions += $result['positions'];
            $writtenTrips += $result['trips'];
            $checkedVehicles += $result['vehicles'];

            $this->line(sprintf(
                '  • %s : %d appareil(s) appairé(s), %d position(s), %d trajet(s) — %d véhicule(s) suivi(s).',
                $company->id,
                $result['linked'],
                $result['positions'],
                $result['trips'],
                $result['vehicles'],
            ));
        }

        $this->info(sprintf(
            'Synchronisation flotte terminée — %d tenant(s), %d véhicule(s), %d appareil(s) appairé(s), %d position(s), %d trajet(s) (%d jour(s) d\'historique).',
            $companies->count(),
            $checkedVehicles,
            $linked,
            $writtenPositions,
            $writtenTrips,
            $days,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<int|string, mixed>  $devices
     * @return array{linked:int, positions:int, trips:int, vehicles:int}
     */
    private function syncCompany(
        string $companyId,
        FleetTrackingSyncService $sync,
        array $devices,
        Carbon $from,
        Carbon $to,
        int $limit,
    ): array {
        $deviceResult = $sync->syncDevices($companyId, $devices);
        $positionResult = $sync->syncPositions($companyId, $from, $to, $limit);
        $tripResult = $sync->syncTrips($companyId, $from, $to, $limit);

        return [
            'linked' => $deviceResult['linked'],
            'positions' => $positionResult['written'],
            'trips' => $tripResult['written'],
            'vehicles' => $positionResult['vehicles'],
        ];
    }

    /**
     * Tenants à traiter : ceux qui ont au moins un véhicule appariable
     * (`traccar_unique_id`) ou déjà appairé (`traccar_device_id`).
     *
     * @return Collection<int, Company>
     */
    private function companiesWithTrackedVehicles(): Collection
    {
        $rawIds = Vehicle::query()
            ->whereNotNull('company_id')
            ->where(function (Builder $query): void {
                $query->whereNotNull('traccar_device_id')->orWhereNotNull('traccar_unique_id');
            })
            ->distinct()
            ->pluck('company_id')
            ->all();

        /** @var list<string> $companyIds */
        $companyIds = array_values(array_filter(
            $rawIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        ));

        if ($companyIds === []) {
            return new Collection;
        }

        return Company::query()->whereIn('id', $companyIds)->get();
    }
}
