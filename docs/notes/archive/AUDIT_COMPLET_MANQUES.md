⚠️ CE FICHIER EST ARCHIVÉ — NE PLUS UTILISER COMME RÉFÉRENCE D'ÉTAT
Remplacé par ORCHESTRATION_MAITRE.md v3.3.1+
Les manques listés ici ont été comblés dans les versions 3.3.0 à 3.3.2.
Conservé uniquement pour la traçabilité historique.

---

# CE QUI MANQUE — LEOPARDO RH
# Audit complet au stade actuel | Mars 2026
# À compléter AVANT ou PENDANT le démarrage du code

---

## RÉSUMÉ EXÉCUTIF

| Catégorie | Statut | Priorité |
|-----------|--------|----------|
| Conception (15 docs) | ✅ Complet à 92% | — |
| Wireframes (20 écrans) | ✅ Complet | — |
| API Contrats | ⚠️ 42% seulement (29/70 endpoints) | BLOQUANT pour Jules |
| Arborescence code | ✅ Définie dans ARBORESCENCE_PROJET_COMPLET.md | — |
| Code backend | ❌ 0% | Démarrer maintenant |
| Code frontend | ❌ 0% | Phase 1 semaine 15+ |
| Code mobile | ❌ 0% | Démarrer maintenant (parallèle) |
| Tests | ❌ 0% | À écrire avec le code |
| CI/CD | ❌ 0% | Sprint 0 |
| Modèles Dart | ❌ Manquants | Nécessaire pour Jules |
| Stratégie cache Redis | ❌ Non documentée | Avant PayrollService |
| Stratégie branches Git | ❌ Non documentée | Avant premier commit |

---

## MANQUE 1 — API CONTRATS INCOMPLETS (BLOQUANT)

### Situation actuelle
29 endpoints documentés dans `02_API_CONTRATS.md`.
Environ 70 endpoints nécessaires pour couvrir toute l'application.

### Ce qui manque exactement

**Auth (manquants)**
```
GET  /auth/me              ← Profil de l'utilisateur connecté (nécessaire au démarrage Flutter)
POST /auth/device/fcm      ← Enregistrement token FCM à chaque login Flutter
DELETE /auth/device/fcm    ← Suppression token FCM à la déconnexion
```

**Employés (manquants)**
```
GET  /employees/{id}              ← Fiche complète d'un employé
PUT  /employees/{id}              ← Modifier un employé
DELETE /employees/{id}            ← Archiver un employé (soft delete)
POST /employees/import            ← Import CSV en masse
GET  /employees/{id}/payslips     ← Liste des bulletins d'un employé
```

**Configuration entreprise (entièrement absente)**
```
GET  /departments                 GET  /positions
POST /departments                 POST /positions
PUT  /departments/{id}            PUT  /positions/{id}
DELETE /departments/{id}          DELETE /positions/{id}

GET  /schedules                   GET  /sites
POST /schedules                   POST /sites
PUT  /schedules/{id}              PUT  /sites/{id}
DELETE /schedules/{id}            DELETE /sites/{id}
```

**Appareils ZKTeco / QR (manquants)**
```
GET  /devices
POST /devices
PUT  /devices/{id}
DELETE /devices/{id}
POST /devices/{id}/test-connection    ← Test connectivité ZKTeco
```

**Pointage (manquants)**
```
GET  /attendance/today            ← Log du jour pour l'écran de pointage Flutter
GET  /attendance/{employee_id}    ← Historique d'un employé spécifique
```

**Absences (partiellement documentés)**
```
GET  /absences                    ← Liste toutes les absences (filtres : period, status, employee)
GET  /absences/{id}               ← Détail d'une absence
PUT  /absences/{id}/cancel        ← Annulation par l'employé
GET  /absence-types               ← Liste des types configurés
POST /absence-types               ← Créer un type
PUT  /absence-types/{id}          ← Modifier un type
DELETE /absence-types/{id}        ← Supprimer un type
```

**Avances (partiellement documentés)**
```
GET  /advances                    ← Liste des avances (filtres : status, employee)
GET  /advances/{id}               ← Détail d'une avance
PUT  /advances/{id}/reject        ← Refus (manquant dans les contrats actuels)
PUT  /advances/{id}/repayment     ← Modifier le plan de remboursement
```

