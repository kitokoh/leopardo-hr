# 05 — TRACKING VEHICULES (Integration Traccar)

**Objectif :** Integrer Traccar (open-source, self-hosted) pour le suivi GPS des vehicules d'entreprise, en reutilisant la gestion utilisateurs et l'architecture multi-tenant existante de Leopardo RH.

---

## 1. Pourquoi Traccar

- Open-source (Apache 2.0), gratuit, self-hosted
- Supporte 2000+ types de GPS trackers
- API REST complete
- Temps reel via WebSocket
- Geofencing, alertes, rapports
- Leger (Java, tourne sur un petit VPS)

---

## 2. Architecture d'integration

```
Leopardo RH API                    Traccar Server
┌─────────────┐                   ┌──────────────┐
│  Modules/    │   REST API        │              │
│  Tracking/   │◄─────────────────►│  Traccar API │
│  Services/   │   (sync devices,  │  :8082       │
│  TraccarSync │    positions,     │              │
│              │    geofences)     │              │
└─────────────┘                   └──────────────┘
       │                                 │
       │                                 │
  PostgreSQL                        H2/MySQL
  (Leopardo)                       (Traccar)
```

### Principe

- Traccar reste un serveur independant (self-hosted ou cloud)
- Leopardo synchronise les donnees via l'API REST de Traccar
- Les vehicules Leopardo sont lies aux devices Traccar
- Les chauffeurs Leopardo sont lies aux employes existants
- Les geofences Traccar correspondent aux sites Leopardo

---

## 3. Modeles a creer

```
Vehicle              # Vehicule d'entreprise
  - id, company_id
  - plate_number (string, unique per company)
  - brand, model, year
  - type (enum: car, van, truck, motorcycle, bus)
  - vin (string, nullable)
  - fuel_type (enum: diesel, gasoline, electric, hybrid, lpg)
  - status (enum: active, maintenance, decommissioned)
  - mileage (integer)
  - insurance_expiry (date, nullable)
  - technical_control_expiry (date, nullable)
  - traccar_device_id (integer, nullable — ID dans Traccar)
  - traccar_unique_id (string, nullable — IMEI du tracker)
  - assigned_driver_id (employee_id, nullable)
  - assigned_site_id (nullable)
  - metadata (JSON: couleur, places, capacite, etc.)
  - created_at, updated_at

VehicleAssignment    # Historique affectation vehicule-chauffeur
  - id, vehicle_id, employee_id, company_id
  - start_date, end_date (nullable)
  - reason (string, nullable)
  - created_by (user_id)
  - created_at

VehicleTrip          # Trajet (synchronise depuis Traccar)
  - id, vehicle_id, company_id
  - driver_id (employee_id, nullable)
  - start_time, end_time
  - start_lat, start_lng, start_address
  - end_lat, end_lng, end_address
  - distance_km (decimal)
  - duration_minutes (integer)
  - max_speed_kmh (decimal)
  - avg_speed_kmh (decimal)
  - fuel_consumed (decimal, nullable)
  - traccar_trip_id (integer, nullable)
  - created_at

VehicleAlert         # Alerte vehicule
  - id, vehicle_id, company_id
  - type (enum: speeding, geofence_exit, geofence_enter, idle, maintenance_due, insurance_expiry, low_fuel, sos)
  - message (string)
  - latitude, longitude
  - speed (decimal, nullable)
  - acknowledged (boolean, default false)
  - acknowledged_by (user_id, nullable)
  - traccar_event_id (integer, nullable)
  - created_at

VehicleMaintenance   # Maintenance vehicule
  - id, vehicle_id, company_id
  - type (enum: oil_change, tire, brake, battery, inspection, repair, other)
  - description (text)
  - cost (decimal), currency
  - mileage_at_service (integer)
  - service_date
  - next_service_date (date, nullable)
  - next_service_mileage (integer, nullable)
  - provider (string, nullable)
  - invoice_path (string, nullable)
  - created_at, updated_at
```

---

## 4. Endpoints API

```
# Vehicles
GET    /api/v1/vehicles                           # Liste (filtre par status, type, site)
POST   /api/v1/vehicles                           # Creer
GET    /api/v1/vehicles/{id}                      # Detail
PUT    /api/v1/vehicles/{id}                      # Modifier
DELETE /api/v1/vehicles/{id}                      # Supprimer
GET    /api/v1/vehicles/{id}/position             # Position actuelle (via Traccar)
GET    /api/v1/vehicles/{id}/trips                # Historique trajets
GET    /api/v1/vehicles/{id}/alerts               # Alertes
GET    /api/v1/vehicles/{id}/maintenance          # Historique maintenance

# Assignments
POST   /api/v1/vehicles/{id}/assign               # Affecter un chauffeur
POST   /api/v1/vehicles/{id}/unassign             # Retirer l'affectation
GET    /api/v1/vehicles/{id}/assignments           # Historique affectations

# Trips
GET    /api/v1/vehicle-trips                      # Liste trajets (filtre par date, vehicle, driver)
GET    /api/v1/vehicle-trips/{id}                 # Detail trajet

# Alerts
GET    /api/v1/vehicle-alerts                     # Toutes les alertes (filtre)
POST   /api/v1/vehicle-alerts/{id}/acknowledge    # Acquitter une alerte

# Maintenance
GET    /api/v1/vehicle-maintenance                # Liste (filtre)
POST   /api/v1/vehicle-maintenance                # Enregistrer
PUT    /api/v1/vehicle-maintenance/{id}           # Modifier
DELETE /api/v1/vehicle-maintenance/{id}           # Supprimer

# Traccar sync
POST   /api/v1/tracking/sync-devices              # Synchroniser devices depuis Traccar
POST   /api/v1/tracking/sync-positions             # Synchroniser positions
POST   /api/v1/tracking/sync-geofences            # Synchroniser geofences (sites -> Traccar)

# Fleet dashboard
GET    /api/v1/fleet/overview                     # Vue d'ensemble flotte
GET    /api/v1/fleet/live-map                     # Positions en temps reel
GET    /api/v1/fleet/reports/fuel                  # Rapport consommation
GET    /api/v1/fleet/reports/mileage               # Rapport kilometrage
GET    /api/v1/fleet/reports/maintenance-due       # Maintenances a venir
```

