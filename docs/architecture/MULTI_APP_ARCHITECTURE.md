# Architecture Multi-App Leopardo HR

## Vue d'ensemble

Leopardo HR est une plateforme **multi-tenant** et **multi-app**. Une seule API centrale sert plusieurs applications distinctes (web et mobile), chacune ayant un scope fonctionnel précis.

---

## 1. Cartographie des Applications

### Applications Web

| App | URL | Public cible | Guard |
|-----|-----|-------------|-------|
| **Admin Web** | `/admin` | Super-admins de la plateforme | `super_admin_api` |
| **Client Web** (vitrine + dashboard) | `/` | Clients / Tenants (principal) | `sanctum` |

### Applications Mobiles

| App | Nom | manager_role requis | Deep link scheme |
|-----|-----|-------------------|-----------------|
| **Admin Mobile** | Leopardo Admin | Super-Admin (plateforme) | `leopardo-admin` |
| **Manager Mobile** | Leopardo Manager | `principal` | `leopardo-manager` |
| **RH Mobile** | Leopardo RH | `rh` | `leopardo-rh` |
| **Employee Mobile** | Leopardo Employee | (employé standard) | `leopardo-employee` |
| **Comptable Mobile** *(prévu)* | Leopardo Comptable | `comptable` | `leopardo-comptable` |
| **Marketing Mobile** *(prévu)* | Leopardo Marketing | `marketing` | `leopardo-marketing` |

---

## 2. Modèle de Rôles

### Niveau Plateforme (SuperAdmin)

```
super_admin
└── Gère tous les tenants (companies) via /api/v1/platform/**
└── Authentifié via guard: super_admin_api (table: super_admins)
└── Applications: Admin Web + Admin Mobile
```

### Niveau Tenant (Employee avec rôle)

```
role: "manager"
├── manager_role: "principal"    → App Manager Mobile (gère tout le tenant)
│   ├── Nomme les rôles: rh, comptable, marketing, dept
│   ├── Accède à /api/v1/company/team-roles
│   ├── Accède à /api/v1/employees/{id}/assign-role
│   └── Vue: dashboard admin + tous les modules
│
├── manager_role: "rh"           → App RH Mobile (gestion employés)
│   ├── Ajoute / modifie les employés (SANS changer les rôles manager)
│   ├── Gère absences, horaires, invitations
│   ├── Accède à /api/v1/hr/** (routes dédiées)
│   └── NE PEUT PAS nommer des rôles manager
│
├── manager_role: "comptable"    → App Comptable Mobile (prévu Phase 3)
│   └── Accès paie, exports comptables, bulletins
│
├── manager_role: "marketing"    → App Marketing Mobile (prévu Phase 4)
│   └── Accès analytics, campagnes, reportings
│
└── manager_role: "dept"         → Chef de département (prévu Phase 5)
    └── Accès équipe de son département uniquement

role: "employee"                 → App Employee Mobile (self-service)
└── Pointage, absences, bulletins de paie, planning
```

---

## 3. Règles d'Assignation des Rôles

### Qui peut nommer qui ?

```
SuperAdmin → peut tout faire sur les tenants (via /platform)
principal  → peut nommer rh, comptable, marketing, dept dans son company
rh         → peut ajouter des employés, mais NE PEUT PAS nommer des managers
Tous les autres rôles → NE PEUVENT PAS changer les rôles
```

### Endpoint d'assignation

```
POST /api/v1/employees/{employee}/assign-role
Authorization: Bearer <token_principal>
Body: { "manager_role": "rh" | "comptable" | "marketing" | "dept" | "principal" | null }
```

- `null` → retire le rôle manager, repasse l'employé en `role: "employee"`
- Déclenche un email de notification avec lien de téléchargement de l'app correspondante

---

## 4. Routes API par App

### App Manager (principal)

```
GET  /api/v1/dashboard/admin          → résumé admin avec comptage des rôles
GET  /api/v1/company/team-roles       → liste équipe avec rôles
POST /api/v1/employees/{id}/assign-role → nommer un rôle
GET  /api/v1/dashboard/summary        → dashboard standard
GET  /api/v1/employees                → liste employés
```

### App RH (rh + principal par héritage)

