<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * #8163 (suite #8124) — notifications & alertes de verrouillage de compte.
 *
 * Deux responsabilités, TOUTES best-effort (un échec d'alerte ne doit jamais
 * casser le flux de login ni changer son contrat 401/423) :
 *
 * 1. Notifier le titulaire du compte lorsqu'un verrou est posé, via le canal
 *    interne canonique `NotificationDispatcher` (ADR-0013 — pas d'émetteur
 *    legacy) : visible dans `GET /notifications` (web/mobile).
 * 2. Alerter sur les verrouillages RÉPÉTÉS d'un même compte (pattern
 *    d'attaque) : compteur 24 h en cache ; à partir de 3 verrouillages dans
 *    la fenêtre, événement d'observabilité `auth.repeated_account_lockouts`
 *    à chaque verrou supplémentaire + UNE alerte in-app aux managers
 *    `principal`/`rh` de la société (à la 3e occurrence seulement, pour ne
 *    pas spammer — les occurrences suivantes restent tracées en log).
 *
 * Surface plateforme (SuperAdmin) : voir PlatformAuthController — pas de
 * canal in-app pour un SuperAdmin, l'alerte y est l'événement structuré
 * `platform.repeated_account_lockouts`.
 */
class AccountLockoutNotifier
{
    public const TYPE_ACCOUNT_LOCKED = 'security.account_locked';

    public const TYPE_REPEATED_LOCKOUTS = 'security.repeated_lockouts';

    /** Seuil de verrouillages en 24 h déclenchant l'alerte. */
    private const ALERT_THRESHOLD = 3;

    /** Fenêtre du compteur de verrouillages (heures). */
    private const WINDOW_HOURS = 24;

    /** Rôles manager destinataires de l'alerte (périmètre société entière). */
    private const RECIPIENT_MANAGER_ROLES = ['principal', 'rh'];

    public function __construct(
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * Appelé lorsqu'un verrou est POSÉ sur le compte d'un employé (5e échec).
     * Ne lève jamais d'exception.
     */
    public function onAccountLocked(Employee $employee, DateTimeInterface $lockedUntil): void
    {
        $this->notifyOwner($employee, $lockedUntil);
        $this->trackRepeatedLockouts($employee);
    }

    /**
     * Notification in-app au titulaire : « votre compte a été verrouillé ».
     * Identifiants uniquement dans les logs (pas d'email en clair, cf. #8164).
     */
    private function notifyOwner(Employee $employee, DateTimeInterface $lockedUntil): void
    {
        try {
            $this->notifications->dispatch(
                (int) $employee->id,
                self::TYPE_ACCOUNT_LOCKED,
                'Compte temporairement verrouillé',
                'Votre compte a été verrouillé pour 15 minutes après plusieurs échecs de connexion. '
                    .'Si vous n\'êtes pas à l\'origine de ces tentatives, prévenez votre responsable.',
                [
                    'company_id' => (string) $employee->company_id,
                    'locked_until' => $lockedUntil->format(DATE_ATOM),
                ],
            );
        } catch (Throwable $exception) {
            Log::channel('structured')->warning('auth.account_lockout_notification_failed', [
                'employee_id' => $employee->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Compteur de verrouillages sur 24 h (cache) + alerte si seuil atteint.
     */
    private function trackRepeatedLockouts(Employee $employee): void
    {
        try {
            $key = "auth_lockouts_24h:employee:{$employee->id}";
            Cache::add($key, 0, now()->addHours(self::WINDOW_HOURS));
            $lockouts = (int) Cache::increment($key);

            if ($lockouts < self::ALERT_THRESHOLD) {
                return;
            }

            // Événement d'observabilité — à CHAQUE verrou au-delà du seuil.
            Log::warning('auth.repeated_account_lockouts', [
                'employee_id' => $employee->id,
                'company_id' => $employee->company_id,
                'lockouts_24h' => $lockouts,
                'window_hours' => self::WINDOW_HOURS,
            ]);

            // Alerte managers une seule fois par fenêtre (au 3e verrouillage).
            if ($lockouts === self::ALERT_THRESHOLD) {
                $this->alertManagers($employee, $lockouts);
            }
        } catch (Throwable $exception) {
            Log::channel('structured')->warning('auth.repeated_lockout_tracking_failed', [
                'employee_id' => $employee->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Alerte in-app aux managers `principal`/`rh` de la société (même
     * résolution que `AlertExpiringContracts::managersFor`, #8142).
     */
    private function alertManagers(Employee $employee, int $lockouts): void
    {
        $managers = Employee::query()
            ->where('company_id', $employee->company_id)
            ->where('role', 'manager')
            ->whereIn('manager_role', self::RECIPIENT_MANAGER_ROLES)
            ->get();

        $employeeName = trim("{$employee->first_name} {$employee->last_name}");

        foreach ($managers as $manager) {
            try {
                $this->notifications->dispatch(
                    (int) $manager->id,
                    self::TYPE_REPEATED_LOCKOUTS,
                    'Verrouillages répétés d\'un compte',
                    "Le compte de {$employeeName} a été verrouillé {$lockouts} fois en "
                        .self::WINDOW_HOURS.' h après des échecs de connexion — possible attaque ciblée.',
                    [
                        'employee_id' => (int) $employee->id,
                        'company_id' => (string) $employee->company_id,
                        'lockouts_24h' => $lockouts,
                        'window_hours' => self::WINDOW_HOURS,
                    ],
                );
            } catch (Throwable $exception) {
                Log::channel('structured')->warning('auth.repeated_lockout_alert_failed', [
                    'employee_id' => $employee->id,
                    'manager_id' => $manager->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
