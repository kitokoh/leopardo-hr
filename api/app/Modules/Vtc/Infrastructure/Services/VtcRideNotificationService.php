<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\SendPushNotificationJob;
use App\Modules\Vtc\Domain\Models\VtcRide;
use App\Support\I18nCatalog;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * #8423 (M-20) — Notifications de cycle de vie d'une course VTC.
 *
 * Consomme les événements de domaine jusqu'ici ORPHELINS
 * (`VtcRideAccepted` / `VtcRideExpired` / `VtcRideCompleted`) pour tenir
 * le passager informé — et le chauffeur à la clôture :
 *
 *  - accepted : passager — « un chauffeur a accepté votre course » ;
 *  - expired : passager — « aucun chauffeur disponible, réessayez » ;
 *  - completed : passager — clôture + montant final ; chauffeur — récap.
 *
 * Canal : push plateforme (`SendPushNotificationJob`, PA2-COMM-006) vers
 * l'EMPLOYÉ concerné, dans SA locale (pattern PaySlip #3946). Un passager
 * INVITÉ (marketplace, `passenger_user_id` null) n'a pas de compte à
 * notifier — ignoré silencieusement.
 *
 * DÉGRADATION GRACIEUSE (spec du lot : « mail/FCM indispo ≠ échec
 * course ») : toute défaillance (file indisponible, fiche employé absente)
 * est journalisée, jamais propagée — une notification ne fait JAMAIS
 * échouer le flux de course qui a émis l'événement.
 */
final class VtcRideNotificationService
{
    /**
     * Acceptation : le passager est informé que SON chauffeur arrive.
     */
    public function notifyAccepted(string $companyId, int $rideId): void
    {
        $ride = $this->findRide($companyId, $rideId);

        if (! $ride instanceof VtcRide) {
            return;
        }

        $this->pushToEmployee(
            $this->passengerEmployee($ride),
            'vtc_ride_accepted_title',
            'vtc_ride_accepted_body',
            [
                'driver' => $ride->driver?->name ?? '',
                'reference' => $ride->reference,
            ],
            $this->metadata($ride, 'vtc.ride.accepted'),
        );
    }

    /**
     * Expiration : la fenêtre de dispatch s'est épuisée sans acceptation —
     * le passager est invité à réessayer (jamais d'attente muette).
     */
    public function notifyExpired(string $companyId, int $rideId): void
    {
        $ride = $this->findRide($companyId, $rideId);

        if (! $ride instanceof VtcRide) {
            return;
        }

        $this->pushToEmployee(
            $this->passengerEmployee($ride),
            'vtc_ride_expired_title',
            'vtc_ride_expired_body',
            ['reference' => $ride->reference],
            $this->metadata($ride, 'vtc.ride.expired'),
        );
    }

    /**
     * Clôture : montant final au passager, récapitulatif au chauffeur.
     */
    public function notifyCompleted(string $companyId, int $rideId): void
    {
        $ride = $this->findRide($companyId, $rideId);

        if (! $ride instanceof VtcRide) {
            return;
        }

        $price = $this->formatPrice($ride->final_price_minor, $ride->currency);

        $this->pushToEmployee(
            $this->passengerEmployee($ride),
            'vtc_ride_completed_title',
            'vtc_ride_completed_body',
            [
                'reference' => $ride->reference,
                'price' => $price,
            ],
            $this->metadata($ride, 'vtc.ride.completed'),
        );

        $this->pushToEmployee(
            $this->driverEmployee($ride),
            'vtc_ride_completed_title',
            'vtc_ride_completed_driver_body',
            [
                'reference' => $ride->reference,
                'price' => $price,
            ],
            $this->metadata($ride, 'vtc.ride.completed'),
        );
    }

    /**
     * Course du tenant de l'événement — fail-closed : course absente ou
     * tenant incohérent → aucune notification (la course peut avoir été
     * purgée entre l'émission et le traitement).
     */
    private function findRide(string $companyId, int $rideId): ?VtcRide
    {
        /** @var VtcRide|null $ride */
        $ride = VtcRide::query()->whereKey($rideId)->first();

        if (! $ride instanceof VtcRide || (string) $ride->company_id !== $companyId) {
            return null;
        }

        return $ride;
    }

    /**
     * Compte du passager — null pour un invité marketplace (pas de compte
     * à notifier) ou si la fiche n'existe plus.
     */
    private function passengerEmployee(VtcRide $ride): ?Employee
    {
        if ($ride->passenger_user_id === null) {
            return null;
        }

        /** @var Employee|null $employee */
        $employee = Employee::query()->whereKey($ride->passenger_user_id)->first();

        return $employee;
    }

    /**
     * Compte du chauffeur assigné (fiche liée par `user_id`) — null si
     * aucun chauffeur n'a été assigné à la course.
     */
    private function driverEmployee(VtcRide $ride): ?Employee
    {
        $userId = $ride->driver?->user_id;

        if ($userId === null) {
            return null;
        }

        /** @var Employee|null $employee */
        $employee = Employee::query()->whereKey($userId)->first();

        return $employee;
    }

    /**
     * Push à un employé dans SA locale — dégradation gracieuse : toute
     * défaillance est journalisée, JAMAIS propagée au flux de course.
     *
     * @param  array<string, string>  $params
     * @param  array<string, mixed>  $metadata
     */
    private function pushToEmployee(?Employee $employee, string $titleKey, string $bodyKey, array $params, array $metadata): void
    {
        if (! $employee instanceof Employee) {
            return;
        }

        try {
            $previousLocale = App::getLocale();
            App::setLocale(I18nCatalog::normalizeLocale($employee->preferred_language));

            $title = (string) trans('notifications.'.$titleKey);
            $body = (string) trans('notifications.'.$bodyKey, $params);

            App::setLocale($previousLocale);

            SendPushNotificationJob::dispatch(
                (int) $employee->getAuthIdentifier(),
                $title,
                $body,
                $metadata,
            );
        } catch (Throwable $exception) {
            Log::warning('vtc.notification_failed', [
                'employee_id' => $employee->getAuthIdentifier(),
                'template' => $bodyKey,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(VtcRide $ride, string $type): array
    {
        return [
            'type' => $type,
            'ride_id' => $ride->id,
            'reference' => $ride->reference,
        ];
    }

    /**
     * Montant minor units → affichage simple (BC-35 : les devises de la
     * verticale — XAF… — n'ont pas de décimales usuelles ; « — » quand le
     * prix final n'a pas été recalculé).
     */
    private function formatPrice(?int $priceMinor, string $currency): string
    {
        if ($priceMinor === null) {
            return '—';
        }

        return number_format($priceMinor / 100, 0, ',', ' ').' '.$currency;
    }
}