```
GET  /api/v1/hr/me                    → profil RH
GET  /api/v1/hr/dashboard             → stats RH (total employés, invitations, etc.)
GET  /api/v1/hr/team-overview         → vue rapide de l'équipe
GET  /api/v1/hr/employees             → liste employés (paginated, filtrable)
POST /api/v1/hr/employees             → ajouter un employé (role=employee uniquement)
GET  /api/v1/hr/employees/{id}        → détail employé
PATCH /api/v1/hr/employees/{id}       → modifier employé (sans toucher aux rôles)
```

Les routes RH classiques (absences, horaires, invitations, pointage) restent dans
`rh.php` et sont accessibles via `api.manager:rh,principal`.

### App Employee (tous les employés authentifiés)

```
GET  /api/v1/me/daily-summary
GET  /api/v1/me/quick-estimate
GET  /api/v1/me/monthly-summary
GET  /api/v1/me/balance
POST /api/v1/attendance/check-in
POST /api/v1/attendance/check-out
GET  /api/v1/absences
...
```

### App Admin (super_admin)

```
GET  /api/v1/platform/companies
POST /api/v1/platform/companies
GET  /api/v1/platform/metrics/overview
...
```

---

## 5. Header X-App-Context (optionnel)

Les apps mobiles peuvent envoyer un header `X-App-Context` pour identifier l'app appelante :

```
X-App-Context: manager | rh | employee | comptable | marketing | admin
```

Ce header est traité par `EnsureAppContextMiddleware` et :
- Valide que l'utilisateur a bien le bon rôle pour l'app déclarée
- Injecte `app_context` dans les attributs de la requête pour les logs
- Est **optionnel** (si absent, le contrôle de rôle se fait normalement via `api.manager`)

---

## 6. Réponse `mobile_experience` (EmployeeResource)

L'endpoint `/api/v1/auth/me` retourne un objet `mobile_experience` avec :

```json
{
  "mobile_experience": {
    "stage": "regular",
    "app": {
      "id": "rh",
      "name": "Leopardo RH",
      "deep_link_scheme": "leopardo-rh"
    },
    "modules": [...],
    "quick_actions": [...]
  },
  "app_links": {
    "android": "https://play.google.com/store/apps/details?id=com.leopardo.rh",
    "ios": "https://apps.apple.com/app/leopardo-rh/id0000000002",
    "name": "Leopardo RH",
    "deep_link_scheme": "leopardo-rh"
  }
}
```

Les modules et quick_actions sont **filtrés selon le rôle** :
- `principal` → modules manager + gestion des rôles + branding
- `rh` → modules RH + gestion employés (sans assignation de rôles)
- `employee` → modules self-service uniquement

---

## 7. Flow d'Onboarding Multi-App

```
1. Tenant crée son compte (principal)
2. Principal se connecte → reçoit app_links.deep_link_scheme = "leopardo-manager"
3. Principal nomme un RH : POST /api/v1/employees/{id}/assign-role {"manager_role":"rh"}
4. Email envoyé au RH avec lien téléchargement Leopardo RH
5. RH installe l'app et se connecte → reçoit app = "rh" dans mobile_experience
6. RH ajoute des employés via /api/v1/hr/employees
7. Employés reçoivent invitation → installent Leopardo Employee
```

---

## 8. Phases de Déploiement

| Phase | Livré | Apps |
|-------|-------|------|
| **Phase 1** (PR #777) | Assignation de rôles, email notification, marketing manager_role | Fondation |
| **Phase 2** (cette PR) | App RH dédiée, MobileExperienceService par rôle, X-App-Context middleware | App RH + Manager séparés |
| **Phase 3** *(prévu)* | Comptable app routes | App Comptable |
| **Phase 4** *(prévu)* | Marketing app routes | App Marketing |
| **Phase 5** *(prévu)* | Dept (chef de département) scope limité à son équipe | App Dept |

---

## 9. Sécurité & Isolation

- **Isolation tenant** : toutes les requêtes passent par `TenantMiddleware` qui charge le bon schéma de base de données
- **Isolation rôle** : `EnsureApiManagerMiddleware` bloque l'accès si `manager_role` ne correspond pas
- **Isolation app** : `EnsureAppContextMiddleware` (optionnel) valide la cohérence app ↔ rôle
- **Principal exclusif** : seule la méthode `isPrincipal()` ouvre l'assignation de rôles — jamais `isManager()` seul
- **RH limité** : `HrController::addEmployee()` force `role: 'employee'` et `manager_role: null` — le RH ne peut pas créer de managers

---

*Document maintenu par l'équipe Leopardo. Dernière mise à jour : Phase 2 (2026-06-23).*