**Tâches (partiellement documentés)**
```
GET  /tasks                       ← Liste des tâches (filtres : status, assignee, priority)
GET  /tasks/{id}                  ← Détail d'une tâche
PUT  /tasks/{id}                  ← Modifier une tâche
DELETE /tasks/{id}                ← Supprimer/annuler une tâche
GET  /tasks/{id}/comments         ← Liste des commentaires
GET  /projects                    ← Liste des projets
POST /projects
PUT  /projects/{id}
```

**Évaluations (entièrement absentes)**
```
GET  /evaluations
POST /evaluations
GET  /evaluations/{employee_id}
PUT  /evaluations/{id}/self       ← Auto-évaluation par l'employé
```

**Paie (partiellement documentée)**
```
GET  /payroll                     ← Liste des bulletins (filtre : month, year, status)
GET  /payroll/{id}                ← Détail d'un bulletin
GET  /payroll/{id}/pdf            ← Télécharger le PDF du bulletin
GET  /payroll/status/{job_id}     ← Statut du job async de génération PDF
```

**Rapports (partiellement documentés)**
```
GET  /reports/absences            ← Rapport absences
GET  /reports/payroll             ← Rapport paie récapitulatif
GET  /reports/performance         ← Rapport performance tâches
```

**Notifications (entièrement absentes)**
```
GET  /notifications               ← Liste des notifications (filtres : read, type)
PUT  /notifications/{id}/read     ← Marquer une notification comme lue
PUT  /notifications/read-all      ← Tout marquer comme lu
GET  /notifications/count         ← Compteur non lus (pour le badge cloche)
```

**Paramètres (partiellement documentés)**
```
PUT  /settings                    ← Mettre à jour les paramètres (manquant)
PUT  /settings/apply-hr-model     ← Appliquer un modèle RH par pays
GET  /settings/hr-models          ← Liste des modèles RH disponibles
```

**Super Admin (partiellement documenté)**
```
GET  /admin/companies/{id}        ← Détail d'une entreprise
PUT  /admin/companies/{id}        ← Modifier une entreprise
PUT  /admin/companies/{id}/reactivate ← Réactiver
PUT  /admin/companies/{id}/extend     ← Prolonger l'abonnement
GET  /admin/plans                 ← Liste des plans
POST /admin/plans
PUT  /admin/plans/{id}
GET  /admin/billing               ← Facturation globale
POST /admin/billing/invoice       ← Créer une facture manuelle
GET  /admin/stats                 ← Statistiques globales plateforme
```

### Solution
Compléter `02_API_CONTRATS.md` avec tous ces endpoints **avant la semaine 5** (quand Jules commence les écrans qui en ont besoin). Les endpoints auth et attendance sont prioritaires (semaine 1-2).

---

## MANQUE 2 — STRATÉGIE DE TESTS

### Ce qui manque
Un document définissant quoi tester, comment, et avec quel niveau de couverture.

### Ce qu'il faut créer : `16_STRATEGIE_TESTS.md`

**Tests obligatoires à écrire (Pest Laravel)**
```
TenantIsolationTest          ← Test CRITIQUE : token entreprise A ne peut pas voir données B
AttendanceCheckInTest        ← check_in crée une ligne, check_out la met à jour (pas INSERT)
AttendanceCheckOutTest       ← Vérifier que check-out = UPDATE sur la ligne existante
PayrollCalculationTest       ← Formule complète : vérifier net = brut - cotisations - IR - retenues
PayrollAdvanceDeductionTest  ← Avance déduite + amount_remaining mis à jour + 'repaid' si soldé
LeaveBalanceTest             ← Acquisition mensuelle, déduction à l'approbation, report annuel
GpsZoneTest                  ← Pointage dans la zone = accepté, hors zone = rejeté
BiometricWebhookTest         ← Webhook ZKTeco avec device_token valide/invalide
```

**Couverture minimale**
- Services (PayrollService, AttendanceService, AbsenceService) : 80%
- Controllers : 60% (via tests Feature)
- Isolation tenant : 100% (test critique — aucune régression tolérée)

