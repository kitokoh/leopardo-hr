# Mobile release device QA - 2026-06-01

## Scope

Plan 69.1 - Recette mobile release sur vrais appareils.

## Build Firebase declenche

| Champ | Valeur |
|---|---|
| Workflow | `Mobile - Build and Firebase Distribution` |
| Run | `26750677529` |
| URL | `https://github.com/kitokoh/leopardo-hr/actions/runs/26750677529` |
| Ref | `main` |
| SHA | `2fc2ca97058365ed468430c2c3323df0690d607e` |
| Environnement | `staging` |
| Notes | `Plan 69.1 staging device QA - employee, manager, platform admin from main` |
| Conclusion | `success` |

## Resultats par app

| App | Package Android | Build name | Artifact | Taille | Firebase target |
|---|---|---|---|---:|---|
| Employee | `com.leopardo.employee` | `employee-manual-20260601` | `leopardo-employee-staging-employee-manual-20260601` / ID `7329799545` | 30,720,287 bytes | verifie |
| Manager | `com.leopardo.manager` | `manager-manual-20260601` | `leopardo-manager-staging-manager-manual-20260601` / ID `7329798816` | 31,287,702 bytes | verifie |
| Platform admin | `com.leopardo.platformadmin` | `platform-admin-manual-20260601` | `leopardo-platform-admin-staging-platform-admin-manual-20260601` / ID `7329792737` | 27,782,284 bytes | verifie |

## Preuves CI

- Les trois jobs ont passe `Flutter analyze`.
- Les trois builds APK release ont ete produits.
- Les trois artifacts GitHub ont ete publies.
- Les trois jobs Firebase App Distribution ont termine en succes.
- Les trois fichiers `google-services.json` ont matche leur package Android attendu.

## Checklist testeurs appareils

Tester sur au moins un Android recent et un Android milieu de gamme :

### Employee

- L'app s'ouvre sans page noire ni logo bloque.
- Login demo employee.
- Acces accueil et pointage.
- Premier pointage simple.
- Acces compte et notifications.

### Manager

- L'app s'ouvre sans page noire ni logo bloque.
- Login demo manager/RH.
- Acces equipe, pointage manager, absences/avances.
- Acces branding entreprise si autorise.

### Platform admin

- L'app s'ouvre sur login, jamais directement dashboard.
- Login demo super-admin.
- Liste entreprises.
- Creation entreprise test ou verification fiche existante.

## Verdict

**Go CI/Firebase. Go device conditionnel.**

Le build/distribution est prouve cote GitHub/Firebase. Le go final Plan 69.1 demande encore retour testeur sur appareils physiques avec captures ou observations datees.
