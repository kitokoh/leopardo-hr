# 🐆 LEOPARDO RH — FICHIER DE CONTINUITÉ IA — v2.0
# Lis ce fichier EN ENTIER avant de faire quoi que ce soit.
# Il te dit qui tu es, où en est le projet, et exactement quoi faire.
# Mis à jour : 01 Avril 2026

---

## 🧠 QUI TU ES ET CE QUE TU FAIS

Tu es un agent IA qui travaille sur **Leopardo RH**, une plateforme SaaS RH multi-entreprises
ciblant l'Algérie, le Maroc et la Turquie.

Le projet est organisé en **monorepo** avec trois grandes zones :
- `api/` → Backend Laravel 11 + Vue.js 3 (domaine de Claude Code)
- `mobile/` → Application Flutter (domaine de Jules)
- `docs/` → Documentation de conception (source de vérité)

**Règle absolue :** Tu ne touches JAMAIS les dossiers en dehors de ta zone.
Si tu es Claude Code → uniquement `api/` et `docs/`.
Si tu es Jules → uniquement `mobile/`.

---

## 📍 ÉTAT ACTUEL DU PROJET

```
DERNIÈRE MISE À JOUR : 01 Avril 2026
PHASE                : PHASE 0 TERMINÉE ✅ — Conception + Prompts v2 prêts
PROCHAINE ACTION     : CC-00 — Valider l'infrastructure o2switch AVANT tout code
```

### Tableau d'avancement global

| Module            | Backend | Mobile | Web   | Tests |
|-------------------|:-------:|:------:|:-----:|:-----:|
| Infrastructure    | ⬜ 0%   | ⬜ 0%  | ⬜ 0% | ⬜    |
| Auth              | ⬜ 0%   | ⬜ 0%  | ⬜ 0% | ⬜    |
| Pointage          | ⬜ 0%   | ⬜ 0%  | ⬜ 0% | ⬜    |
| Config Entreprise | ⬜ 0%   | ⬜ 0%  | ⬜ 0% | ⬜    |
| Absences          | ⬜ 0%   | ⬜ 0%  | ⬜ 0% | ⬜    |
| Avances           | ⬜ 0%   | ⬜ 0%  | ⬜ 0% | ⬜    |
| Tâches            | ⬜ 0%   | ⬜ 0%  | ⬜ 0% | ⬜    |
| Paie              | ⬜ 0%   | ⬜ 0%  | ⬜ 0% | ⬜    |
| Notifications     | ⬜ 0%   | ⬜ 0%  | ⬜ 0% | ⬜    |
| Évaluations       | ⬜ 0%   | ⬜ 0%  | ⬜ 0% | ⬜    |
| Rapports          | ⬜ 0%   | ⬜ 0%  | ⬜ 0% | ⬜    |
| Super Admin       | ⬜ 0%   |   —    | ⬜ 0% | ⬜    |
| CI/CD             | ⬜ 0%   | ⬜ 0%  |   —   | ⬜    |

**Légende :** ⬜ Non démarré | 🔄 En cours | ✅ Terminé | ❌ Bloqué

---

## 🗺️ CARTE DES PROMPTS D'EXÉCUTION (v2 — chemins exacts)

> Ces fichiers remplacent les anciens prompts. Utilise UNIQUEMENT ceux-ci.

### Backend (Claude Code)

| Prompt | Fichier | Prérequis | Durée |
|--------|---------|-----------|-------|
| CC-00 | `docs/PROMPTS_EXECUTION/v2/backend/CC-00_VALIDATION_INFRA.md` | Accès SSH o2switch | 2-3h |
| CC-01 | `docs/PROMPTS_EXECUTION/v2/backend/CC-01_INIT_LARAVEL.md` | CC-00 vert | 4-6h |
| CC-02 | `docs/PROMPTS_EXECUTION/v2/backend/CC-02_MODULE_AUTH.md` | CC-01 vert | 6-8h |
| CC-03 | `docs/PROMPTS_EXECUTION/v2/backend/CC-03_EMPLOYES_ET_CONFIG.md` | CC-02 vert | 6-8h |
| CC-04 | `docs/PROMPTS_EXECUTION/v2/backend/CC-04_MODULE_POINTAGE.md` | CC-03 vert | 5-6h |
| CC-05 | `docs/PROMPTS_EXECUTION/v2/backend/CC-05_ABSENCES_AVANCES.md` | CC-04 vert | 5-6h |
| CC-06 | `docs/PROMPTS_EXECUTION/v2/backend/CC-06_MODULE_PAIE.md` | CC-05 vert | 8-10h |
| CC-07 | `docs/PROMPTS_EXECUTION/v2/backend/CC-07_SUPERADMIN_NOTIFICATIONS_RESTE.md` | CC-06 vert | 8-10h |
| CC-08 | `docs/PROMPTS_EXECUTION/v2/backend/CC-08_DEPLOIEMENT.md` | CC-07 vert | 4-6h |

