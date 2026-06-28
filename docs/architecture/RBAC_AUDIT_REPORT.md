# Audit RBAC — Leopardo HR
> Audit réalisé le 2026-06-23 — post merge PR #777 + PR #778

## 1. Cartographie des utilisateurs

Le système contient **deux familles d'utilisateurs** totalement séparées :

| Famille | Modèle | Guard Sanctum | Endpoint login |
|---------|--------|---------------|----------------|
| Super Admin (plateforme) | `SuperAdmin` | `super_admin_api` / `super_admin_web` | `POST /api/v1/platform/auth/login` |
| Employés Tenant (tous rôles) | `Employee` | `sanctum` / `web` | `POST /api/v1/auth/login` |

---

## 2. Super Admin

### Qui c'est
L'opérateur Leopardo HR (toi, Africanova Tech). Il n'appartient à aucun tenant.

### Ce qu'il peut faire
- Créer / modifier des entreprises (tenants)
- Gérer les abonnements et fonctionnalités par tenant
- Voir les métriques globales de la plateforme
- Approuver les company requests
- Accéder au CRM pipeline

### Comment il se connecte

**Web Admin (`/platform/login`)** ✅
```
POST /platform/login  (formulaire web)
Guard : super_admin_web (session)
Route : auth:super_admin_web → /platform/companies
```

**API / App Mobile Admin** ✅
```
POST /api/v1/platform/auth/login
  Body: { email, password, device_name?, two_fa_code? }
  
GET /api/v1/platform/auth/me
  Header: Authorization: Bearer <token>
  Guard : super_admin_api (Sanctum)
```

**Note 2FA :** Si `two_fa_secret` est configuré, le login retourne `202 TWO_FA_REQUIRED` → l'app doit renvoyer avec `two_fa_code`.

### Isolation de sécurité
- Guard complètement séparé (`super_admin_api`) — un token Sanctum super_admin ne peut PAS accéder aux routes `auth:sanctum` (employees) et vice-versa.
- Toutes les routes `/platform/**` sont sous `auth:super_admin_api`.

### Ce qu'il NE PEUT PAS faire
- Se connecter via `POST /api/v1/auth/login` (endpoint tenant)
- Voir les données RH d'un tenant (pointages, paies, employés)
- Accéder aux routes `auth:sanctum`

---

## 3. Principal (Manager Tenant)

### Qui c'est
L'administrateur d'un tenant (entreprise cliente). C'est le seul qui peut nommer des sous-rôles.

**Profil DB :** `role = 'manager'`, `manager_role = 'principal'`

### Comment il se connecte

**Web client (`/login`)** ✅
```
POST /login (formulaire web)
Guard : web (session)
Redirect : /dashboard (homeRoute: 'dashboard')
```

**App Mobile Manager** ✅
```
POST /api/v1/auth/login
  Body: { email, password, device_name }
  Response: EmployeeResource + token
  → mobile_experience.app.id = "manager"
  → mobile_experience.app.deep_link_scheme = "leopardo-manager"
  → suggested_home_route = "dashboard"
```

### Ce qu'il peut faire (API)
| Endpoint | Middleware | Description |
|----------|-----------|-------------|
| `GET /api/v1/dashboard/*` | `api.manager` | Tous les dashboards |
| `GET /api/v1/dashboard/admin` | `api.manager:principal` | Vue admin complète |
| `GET /api/v1/company/team-roles` | `api.manager:principal` | Liste des rôles |
| `POST /employees/{id}/assign-role` | `api.manager:principal` | **Nommer RH, comptable, marketing** |
| `GET/POST /employees` | `auth:sanctum + tenant` | Créer/voir employés |
| `GET/POST /hr/*` | `api.manager:rh,principal` | Accès hérité app RH |
| `GET/POST /invitations` | `auth:sanctum + tenant` | Gérer invitations |
| Toutes routes `rh.php`, `payroll.php`, etc. | — | Accès complet |

### Ce qu'il voit (mobile_experience)
- Modules : attendance, absences, salary_advances, payrolls, evaluations, notifications, cabinet, **team, role_management, schedules, tasks, company_branding, dashboard_admin**
- Quick actions : pointer, équipe, rôles, tâches, mois, historique, modules, paramètres

