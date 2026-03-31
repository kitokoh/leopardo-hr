# 🐆 LEOPARDO RH — FICHIER DE CONTINUITÉ IA
# Lis ce fichier EN ENTIER avant de faire quoi que ce soit.
# Il te dit qui tu es, où en est le projet, et exactement quoi faire.

---

## 🧠 QUI TU ES ET CE QUE TU FAIS

Tu es un agent IA qui travaille sur **Leopardo RH**, une plateforme SaaS RH multi-entreprises
ciblant l'Algérie, le Maroc et la Turquie.

Le projet est organisé en **monorepo** avec trois grandes zones :
- `api/` → Backend Laravel 11 + Vue.js 3 (ton domaine si tu es Claude Code)
- `mobile/` → Application Flutter (domaine de Jules)
- `docs/` → Documentation de conception (source de vérité)

**Règle absolue :** Tu ne touches JAMAIS les dossiers en dehors de ta zone.
Si tu es Claude Code → uniquement `api/` et `docs/`.
Si tu es Jules → uniquement `mobile/`.

---

## 📍 ÉTAT ACTUEL DU PROJET (à mettre à jour après chaque session)

```
DERNIÈRE MISE À JOUR : 31 Mars 2026
PHASE                : PHASE 0 TERMINÉE — Conception 100% ✅
PROCHAINE ACTION     : CC-01 — Initialiser Laravel 11 (backend) + JU-01 Flutter (mobile) en parallèle
```

### Tableau d'avancement global

| Module            | Backend | Mobile | Web  | Tests |
|-------------------|:-------:|:------:|:----:|:-----:|
| Infrastructure    | ⬜ 0%  | ⬜ 0% | ⬜ 0% | ⬜  |
| Auth              | ⬜ 0%  | ⬜ 0% | ⬜ 0% | ⬜  |
| Pointage          | ⬜ 0%  | ⬜ 0% | ⬜ 0% | ⬜  |
| Config Entreprise | ⬜ 0%  | ⬜ 0% | ⬜ 0% | ⬜  |
| Absences          | ⬜ 0%  | ⬜ 0% | ⬜ 0% | ⬜  |
| Avances           | ⬜ 0%  | ⬜ 0% | ⬜ 0% | ⬜  |
| Tâches            | ⬜ 0%  | ⬜ 0% | ⬜ 0% | ⬜  |
| Paie              | ⬜ 0%  | ⬜ 0% | ⬜ 0% | ⬜  |
| Notifications     | ⬜ 0%  | ⬜ 0% | ⬜ 0% | ⬜  |
| Évaluations       | ⬜ 0%  | ⬜ 0% | ⬜ 0% | ⬜  |
| Rapports          | ⬜ 0%  | ⬜ 0% | ⬜ 0% | ⬜  |
| Super Admin       | ⬜ 0%  |   —   | ⬜ 0% | ⬜  |
| CI/CD             | ⬜ 0%  | ⬜ 0% |   —   | ⬜  |

**Légende :** ⬜ Non démarré | 🔄 En cours | ✅ Terminé | ❌ Bloqué

---

## 🗂️ OÙ SE TROUVENT LES INFORMATIONS

Avant de coder, tu DOIS avoir lu les documents suivants (ils font loi) :

| Document | Chemin | Rôle |
|---|---|---|
| Prompt Maître Claude Code | `docs/PROMPTS_EXECUTION/99_prompts_execution/01_PROMPT_MASTER_CLAUDE_CODE.md` | Règles de code absolues — lis en premier |
| API Contrats (82+ endpoints) | `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` | Source de vérité des endpoints |
| ERD base de données | `docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md` | Source de vérité des tables |
| Règles métier | `docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md` | Calculs paie, pointage, absences |
| Multitenancy | `docs/dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md` | Architecture multi-schéma PostgreSQL |
| RBAC | `docs/dossierdeConception/07_securite_rbac/10_RBAC_COMPLET.md` | Matrice des 7 rôles et permissions |
| OpenAPI | `api/openapi.yaml` | 76 endpoints machine-readable |
| Schéma SQL | (dans docs) `07_SCHEMA_SQL_COMPLET.sql` | PostgreSQL complet |

---

## 🔢 PROCHAINE TÂCHE CONCRÈTE

### ▶️ SI C'EST UNE NOUVELLE SESSION — fais exactement ceci :