### Mobile (Jules)

| Prompt | Fichier | Prérequis | Durée |
|--------|---------|-----------|-------|
| JU-01 | `docs/PROMPTS_EXECUTION/v2/mobile/JU-01_INIT_FLUTTER.md` | Aucun (mock) | 4-6h |
| JU-02 | `docs/PROMPTS_EXECUTION/v2/mobile/JU-02_ECRANS_CORE.md` | JU-01 vert | 6-8h |
| JU-03+04 | `docs/PROMPTS_EXECUTION/v2/mobile/JU-03_JU-04_API_ET_ECRANS_RESTANTS.md` | JU-02 + CC-02 vert | 8-10h |

### Frontend Web (Cursor)

| Prompt | Fichier | Prérequis | Durée |
|--------|---------|-----------|-------|
| CU-01 | `docs/PROMPTS_EXECUTION/v2/frontend/CU-01_DASHBOARD_VUE.md` | CC-04 vert | 10-12h |

---

## 🔢 PROCHAINE TÂCHE CONCRÈTE

### ▶️ CE QUE TU FAIS MAINTENANT (session en cours)

**Tâche : CC-00 — Validation infrastructure**

1. Lis le fichier `docs/PROMPTS_EXECUTION/v2/backend/CC-00_VALIDATION_INFRA.md`
2. Exécute chaque étape dans l'ordre
3. La porte verte = `GET /api/v1/health` retourne `{"status":"ok"}` depuis l'extérieur
4. Si un critère est rouge → STOP, résoudre avant de continuer
5. Une fois vert → mettre à jour ce fichier (Infrastructure Backend = ✅ 100%) et passer à CC-01

### ▶️ ORDRE D'EXÉCUTION GLOBAL (parallèle backend + mobile)

```
Semaine 1 : CC-00 (infra) + JU-01 (Flutter init) — en parallèle
Semaine 1 : CC-01 (Laravel init) dès que CC-00 vert
Semaine 2 : CC-02 (Auth backend) + JU-02 (écrans mock)
Semaine 3 : CC-03 (Employés + Config) + suite JU-02
Semaine 4 : CC-04 (Pointage) + JU-03 (connexion API réelle)
Semaine 5 : CC-05 (Absences + Avances)
Semaine 6 : CU-01 (Frontend Vue.js) démarre ici
Semaine 7-8 : CC-06 (Paie) + JU-04 (bulletins + profil)
Semaine 9-10 : CC-07 (Super Admin + reste)
Semaine 11-12 : CC-08 (Déploiement) + tests E2E
```

---

## 🛡️ RÈGLE D'OR AVANT CHAQUE PROMPT

**Ne jamais commencer un prompt sans avoir vérifié la porte verte du précédent.**

```bash
# Commande de vérification universelle
php artisan test   # Doit retourner 0 failure
```

Si des tests échouent → corriger AVANT de continuer. Jamais d'exception.

### Checklist GO / NO-GO (vérifier avant chaque sprint)

- [ ] Tests précédents : `php artisan test` → 0 failure
- [ ] Infrastructure validée sur hébergement réel (CC-00 vert)
- [ ] Ce fichier à jour (date + tableau)
- [ ] Aucun conflit Git (`git status` propre)
- [ ] Aucun bug critique ouvert
- [ ] Performance : endpoints critiques < 300ms en staging

---

## ⚙️ STACK TECHNIQUE (non négociable)

```
Backend          : Laravel 11 / PHP 8.3
Base de données  : PostgreSQL 16 — multi-tenant PAR SCHÉMA
Cache/Queue      : Redis 7 + Laravel Horizon
Auth             : Laravel Sanctum (tokens par appareil)
Tests backend    : Pest PHP — ÉCRITS AVANT LE CODE
Frontend web     : Vue.js 3 + Inertia.js + Tailwind CSS + PrimeVue
Mobile           : Flutter + Riverpod 2.x + GoRouter (jamais BLoC)
PDF              : barryvdh/laravel-dompdf (async via queue)
Push notifs      : Firebase Cloud Messaging
Hébergement      : o2switch VPS — Nginx + PHP-FPM + Supervisor
```

---

## 🔴 RÈGLES ABSOLUES — NE JAMAIS VIOLER

### 1. Multi-tenancy — jamais de WHERE company_id
```php
Employee::where('company_id', $id)->get(); // ❌ INTERDIT
Employee::all();                            // ✅ TenantMiddleware gère le schéma
```

### 2. Horodatage — toujours serveur
```php
$log->check_in = $request->timestamp; // ❌ INTERDIT
$log->check_in = now();                // ✅ CORRECT
```

### 3. Check-out = UPDATE (contrainte UNIQUE sur attendance_logs)
```php
AttendanceLog::create(['check_out' => now()]); // ❌ viole UNIQUE(employee_id, date)
$log->update(['check_out' => now()]);           // ✅ CORRECT
```