---

## 4. RH (Responsable RH)

### Qui c'est
Nommé par le Principal. Gère les employés et l'onboarding, **ne peut jamais nommer de rôles manager**.

**Profil DB :** `role = 'manager'`, `manager_role = 'rh'`

### Comment il se connecte

**Web client (`/login`)** ✅
```
POST /login → /dashboard (accès au dashboard manager)
Middleware `manager` et `manager_role:principal,rh` autorisent les routes employee mgmt
```

**App Mobile RH** ✅
```
POST /api/v1/auth/login
  → mobile_experience.app.id = "rh"
  → mobile_experience.app.deep_link_scheme = "leopardo-rh"
  → suggested_home_route = "dashboard"
```

### Ce qu'il peut faire (API)
| Endpoint | Middleware | Description |
|----------|-----------|-------------|
| `GET /api/v1/hr/me` | `api.manager:rh,principal` | Profil RH contextualisé |
| `GET /api/v1/hr/dashboard` | `api.manager:rh,principal` | Dashboard RH |
| `GET /api/v1/hr/team-overview` | `api.manager:rh,principal` | Vue équipe |
| `GET /api/v1/hr/employees` | `api.manager:rh,principal` | Liste employés |
| `POST /api/v1/hr/employees` | `api.manager:rh,principal` | **Ajouter employé** (role forcé = 'employee') |
| `PATCH /api/v1/hr/employees/{id}` | `api.manager:rh,principal` | Modifier employé |
| `GET /api/v1/dashboard/rh` | `api.manager:rh,principal` | Dashboard RH agrégé |
| Absences, planning, tasks | `auth:sanctum` | Même accès que les autres managers |

### Ce qu'il NE PEUT PAS faire ✅ (vérifié par tests)
- Appeler `POST /employees/{id}/assign-role` → 403 (réservé principal)
- Créer un employé avec `manager_role` → champ ignoré (forcé null)
- Accéder à `/dashboard/admin` → 403

### Ce qu'il voit (mobile_experience)
- Modules : attendance, absences, salary_advances, payrolls, evaluations, notifications, cabinet, **hr_employees, hr_team_overview, schedules, invitations**
- Quick actions : pointer, ajouter employé, équipe, absences, mois, historique, modules, paramètres

---

## 5. Employé (standard)

### Qui c'est
Tout utilisateur `role = 'employee'` sans `manager_role`.

### Comment il se connecte

**Web (`/login`)** ✅
```
POST /login → /me (homeRoute: 'me.dashboard')
Accès : uniquement /me/* (middleware 'employee')
```

**App Mobile Employee** ✅
```
POST /api/v1/auth/login
  → mobile_experience.app.id = "employee"
  → mobile_experience.app.deep_link_scheme = "leopardo-employee"
  → suggested_home_route = "me.dashboard"
```

### Ce qu'il peut faire (API)
| Endpoint | Description |
|----------|-------------|
| `GET /api/v1/auth/me` | Profil + mobile_experience |
| `GET /api/v1/me/daily-summary` | Résumé journalier |
| `GET /api/v1/me/monthly-summary` | Résumé mensuel |
| `GET /api/v1/me/balance` | Solde congés |
| `POST /api/v1/attendance/check-in|out` | Pointage |
| `GET /api/v1/attendance/today` | Pointage du jour |
| `GET /api/v1/absences` | Ses absences |
| `POST /api/v1/absences` | Demande d'absence |
| `GET /api/v1/payrolls` | Ses bulletins |
| `GET /api/v1/salary-advances` | Ses avances |
| `GET /api/v1/notifications` | Ses notifications |
| `GET /api/v1/cabinet/*` | Ses documents |

### Ce qu'il NE PEUT PAS faire ✅
- Toute route `api.manager` → 403
- Toute route `api.manager:rh,principal` → 403
- `/platform/**` → guard différent, 401

---

## 6. Autres rôles managers (comptable, marketing, dept, superviseur)

Nommés par le Principal via `POST /employees/{id}/assign-role`.

