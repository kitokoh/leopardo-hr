# Kiosque — Méthodes de pointage configurables par tenant (#5120)

> Voir également : [docs/kiosk/punch-methods-config.md](../../kiosk/punch-methods-config.md) pour le guide opérateur.

## Modèle de données

```sql
-- zkteco_devices : colonne ajoutée par migration 2026_08_19_000001
punch_methods  jsonb  nullable  DEFAULT NULL
-- null = toutes méthodes autorisées (rétro-compat)
-- Exemple : ["fingerprint","face"]
```

```sql
-- employees : colonne ajoutée par migration 2026_08_19_000002
badge_number  varchar(50)  nullable
-- Contrainte unicité partielle (company_id, badge_number) WHERE badge_number IS NOT NULL
```

## Isolation tenant

- `punch_methods` est scopé à la table `zkteco_devices` qui a un index `(company_id, status)` ; le lookup se fait toujours avec `WHERE company_id = ?` — isolation garantie.
- `badge_number` a un index unique partiel scopé à `company_id` — deux tenants peuvent avoir le même badge sans collision.

## Enforcement (service, pas middleware)

La logique d'enforcement est dans `ZktecoIntegrationService::pullAttendance` :
1. Extraire `method` du record (défaut : `fingerprint`)
2. `ZktecoDevice::isPunchMethodAllowed($method)` — compare avec `resolvedPunchMethods()`
3. `isEmployeeEnrolledForMethod($employee, $method)` — vérifie l'enrôlement

Les refus sont journalisés sur le canal `audit` (device_id, employee_id, method, error_code).

## Rétro-compatibilité

- `punch_methods = null` ⇒ `resolvedPunchMethods()` retourne toutes les méthodes
- Record sans `method` ⇒ `fingerprint` supposé (comportement actuel)
- `badge_number` absent ⇒ fallback `matricule` actuel conservé (lookup backward-compat)
