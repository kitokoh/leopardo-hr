<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\Contract;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Alertes d'expiration de contrat (J-30 / J-15 / J-7).
 *
 * BOS-007 (#8142) — la commande ne faisait qu'un `Log::info` : **aucune
 * notification n'était jamais envoyée** aux managers, et les logs portaient
 * les **noms complets des employés** (PII). Désormais :
 *   - notifications RÉELLES aux managers `principal`/`rh` de la société du
 *     contrat, via le canal interne canonique (`NotificationDispatcher` →
 *     table `notifications`, lue par `GET /notifications`) ;
 *   - **déduplication** par `(contrat, seuil, date)` : le double scheduler
 *     (BOS-006A) fait tourner la commande 2×/jour — le second passage ne
 *     produit aucun doublon ;
 *   - **logs sans PII** : identifiants uniquement (le nom de l'employé ne
 *     figure que dans la notification in-app, jamais dans les logs).
 *
 * La commande itère TOUS les tenants (contexte console) : l'accès cross-tenant
 * est explicité par `crossTenantForSystemTask()` (#7960).
 */
class AlertExpiringContracts extends Command
{
    /** Seuils d'alerte, en jours avant l'échéance. */
    private const THRESHOLDS = [30, 15, 7];

    /** Rôles manager destinataires (périmètre compagnie entière). */
    private const RECIPIENT_MANAGER_ROLES = ['principal', 'rh'];

    /** Type canonique des notifications d'expiration de contrat. */
    public const NOTIFICATION_TYPE = 'contract_expiring';

    protected $signature = 'contracts:alert-expiring';

    protected $description = 'Notify when contracts expire in 30, 15, or 7 days';

    public function __construct(
        private readonly NotificationDispatcher $notifications,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sent = 0;
        $duplicates = 0;
        $sentAlerts = [];

        foreach (self::THRESHOLDS as $days) {
            $expiresOn = now()->addDays($days)->toDateString();

            $contracts = Contract::query()
                ->crossTenantForSystemTask('alertes d\'expiration de contrats, itere tous les tenants (#8142)')
                ->where('status', 'active')
                ->whereNotNull('end_date')
                ->whereDate('end_date', $expiresOn)
                ->with('employee:id,first_name,last_name')
                ->get();

            foreach ($contracts as $contract) {
                $companyId = (string) $contract->company_id;

                foreach ($this->managersFor($companyId) as $manager) {
                    if ($this->alreadyNotified((int) $manager->id, (int) $contract->id, $days)) {
                        $duplicates++;

                        continue;
                    }

                    $this->notifications->dispatch(
                        (int) $manager->id,
                        self::NOTIFICATION_TYPE,
                        'Contrat arrivant à échéance',
                        $this->body($contract, $days, $expiresOn),
                        [
                            'contract_id' => (int) $contract->id,
                            'threshold_days' => $days,
                            'expires_on' => $expiresOn,
                            'company_id' => $companyId,
                        ],
                        '/hr/contracts',
                    );

                    $sent++;
                    $sentAlerts[] = [
                        'contract_id' => (int) $contract->id,
                        'threshold_days' => $days,
                        'company_id' => $companyId,
                    ];
                }
            }
        }

        // BOS-007 (#8142) — logs SANS PII : identifiants uniquement, jamais de
        // nom d'employé (le nom vit dans la notification in-app, pas ici).
        Log::info('contracts:alert-expiring', [
            'notifications_sent' => $sent,
            'duplicates_skipped' => $duplicates,
            'alerts' => $sentAlerts,
        ]);

        $this->info("Found {$sent} contract expiry alert(s) sent ({$duplicates} duplicate(s) skipped).");

        return self::SUCCESS;
    }

    /**
     * Managers destinataires : `principal`/`rh` de la société du contrat.
     *
     * @return list<Employee>
     */
    private function managersFor(string $companyId): array
    {
        /** @var list<Employee> $managers */
        $managers = Employee::query()
            ->where('company_id', $companyId)
            ->where('role', 'manager')
            ->whereIn('manager_role', self::RECIPIENT_MANAGER_ROLES)
            ->get()
            ->all();

        return $managers;
    }

    /**
     * Déduplication journalière par (contrat, seuil) — l'unicité du jour est
     * portée par la notification elle-même : deux exécutions le même jour ne
     * produisent qu'une alerte (le double scheduler BOS-006A ne double plus
     * les notifications).
     */
    private function alreadyNotified(int $managerId, int $contractId, int $days): bool
    {
        return Notification::query()
            ->crossTenantForSystemTask('deduplication des alertes d\'expiration de contrats (#8142)')
            ->where('employee_id', $managerId)
            ->where('type', self::NOTIFICATION_TYPE)
            ->whereDate('created_at', now()->toDateString())
            ->where('data->contract_id', (string) $contractId)
            ->where('data->threshold_days', $days)
            ->exists();
    }

    private function body(Contract $contract, int $days, string $expiresOn): string
    {
        $employee = $contract->employee;
        $holder = $employee !== null
            ? trim("{$employee->first_name} {$employee->last_name}")
            : null;

        $subject = $holder !== null && $holder !== ''
            ? "Le contrat {$contract->reference} de {$holder}"
            : "Le contrat {$contract->reference}";

        return "{$subject} arrive à échéance dans {$days} jour(s) (le {$expiresOn}).";
    }
}