---

## MANQUE 3 — MODÈLES DART FLUTTER

### Ce qui manque
Les classes Dart avec `fromJson()` et `toJson()` pour tous les objets API.

### Classes à créer dans `lib/shared/models/`

```dart
// employee.dart
class Employee {
  final int id;
  final String matricule;
  final String firstName;
  final String lastName;
  final String email;
  final String role;
  final String? managerRole;
  final double leaveBalance;
  final String status;
  final String? photoUrl;

  factory Employee.fromJson(Map<String, dynamic> json) => Employee(
    id: json['id'],
    matricule: json['matricule'],
    firstName: json['first_name'],
    lastName: json['last_name'],
    email: json['email'],
    role: json['role'],
    managerRole: json['manager_role'],
    leaveBalance: (json['leave_balance'] as num).toDouble(),
    status: json['status'],
    photoUrl: json['photo_url'],
  );

  String get fullName => '$firstName $lastName';
}

// attendance_log.dart
class AttendanceLog {
  final int id;
  final String date;
  final DateTime? checkIn;     // TOUJOURS DateTime — jamais String
  final DateTime? checkOut;
  final double? hoursWorked;
  final double? overtimeHours;
  final String status;
  final String method;
  final bool isManualEdit;

  factory AttendanceLog.fromJson(Map<String, dynamic> json) => AttendanceLog(
    id: json['id'],
    date: json['date'],
    checkIn: json['check_in'] != null ? DateTime.parse(json['check_in']) : null,
    checkOut: json['check_out'] != null ? DateTime.parse(json['check_out']) : null,
    hoursWorked: json['hours_worked']?.toDouble(),
    overtimeHours: json['overtime_hours']?.toDouble(),
    status: json['status'],
    method: json['method'],
    isManualEdit: json['is_manual_edit'] ?? false,
  );
}

// absence.dart, salary_advance.dart, task.dart, payroll.dart, notification.dart
// → même pattern fromJson()
```

---

## MANQUE 4 — STRATÉGIE CACHE REDIS

### Ce qui manque
Un document listant exactement ce qui est mis en cache et les règles d'invalidation.

### Tableau à créer dans `01_PROMPT_MASTER_CLAUDE_CODE.md` (section à ajouter)

| Donnée | Clé Redis | TTL | Invalidation |
|--------|-----------|-----|-------------|
| Paramètres entreprise | `tenant:{uuid}:settings` | 1h | `Cache::tags(['tenant:{uuid}'])->forget('settings')` à chaque PUT /settings |
| Plannings de travail | `tenant:{uuid}:schedules` | 24h | À chaque CRUD schedule |
| Départements | `tenant:{uuid}:departments` | 24h | À chaque CRUD department |
| Jours fériés | `holidays:{country}:{year}` | 365j | Jamais (données statiques) |
| Token → company mapping | `auth:company:{token_hash}` | TTL du token | À la déconnexion |
| Statistiques dashboard | `tenant:{uuid}:dashboard:{date}` | 5 min | TTL automatique |
| Compteur notifications non lues | `tenant:{uuid}:notif:{employee_id}:unread` | 1h | À chaque nouvelle notif / lecture |

---

## MANQUE 5 — CI/CD ET DÉPLOIEMENT AUTOMATIQUE

### Fichiers à créer

**`.github/workflows/tests.yml`**
```yaml
name: Tests
on: [pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_DB: leopardo_test
          POSTGRES_USER: leopardo_user
          POSTGRES_PASSWORD: password
      redis:
        image: redis:7
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install
      - run: cp .env.example .env.testing
      - run: php artisan key:generate --env=testing
      - run: php artisan migrate --env=testing
      - run: php artisan db:seed --env=testing
      - run: php artisan test --parallel
```

**`.github/workflows/deploy.yml`**
```yaml
name: Deploy to o2switch
on:
  push:
    branches: [main]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Deploy via SSH
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.O2SWITCH_HOST }}
          username: ${{ secrets.O2SWITCH_USER }}
          key: ${{ secrets.O2SWITCH_SSH_KEY }}
          script: |
            cd /var/www/leopardo-rh-api
            git pull origin main
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            sudo supervisorctl restart all
```