| Rôle | App mobile | Dashboard dédié | Accès spécifique |
|------|-----------|----------------|------------------|
| `comptable` | leopardo-comptable | `/dashboard/comptable` | Paie, exports comptables |
| `marketing` | leopardo-marketing | `/dashboard/marketing` | Analytics marketing |
| `dept` | leopardo-dept | `/dashboard` | Gestion département |
| `superviseur` | leopardo-manager | `/dashboard` | Biométrie, pointages |

> Phase 3 (comptable) et Phase 4 (marketing) : contrôleurs dédiés à implémenter.

---

## 7. Matrice de sécurité complète

```
                    SuperAdmin  Principal  RH   Comptable  Marketing  Employé
Platform login         ✅          ❌       ❌     ❌          ❌         ❌
Tenant login           ❌          ✅       ✅     ✅          ✅         ✅
Dashboard admin        ❌          ✅       ❌     ❌          ❌         ❌
Dashboard RH           ❌          ✅       ✅     ❌          ❌         ❌
Dashboard comptable    ❌          ✅       ❌     ✅          ❌         ❌
Dashboard marketing    ❌          ✅       ❌     ❌          ✅         ❌
/hr/* endpoints        ❌          ✅       ✅     ❌          ❌         ❌
Assign role            ❌          ✅       ❌     ❌          ❌         ❌
Create employee        ❌          ✅       ✅     ❌          ❌         ❌
Self-service /me       ❌          ✅       ✅     ✅          ✅         ✅
Pointage               ❌          ✅       ✅     ✅          ✅         ✅
Platform companies     ✅          ❌       ❌     ❌          ❌         ❌
Platform subscriptions ✅          ❌       ❌     ❌          ❌         ❌
```

---

## 8. Lacunes identifiées & recommandations

### ✅ OK — déjà en place
- Séparation totale des guards SuperAdmin / Employee
- Isolation RH : ne peut pas créer de managers (testé x10)
- `mobile_experience` differ par rôle → l'app Flutter reçoit la bonne configuration
- `app_links` dans `EmployeeResource` → deep link vers la bonne app

### ⚠️ À surveiller
1. **Super Admin mobile** : le endpoint `POST /api/v1/platform/auth/login` fonctionne mais il n'y a pas encore d'app mobile Admin dédiée. À documenter pour l'équipe Flutter.
2. **Comptable et Marketing** : les dashboards existent (`/dashboard/comptable`, `/dashboard/marketing`) mais les contrôleurs dédiés type `HrController` n'existent pas encore (Phase 3/4).
3. **Web client — RH** : la route web `/employees/create` est accessible au RH via `manager_role:principal,rh` ✅ mais la page n'a pas encore de filtre UI pour masquer l'option `manager_role`. À corriger côté frontend.
4. **Employé ordinaire (`role='ordinary'`)** : le `TenantMiddleware` le laisse passer sans company. S'assurer que ces comptes transitoires sont bien bloqués par les contrôleurs.

### 🔜 Phase 3 — À implémenter
- `ComptableController` + routes `/api/v1/comptable/**`
- `MarketingController` + routes `/api/v1/marketing/**`  
- Tests dédiés (même pattern que `HrAppRoutesTest`)
- Mise à jour `MobileExperienceService` pour les modules comptable/marketing

---

## 9. Endpoints de connexion — Récapitulatif pour les apps

### Super Admin
```
POST /api/v1/platform/auth/login
{ "email": "...", "password": "...", "device_name": "Admin Mobile" }
→ token + data.role = "super_admin"
```

### Tenant (Principal, RH, Comptable, Marketing, Employé)
```
POST /api/v1/auth/login
{ "email": "...", "password": "...", "device_name": "Manager Mobile" }
→ token + EmployeeResource (inclut mobile_experience.app.id pour routing Flutter)
```

**L'app Flutter doit lire `mobile_experience.app.id` après login pour diriger vers la bonne expérience :**
- `"manager"` → App Manager
- `"rh"` → App RH
- `"comptable"` → App Comptable (Phase 3)
- `"marketing"` → App Marketing (Phase 4)
- `"employee"` → App Employé