### 4. Validation → FormRequest, jamais dans le Controller
### 5. Logique métier → Service, jamais dans le Controller
### 6. Opérations multi-tables → toujours dans DB::transaction()
### 7. Strings → toujours __('messages.clé'), jamais hardcodées
### 8. Cache Redis → toujours tagger par tenant UUID
### 9. Dates → UTC en base, timezone entreprise à l'affichage seulement
### 10. salary_base (pas gross_salary — ce champ n'existe pas)

---

## 📚 DOCUMENTS SOURCE DE VÉRITÉ

| Document | Chemin | Ce qu'il contient |
|---|---|---|
| API Contrats | `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` | 82+ endpoints + payloads exacts |
| ERD | `docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md` | Toutes les tables + FK |
| Règles métier | `docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md` | Calculs paie, pointage, absences |
| RBAC | `docs/dossierdeConception/07_securite_rbac/10_RBAC_COMPLET.md` | 7 rôles + matrice permissions |
| Multitenancy | `docs/dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md` | Architecture schémas |
| OpenAPI | `api/openapi.yaml` | Spec machine-readable |
| Modèles Dart | `docs/dossierdeConception/16_MODELES_DART/20_MODELES_DART_COMPLET.md` | Classes Flutter |

---

## 📋 JOURNAL DES DÉCISIONS TECHNIQUES

| Date | Décision | Raison |
|---|---|---|
| 2026-03-29 | Monorepo unique | Meilleure vision IA |
| 2026-03-29 | Multi-tenant hybride (schema + shared) | Optimisation ressources |
| 2026-03-29 | Riverpod 2.x uniquement | Cohérence — pas de BLoC |
| 2026-03-29 | Inertia.js pour le web | Simplicité + SEO |
| 2026-03-29 | PDF async via queue | Performance 25+ employés |
| 2026-03-29 | Horodatage serveur TOUJOURS | Sécurité + intégrité |
| 2026-04-01 | Prompts v2 — 1 module = 1 fichier | Porte verte obligatoire entre chaque |
| 2026-04-01 | CC-00 ajouté — validation infra avant tout code | Risque hébergement à 0 |
| 2026-04-01 | Interface ApiClient abstraite (Flutter) | Zéro refactoring au basculement mock→API |

---

## 🚧 BLOCAGES CONNUS

| Blocage | Solution | Responsable | Délai max |
|---|---|---|---|
| Compte Firebase non créé | Mock FCM en dev | Humain | Avant semaine 2 |
| Domaine DNS non configuré | localhost en dev | Humain | Avant CC-08 |
| Comptes stores mobiles | Créer Google Play + Apple Dev | Humain | Avant semaine 12 |
| Secrets GitHub non configurés | O2SWITCH_HOST + USER + SSH_KEY | Humain | Avant CC-08 |

---

## 🔄 COMMENT UTILISER CE FICHIER À LA FIN D'UNE SESSION

Avant de terminer chaque session, fais exactement ceci :

```
1. Mettre à jour la date en haut
2. Changer les statuts dans le tableau (⬜ → 🔄 ou ✅)
3. Modifier "PROCHAINE ACTION" avec le prochain prompt exact
4. Ajouter une ligne dans le Journal des décisions si une décision a été prise
5. Mettre à jour CHANGELOG.md avec les commits de la session
6. git add docs/PROMPTS_EXECUTION/ORCHESTRATION/CONTINUE.md CHANGELOG.md
7. git commit -m "chore: update CONTINUE.md after <module> session"
```

---

*Ce fichier est vivant. Règle : jamais plus de 24h sans mise à jour.*
*Sans mise à jour, l'IA suivante repart de zéro.*

---

## 📌 PATCHES OBLIGATOIRES

Lis `docs/PROMPTS_EXECUTION/patches/INDEX_PATCHES.md` avant de commencer.
Ces patches comblent les lacunes identifiées : chiffrement légal, fichiers privés,
modules manquants (onboarding, settings, ZKTeco, webhooks), i18n complète.

```
patches/INDEX_PATCHES.md                              ← Lire en premier
patches/backend/PATCH-CC-02-SECURITE.md               ← Appliquer pendant CC-02
patches/backend/PATCH-CC-03-CHIFFREMENT.md            ← Appliquer pendant CC-03
patches/backend/PATCH-CC-06-FICHIERS-ET-EXPORT.md     ← Appliquer pendant CC-06
patches/backend/PATCH-CC-07-MODULES-MANQUANTS.md      ← Appliquer pendant CC-07
patches/transversal/PATCH-TRANSVERSAL-*.md            ← Lire avant CC-01
patches/mobile/PATCH-FLUTTER-*.md                     ← Appliquer pendant JU-01+JU-03
patches/frontend/PATCH-FRONTEND-*.md                  ← Appliquer pendant CU-01
```