---

## MANQUE 6 — STRATÉGIE BRANCHES GIT

### Convention à adopter (à coller dans README des 2 repos code)

```
Branches principales
  main     → production (o2switch). Merges via PR uniquement. Tests obligatoires.
  develop  → intégration. Base de toutes les features.

Branches de feature
  feature/auth-multitenant
  feature/module-pointage
  feature/module-absences
  feature/module-paie
  bugfix/checkout-update-not-insert
  hotfix/gps-validation-crash

Convention commits (Conventional Commits)
  feat:     Nouvelle fonctionnalité
  fix:      Correction de bug
  test:     Ajout de tests
  refactor: Refactoring sans changement fonctionnel
  docs:     Mise à jour documentation / API contrats
  chore:    Tâches techniques (deps, config)

Exemples
  feat: add check-in endpoint with server-side timestamp
  fix: checkout now uses UPDATE instead of INSERT (BUG#1)
  test: add tenant isolation test for employee endpoint
  docs: add GET /employees/{id} to API contrats

Règle de merge
  feature/* → develop : PR + 1 review + tous tests verts
  develop → main : PR + validation manuelle + tous tests verts
```

---

## MANQUE 7 — FICHIER CHANGELOG

### À créer à la racine de `leopardo-rh-api/`

```markdown
# CHANGELOG — Leopardo RH API
Format : Keep a Changelog (keepachangelog.com)
Versioning : Semantic Versioning (semver.org)

## [Unreleased]
### À faire
- Tous les endpoints listés dans 02_API_CONTRATS.md

## [0.1.0] - 2026-XX-XX (Sprint 1)
### Ajouté
- POST /auth/login
- POST /auth/logout
- Schéma PostgreSQL public + migrations
- TenantMiddleware multi-schéma
- POST /admin/companies (création tenant)
```

---

## MANQUE 8 — FICHIER GET /attendance/today (CRITIQUE POUR FLUTTER)

### Pourquoi c'est critique
L'écran de pointage Flutter doit savoir dès l'ouverture si l'employé a déjà pointé son arrivée ou non, pour afficher le bon bouton. Sans `GET /attendance/today`, Jules ne peut pas implémenter cet écran correctement.

### Contrat à ajouter dans `02_API_CONTRATS.md`

```
GET /attendance/today
Auth : Bearer token
Params : aucun

Response 200 (employé déjà pointé arrivée) :
{
  "data": {
    "id": 1547,
    "date": "2026-04-15",
    "check_in": "2026-04-15T07:58:00Z",
    "check_out": null,
    "status": "ontime",
    "method": "mobile",
    "hours_worked": null
  }
}

Response 200 (employé pas encore pointé) :
{
  "data": null
}

Response 200 (jour férié ou congé approuvé) :
{
  "data": null,
  "context": "holiday"   ← ou "leave"
}
```

---

## ORDRE DE RÉSOLUTION RECOMMANDÉ

```
MAINTENANT (avant de coder) :
  1. Compléter API contrats : /auth/me, /auth/device/fcm, /attendance/today
     → Claude Code et Jules en ont besoin dès la semaine 1

  2. Créer stratégie branches Git dans les README repos
     → Avant le premier commit

SEMAINE 1-2 (pendant l'infrastructure) :
  3. Créer les modèles Dart de base (Employee, AttendanceLog)
     → Jules en a besoin pour l'écran de pointage semaine 3

  4. Créer .github/workflows/tests.yml
     → Tests automatiques dès le premier PR

SEMAINE 3-4 (pendant le développement pointage) :
  5. Compléter API contrats : /employees/{id}, /departments, /schedules, /sites
     → Avant que Jules attaque les écrans gestionnaire semaine 5

  6. Documenter stratégie cache Redis dans le prompt Claude Code

SEMAINE 5-8 :
  7. Compléter API contrats : /absences, /tasks, /evaluations, /notifications
  8. Créer les modèles Dart restants
  9. Créer CHANGELOG.md

SEMAINE 9-12 :
  10. Créer .github/workflows/deploy.yml
  11. Créer 16_STRATEGIE_TESTS.md complet
```
