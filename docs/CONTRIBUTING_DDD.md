# Guide de contribution — Architecture DDD Backend

## Règle principale

> **Tout nouveau code métier va dans `api/app/Modules/<NomDuModule>/`.**

⚠️ **`api/app/Http/Controllers/Api/V1/` et `api/app/Models/` sont entièrement supprimés** (bilan
PR #824 + phase 2, voir `api/ARCHITECTURE.md` section «Nettoyage complet») — pas de code legacy
à y éviter, ces dossiers n'existent plus du tout.

Dossiers encore en coexistence partielle — ne pas y ajouter de nouveau code :

| Dossier legacy | Remplacé par |
|---|---|
| `api/app/Services/` (reste TenantManager.php shim + Cache/Communication/Payroll/SSO/Security/Tracking) | `Modules/<Name>/Infrastructure/Services/` |
| `api/app/Exceptions/` (base `DomainException` partagée, encore étendue par des modules) | `Modules/<Name>/Domain/Exceptions/` |

## Modules existants (19 modules)

| Module | Domaine couvert |
|---|---|
| `Absence` | Demandes de congés, soldes, approbations |
| `Attendance` | Pointage, ZKTeco, anomalies, géofencing |
| `Billing` | Abonnements, webhooks Stripe, facturation |
| `Cabinet` | Gestion documentaire, partage |
| `Cameras` | Surveillance, streaming |
| `EdgeSync` | Synchronisation offline/mobile (structure spécialisée, hors squelette DDD standard) |
| `Expense` | Notes de frais employés |
| `Fleet` | Véhicules, trajets, affectations |
| `Growth` | Programme partenaires, référencement, payout |
| `HR` | Employés, départements, contrats, évaluations, formations |
| `Marketing` | Vitrine, leads, campagnes |
| `Notification` | Notifications in-app, dispatch FCM/APNs |
| `Onboarding` | Provisioning entreprise, QR onboarding |
| `Payroll` | Paie, bulletins, avances, loans |
| `Planning` | Planning, congés approbation side-manager |
| `Platform` | Super-admin plateforme, gestion tenants |
| `Recruitment` | Offres, candidats, entretiens |
| `SmartAttendance` | Pointage intelligent / variantes avancées d'Attendance |
| `Training` | Formations, sessions, suivis |

> Liste vivante — voir `docs/ARCHITECTURE_STATUS.md` section 1 pour l'état de complétude
> (Domain/Contracts/Application/Infra/Interfaces/Tests) de chaque module.

## Créer un nouveau module

```bash
cd api
php artisan make:module <NomPascalCase>
```

Le scaffolding génère automatiquement la structure DDD complète + routes + ServiceProvider.

## Structure d'un module

```
Modules/<Name>/
├── Application/
│   ├── Actions/        # Cas d'usage (une classe = une action métier)
│   ├── DTOs/           # Objets de transfert en entrée des Actions
│   ├── Queries/        # Requêtes lecture seule
│   └── Listeners/      # Listeners d'événements
├── Domain/
│   ├── Models/         # Entités Eloquent du domaine
│   ├── ValueObjects/   # Value Objects immutables
│   ├── Events/         # Événements du domaine
│   ├── Exceptions/     # Exceptions métier (étendent DomainException)
│   └── Enums/          # Enums du domaine
├── Infrastructure/
│   ├── Services/       # Implémentations des services
│   ├── Repositories/   # Requêtes DB complexes
│   └── Exports/        # Exports Excel/PDF
├── Interfaces/
│   └── Api/V1/
│       ├── Controllers/ # Controllers (injectent les Actions)
│       ├── Requests/    # Form Requests de validation
│       └── Resources/   # API Resources (transformation des sorties)
└── Providers/
    └── <Name>ServiceProvider.php
```

## Conventions de nommage

| Élément | Pattern | Exemple |
|---|---|---|
| **Action** | Verbe + Nom | `CreateEmployee`, `ApproveAbsence` |
| **DTO** | ActionName + DTO | `CreateEmployeeDTO`, `RequestAbsenceDTO` |
| **Exception** | Description + Exception | `InsufficientLeaveBalanceException` |
| **Controller** | Resource + Controller | `AbsenceController` |
| **Service** | Resource + Service | `AbsenceService`, `PayrollService` |

## Enregistrement d'un nouveau module

Après `php artisan make:module`, vérifier :

1. **`api/bootstrap/providers.php`** — ServiceProvider ajouté
2. **`api/routes/api.php`** — `require __DIR__.'/modules/<name>.php'` ajouté

## Coexistence legacy / nouveau

Les controllers (`app/Http/Controllers/Api/V1/`) et modèles (`app/Models/`) legacy ont déjà été
entièrement supprimés (PR #824 + phase 2) — toutes les routes pointent vers les modules DDD.
Ce qui reste en coexistence partielle aujourd'hui :
- `app/Services/` (shim `TenantManager.php` + services spécialisés non encore migrés)
- `app/Exceptions/` (base `DomainException` partagée, encore étendue par certains modules)

Pour une nouvelle feature sur un domaine déjà migré : étendre le module DDD correspondant. Pour un
service encore dans `app/Services/` : vérifier s'il doit être migré vers
`Modules/<Name>/Infrastructure/Services/` avant d'y ajouter de la logique.