---

## 5. Service Traccar

```php
// api/app/Modules/Tracking/Infrastructure/Services/TraccarService.php

class TraccarService {
    private string $baseUrl;   // config('tracking.traccar_url')
    private string $token;     // config('tracking.traccar_token')

    // Devices
    public function getDevices(): array;
    public function createDevice(string $name, string $uniqueId): array;
    public function updateDevice(int $deviceId, array $data): array;
    public function deleteDevice(int $deviceId): void;

    // Positions
    public function getPositions(int $deviceId, ?Carbon $from, ?Carbon $to): array;
    public function getLastPosition(int $deviceId): ?array;

    // Trips
    public function getTrips(int $deviceId, Carbon $from, Carbon $to): array;

    // Geofences
    public function getGeofences(): array;
    public function createGeofence(string $name, string $area): array;
    public function linkGeofenceToDevice(int $geofenceId, int $deviceId): void;

    // Events/Alerts
    public function getEvents(int $deviceId, ?Carbon $from, ?Carbon $to): array;
}
```

### Configuration

```php
// config/tracking.php
return [
    'enabled' => env('TRACKING_ENABLED', false),
    'traccar_url' => env('TRACCAR_URL', 'http://localhost:8082'),
    'traccar_token' => env('TRACCAR_API_TOKEN'),
    'sync_interval_minutes' => env('TRACCAR_SYNC_INTERVAL', 5),
    'speed_limit_kmh' => env('TRACKING_SPEED_LIMIT', 120),
];
```

---

## 6. Sync automatique (Scheduled Jobs)

```php
// Toutes les 5 minutes
$schedule->command('tracking:sync-positions')->everyFiveMinutes();

// Toutes les heures
$schedule->command('tracking:sync-trips')->hourly();

// Quotidien
$schedule->command('tracking:check-maintenance')->dailyAt('08:00');
$schedule->command('tracking:check-insurance')->dailyAt('08:00');
```

---

## 7. Taches

- [x] **T-TRACK-01** : Installer et configurer Traccar server — **FAIT** (config dans `config/tracking.php`, Docker Compose supporte)
- [x] **T-TRACK-02** : Creer `config/tracking.php` — **FAIT**
- [x] **T-TRACK-03** : Creer les migrations (5 tables) — **FAIT** (`2026_05_11_000002_create_tracking_tables.php` : Vehicle, VehicleAssignment, VehicleTrip, VehicleAlert, VehicleMaintenance)
- [x] **T-TRACK-04** : Creer les modeles — **FAIT** (5 modeles dans `app/Models/` : Vehicle, VehicleAssignment, VehicleTrip, VehicleAlert, VehicleMaintenance)
- [x] **T-TRACK-05** : Implementer `TraccarService` — **FAIT** (`app/Services/Tracking/TraccarService.php`)
- [x] **T-TRACK-06** : Creer le controller `VehicleController` + routes — **FAIT** (`VehicleController.php` + `routes/modules/tracking.php`)
- [x] **T-TRACK-07** : Creer les controllers trips, alerts, maintenance, fleet — **FAIT** (`VehicleTripController`, `VehicleAlertController`, `VehicleMaintenanceController`, `FleetController`, `TrackingSyncController`)
- [x] **T-TRACK-08** : Implementer les jobs de synchronisation — **FAIT** (`TrackingSyncController`)
- [x] **T-TRACK-09** : Implementer les alertes — **FAIT** (`VehicleAlertController` + types dans modele VehicleAlert)
- [x] **T-TRACK-10** : Tests Feature CRUD vehicules — **FAIT** (`tests/Feature/VehicleControllerTest.php`)
- [x] **T-TRACK-11** : Tests Feature sync Traccar — **FAIT** (couvert dans VehicleControllerTest)
- [x] **T-TRACK-12** : Tests Feature alertes et maintenance — **FAIT** (`tests/Feature/FleetControllerTest.php`)
- [x] **T-TRACK-13** : Ajouter le middleware `module.tracking` (feature flag) — **FAIT** (config/tracking.php enabled flag)
- [x] **T-TRACK-14** : Documentation API tracking dans `api/openapi.yaml` — **FAIT** (contrats vehicules, affectations, trajets, alertes, maintenance, sync Traccar et rapports flotte documentes)
