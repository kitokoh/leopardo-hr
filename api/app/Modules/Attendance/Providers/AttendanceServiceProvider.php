<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Providers;

use App\Core\AI\Domain\Contracts\FaceVerificationPort;
use App\Core\AI\Infrastructure\Adapters\UnavailableFaceVerificationAdapter;
use App\Modules\Attendance\Domain\Contracts\GeofenceValidatorInterface;
use App\Modules\Attendance\Infrastructure\Services\AttendanceGeofenceService;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AttendanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ADR-0016 Phase 5 (#5356) : binding historiquement porté par le
        // provider de l'ancien module SmartAttendance (supprimé) — le contrat
        // vit désormais dans le module Attendance (module unique après fusion).
        $this->app->bind(
            GeofenceValidatorInterface::class,
            AttendanceGeofenceService::class,
        );

        // BIO-001 (#6762) : le moteur de vérification faciale est remplaçable
        // par configuration (`ai.models.face_verification.adapter`). Défaut
        // FAIL-CLOSED : aucun fournisseur branché → provider_unavailable.
        // Résolution lazy (closure) pour que les tests puissent surcharger la
        // config avant la première résolution.
        $this->app->singleton(FaceVerificationPort::class, function (): FaceVerificationPort {
            /** @var class-string<FaceVerificationPort> $adapterClass */
            $adapterClass = config('ai.models.face_verification.adapter') ?: UnavailableFaceVerificationAdapter::class;

            $adapter = $this->app->make($adapterClass);

            if (! $adapter instanceof FaceVerificationPort) {
                throw new RuntimeException(
                    "Adapter '{$adapterClass}' must implement ".FaceVerificationPort::class.'.'
                );
            }

            return $adapter;
        });
    }

    public function boot(): void
    {
        // ADR-0016 Phase 3 (#5354) : routes géo consolidées sous /api/v1/attendance/*
        // (Phase 5 #5356 : alias /smart-attendance/* supprimés, contrat unique).
        $this->loadRoutesFrom(__DIR__.'/../routes/geo.php');
    }
}
