# Guide de contribution — Architecture DDD Backend

## Règle principale

> **Tout nouveau code métier va dans `api/app/Modules/<NomDuModule>/`.**

Les dossiers legacy suivants sont en cours de migration — ne pas y ajouter de nouveau code :

| Dossier legacy | Remplacé par |
|---|---|
| `api/app/Http/Controllers/Api/V1/` | `Modules/<Name>/Interfaces/Api/V1/Controllers/` |
| `api/app/Services/` | `Modules/<Name>/Infrastructure/Services/` |
| `api/app/Models/` | `Modules/<Name>/Domain/Models/` |
| `api/app/Exceptions/` | `Modules/<Name>/Domain/Exceptions/` |

## Modules existants (12 modules)

| Module | Domaine couvert |
|---|---|
| `Absence` | Demandes de congés, soldes, approbations |
| `Attendance` | Pointage, ZKTeco, anomalies, géofencing |
| `Billing` | Abonnements, webhooks Stripe, facturation |
| `Cabinet` | Gestion documentaire, partage |
| `Cameras` | Surveillance, streaming |
| `Expense` | Notes de frais employés |
| `Fleet` | Véhicules, trajets, affectations |
| `HR` | Employés, départements, contrats, évaluations, formations |
| `Notification` | Notifications in-app, dispatch FCM/APNs |
| `Payroll` | Paie, bulletins, avances, loans |
| `Planning` | Planning, congés approbation side-manager |
| `Recruitment` | Offres, candidats, entretiens |

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

Pendant la phase de migration :
- Les controllers legacy dans `app/Http/Controllers/Api/V1/` continuent de fonctionner
- Ne pas les modifier : les routes existantes pointent vers eux
- Pour une nouvelle feature sur un domaine déjà en legacy : créer/étendre le module DDD correspondant
- Une fois le module DDD validé en production → migration du legacy dans une PR dédiée
