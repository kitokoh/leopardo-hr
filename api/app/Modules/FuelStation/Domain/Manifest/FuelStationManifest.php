<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Manifest;

/**
 * Manifest de la solution FuelStation — Issue #5795 (FUEL-001).
 *
 * Source de vérité du module : clé de feature, maturité, dépendances de
 * plateforme/métier, permissions et catalogue de fonctionnalités exposées.
 *
 * Le manifest est validé par allowlist (FuelStationManifest::validKey()) :
 * toute clé hors catalogue est refusée à l'enregistrement/activation.
 */
final class FuelStationManifest
{
    public const KEY = 'fuel_station';

    /** Maturité de la solution (pilot | production). */
    public const MATURITY = 'pilot';

    /**
     * Dépendances déclarées de la solution (modules Leopardo). `requires`
     * sont des prérequis HARD à l'activation ; `integrates_with` sont des
     * intégrations optionnelles déclarées au catalogue.
     *
     * @var list<string>
     */
    public const REQUIRED_DEPENDENCIES = ['rh', 'attendance'];

    /** @var list<string> */
    public const OPTIONAL_INTEGRATIONS = ['payroll', 'crm', 'marketing', 'accounting'];

    /** Roles manager autorisés à activer/gérer la solution. */
    public const MANAGER_ROLES = ['principal', 'rh'];

    /**
     * Catalogue des fonctionnalités FuelStation (clés de la table `features`).
     *
     * @var array<string, array{title: string, endpoint: string, methods: list<string>, permissions: list<string>}>
     */
    public const FEATURES = [
        'fuel_station.manifest' => [
            'title' => 'FuelStation — manifeste de la solution',
            'endpoint' => '/api/v1/fuel-station/manifest',
            'methods' => ['GET'],
            'permissions' => ['manager'],
        ],
        'fuel_station.activate' => [
            'title' => 'FuelStation — activation tenant (idempotente)',
            'endpoint' => '/api/v1/fuel-station/activate',
            'methods' => ['POST'],
            'permissions' => ['principal'],
        ],
        'fuel_station.stations' => [
            'title' => 'FuelStation — stations et sites (FUEL-002)',
            'endpoint' => '/api/v1/fuel-station/stations',
            'methods' => ['GET', 'POST'],
            'permissions' => ['principal', 'rh'],
        ],
        'fuel_station.equipment' => [
            'title' => 'FuelStation — pompes, cuves et compteurs (FUEL-003)',
            'endpoint' => '/api/v1/fuel-station/pumps',
            'methods' => ['GET', 'POST'],
            'permissions' => ['principal', 'rh'],
        ],
        'fuel_station.readings' => [
            'title' => 'FuelStation — relevés de compteur (FUEL-004)',
            'endpoint' => '/api/v1/fuel-station/readings',
            'methods' => ['GET', 'POST'],
            'permissions' => ['principal', 'rh'],
        ],
    ];

    public static function validKey(string $key): bool
    {
        return $key === self::KEY
            || array_key_exists($key, self::FEATURES)
            || str_starts_with($key, self::KEY.'.');
    }
}