**Étape 0 — Preuve avant code (règle de l'ami)**
Avant d'écrire le moindre vrai code métier, valide l'infrastructure :
1. Crée un prototype minimal Laravel 11 + PostgreSQL + Redis + Queue
2. Lance `php artisan serve` → vérifie que `GET /api/v1/health` retourne `{"status":"ok"}`
3. Si ça marche → continue. Si ça ne marche pas → résous le problème d'hébergement MAINTENANT.

**Étape 1 — Lis le prompt d'exécution correspondant**

| Si tu travailles sur... | Lis ce fichier |
|---|---|
| Backend Laravel (init) | `docs/PROMPTS_EXECUTION/backend/CC-01_INIT_LARAVEL.md` |
| Backend Auth | `docs/PROMPTS_EXECUTION/backend/CC-02_MODULE_AUTH.md` |
| Backend modules métier | `docs/PROMPTS_EXECUTION/backend/CC-03_A_CC-06_MODULES.md` |
| Mobile Flutter | `docs/PROMPTS_EXECUTION/mobile/JU-01_A_JU-04_FLUTTER.md` |
| Frontend Vue.js | `docs/PROMPTS_EXECUTION/frontend/CU-01_ET_AGENTS.md` |

**Étape 2 — Écris les tests AVANT le code**
```
Règle absolue : aucun Controller ne s'écrit sans son test Pest correspondant rédigé en premier.
```

**Étape 3 — Code, teste, commite**
```
Chaque commit doit faire passer les tests CI.
Format de commit : feat: <module> - <description courte>
```

**Étape 4 — Mets à jour ce fichier**
À la fin de chaque session, mets à jour :
- La date en haut
- Le tableau d'avancement (⬜ → 🔄 ou ✅)
- La section "PROCHAINE ACTION"
- Le CHANGELOG.md du projet

---

## ⚙️ STACK TECHNIQUE (non négociable)

```
Backend     : Laravel 11 / PHP 8.3
Base de données : PostgreSQL 16 — multi-tenant PAR SCHÉMA
Cache/Queue : Redis 7 + Laravel Horizon
Auth        : Laravel Sanctum (tokens par appareil)
Tests       : Pest PHP (toujours écrits AVANT le code)
Frontend web: Vue.js 3 + Inertia.js + Tailwind CSS
Mobile      : Flutter + Riverpod 2.x + GoRouter (pas de BLoC ni Provider)
PDF         : barryvdh/laravel-dompdf (async via queue)
Push notifs : Firebase Cloud Messaging
Hébergement : o2switch VPS — Nginx + PHP-FPM + Supervisor
```

---

## 🔴 RÈGLES ABSOLUES — NE JAMAIS VIOLER

### 1. Multi-tenancy
```php
// ❌ INTERDIT — filtre company_id dans un modèle tenant
Employee::where('company_id', $id)->get();

// ✅ CORRECT — le TenantMiddleware a déjà switché le schéma
Employee::all();
```

### 2. Horodatage
```php
// ❌ INTERDIT — timestamp venant du client
$log->check_in = $request->timestamp;

// ✅ CORRECT — toujours le serveur
$log->check_in = now();
```

### 3. Check-out = UPDATE, jamais INSERT
La table `attendance_logs` a une contrainte UNIQUE sur `(employee_id, date)`.
Le check-in crée la ligne. Le check-out la MET À JOUR.

### 4. Validation → toujours dans un FormRequest
```php
// ❌ INTERDIT dans un Controller
$request->validate([...]);

// ✅ CORRECT
class StoreAttendanceRequest extends FormRequest { ... }
```

### 5. Logique métier → toujours dans un Service
```php
// ❌ INTERDIT dans un Controller
$hours = ($checkOut - $checkIn) / 3600;

// ✅ CORRECT
$hours = $this->attendanceService->calculateHoursWorked($log, $schedule);
```

### 6. Transactions multi-tables
```php
// Obligatoire pour : approbation absence, avance, génération paie
DB::transaction(function () { ... });
```

### 7. Format de réponse API strict
```php
// Succès liste
return response()->json(['data' => Resource::collection($items), 'meta' => [...] ]);

// Succès unique
return response()->json(['data' => new Resource($item), 'message' => __('messages.created')], 201);

// Erreur métier
return response()->json(['error' => 'CODE_ERREUR', 'message' => __('messages.erreur')], 422);
```

### 8. Jamais de string en dur — toujours i18n
```php
// ❌ return response()->json(['message' => 'Créé avec succès']);
// ✅ return response()->json(['message' => __('messages.created')]);
```

### 9. Cache Redis — toujours tagger par tenant
```php
Cache::tags(['tenant:' . $tenantUuid])->remember('settings', 3600, fn() => ...);
Cache::tags(['tenant:' . $tenantUuid])->forget('settings'); // à chaque PUT /settings
```

### 10. Dates — toujours UTC en base, timezone entreprise à l'affichage uniquement

---

## 🛡️ STRATÉGIE DE RÉDUCTION DU RISQUE (conseils de l'ami)

Avant d'avancer sur chaque module, applique cette méthode :

**Transformer chaque hypothèse en test automatique immédiat.**

| Hypothèse | Test à faire MAINTENANT |
|---|---|
| "Le multitenancy fonctionnera" | Crée 2 tenants aujourd'hui, vérifie qu'ils ne voient pas les mêmes employés |
| "Le calcul de paie sera correct" | Écris 10 cas de paie réels (DZ, MA, TR) et automatise-les en Pest |
| "Le mobile et le backend resteront synchronisés" | Génère le modèle Dart automatiquement depuis `api/openapi.yaml` à chaque commit |
| "L'hébergement est compatible" | Déploie un prototype minimal sur o2switch AVANT d'écrire le vrai code |

**Checklist GO / NO-GO avant chaque sprint :**
- [ ] Infrastructure validée (prototype tourne sur hébergement réel)
- [ ] Tests passent (`php artisan test` → 0 échec)
- [ ] Documentation à jour (ce fichier + CHANGELOG.md)
- [ ] Aucun conflit Git
- [ ] Aucun bug critique ouvert
- [ ] Sécurité validée (multitenancy isolé, SQL injection impossible)
- [ ] Performance acceptable (< 300ms sur endpoints critiques)
- [ ] Rollback testé (tu sais comment revenir en arrière)

---

## 📋 JOURNAL DES DÉCISIONS TECHNIQUES

| Date | Décision | Raison |
|---|---|---|
| 2026-03-29 | Monorepo unique (api + mobile + docs) | Meilleure vision IA |
| 2026-03-29 | Multi-tenant hybride (schema + shared) | Optimisation ressources |
| 2026-03-29 | Riverpod 2.x UNIQUEMENT pour Flutter | Cohérence — pas de BLoC |
| 2026-03-29 | Inertia.js pour le web (pas d'API séparée) | Simplicité + SEO |
| 2026-03-29 | PDF génération asynchrone (queue job) | Performance 25+ employés |
| 2026-03-29 | Horodatage serveur TOUJOURS | Sécurité + intégrité |

---

## 🚧 BLOCAGES CONNUS

| Blocage | Solution | Responsable |
|---|---|---|
| Pas encore de compte Firebase | Utiliser mock FCM en dev | Humain (avant W1) |
| Pas encore de domaine DNS | Utiliser localhost en dev | Humain (avant déploiement) |
| Comptes stores mobiles non créés | Créer avant la semaine 12 | Humain |

---

## 🔄 COMMENT CONTINUER EXACTEMENT

Quand tu reprends après une pause, fais dans cet ordre :

1. **Lis ce fichier** (tu es en train de le faire ✅)
2. **Vérifie le tableau d'avancement** — identifie la dernière tâche ✅ et la prochaine ⬜
3. **Lis le prompt d'exécution correspondant** à la prochaine tâche (voir tableau plus haut)
4. **Vérifie la checklist GO / NO-GO** — ne commence pas si un point est rouge
5. **Écris le test Pest d'abord** — puis le code jusqu'à ce que le test passe
6. **Commite** avec le bon format de message
7. **Mets à jour ce fichier** : date, tableau, prochaine action

---

## 🔗 INDEX RAPIDE DES FICHIERS CLÉS

```
docs/PROMPTS_EXECUTION/ORCHESTRATION/ORCHESTRATION_MAITRE.md  ← tableau de bord global
docs/PROMPTS_EXECUTION/backend/CC-01_INIT_LARAVEL.md          ← init Laravel
docs/PROMPTS_EXECUTION/backend/CC-02_MODULE_AUTH.md           ← module Auth
docs/PROMPTS_EXECUTION/backend/CC-03_A_CC-06_MODULES.md       ← modules métier
docs/PROMPTS_EXECUTION/mobile/JU-01_A_JU-04_FLUTTER.md        ← Flutter complet
docs/PROMPTS_EXECUTION/frontend/CU-01_ET_AGENTS.md            ← Vue.js + landing page
docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md ← 82+ endpoints
docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md               ← base de données
docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md                ← calculs métier
docs/dossierdeConception/07_securite_rbac/10_RBAC_COMPLET.md                 ← permissions
docs/dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md         ← multi-tenant
api/openapi.yaml                                                              ← spec OpenAPI
CHANGELOG.md                                                                  ← historique
```

---

*Ce fichier est vivant. Il doit être mis à jour après CHAQUE session de travail.*
*Sans mise à jour, la prochaine IA ne saura pas où elle en est.*
*Règle : jamais plus de 24h sans mise à jour de ce fichier.*
