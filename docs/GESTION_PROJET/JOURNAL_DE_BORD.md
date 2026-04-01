# 📋 JOURNAL DE BORD — LEOPARDO RH
# SOURCE DE VÉRITÉ OPÉRATIONNELLE
# Mettre à jour après CHAQUE session IA — sans exception
# Version 4.0 | 31 Mars 2026

---

## ⚡ ÉTAT INSTANTANÉ DU PROJET

```
DATE MAJ         : 31 Mars 2026
PHASE            : PHASE 0 — Conception ✅ TERMINÉE (v3.3.3)
CODE             : 0% — PAS ENCORE DÉMARRÉ
PROCHAINE TÂCHE  : CC-01 — Init Laravel (Backend)
PROCHAINE TÂCHE  : JU-01 — Init Flutter (Mobile) — EN PARALLÈLE
BLOQUANTS        : Infrastructure o2switch + Firebase + GitHub (à faire par le chef de projet)
```

---

## 👥 QUI FAIT QUOI — MATRICE COMPLÈTE

| Agent | Rôle | Prompts | Quand |
|-------|------|---------|-------|
| **Claude Code** | Backend Laravel 11 (API REST) | CC-01 → CC-02 → CC-03 | Sprint 1 (Semaine 1+) |
| **Jules** (ou Claude Code) | App Flutter mobile | JU-01 → JU-04 | Sprint 1 (parallèle CC-01) |
| **Cursor** (ou Claude Code) | Frontend Vue.js/Inertia | CU-01 | Sprint 2 (après CC-02 validé) |
| **Claude (chat)** | Review, debug, conception | — | À la demande |
| **Toi** | Chef de projet : infra, validation, ce journal | — | En permanence |

> **RÈGLE ABSOLUE** : Chaque session IA reçoit UNE seule tâche avec son prompt.
> Elle lit ce journal en PREMIER pour connaître l'état du projet.
> Elle met à jour ce journal en DERNIER avant de terminer.

---

## 📊 TABLEAU D'AVANCEMENT (mise à jour en temps réel)

### BACKEND — Laravel 11

| # | Tâche | Prompt | Agent | Statut | Date début | Date fin |
|---|-------|--------|-------|--------|-----------|---------|
| B1 | Init Laravel + PostgreSQL + Redis + Seeders | CC-01 | Claude Code | ⬜ À faire | — | — |
| B2 | Auth (login, register, FCM, guards) | CC-02 | Claude Code | ⬜ À faire | — | — |
| B3 | Config entreprise (depts, postes, plannings, sites) | CC-03 | Claude Code | ⬜ À faire | — | — |
| B4 | Employés + import CSV + PlanLimit | CC-04 | Claude Code | ⬜ À faire | — | — |
| B5 | Pointage (check-in/out, ZKTeco, GPS, HS) | CC-05 | Claude Code | ⬜ À faire | — | — |
| B6 | Absences + Types d'absence | CC-06 | Claude Code | ⬜ À faire | — | — |
| B7 | Avances sur salaire | CC-07 | Claude Code | ⬜ À faire | — | — |
| B8 | Paie (PayrollService + PDF + export banque) | CC-08 | Claude Code | ⬜ À faire | — | — |
| B9 | Tâches + Projets + Évaluations | CC-09 | Claude Code | ⬜ À faire | — | — |
| B10 | Notifications (DB + FCM + SSE) | CC-10 | Claude Code | ⬜ À faire | — | — |
| B11 | Super Admin + Facturation + Crons | CC-11 | Claude Code | ⬜ À faire | — | — |
| B12 | Tests complets Pest (isolation, paie, pointage) | CC-12 | Claude Code | ⬜ À faire | — | — |

### MOBILE — Flutter

| # | Tâche | Prompt | Agent | Statut | Date début | Date fin |
|---|-------|--------|-------|--------|-----------|---------|
| M1 | Init Flutter + packages + mocks + RTL | JU-01 | Jules | ⬜ À faire | — | — |
| M2 | Auth + Login + Profil + FCM | JU-02 | Jules | ⬜ À faire | — | — |
| M3 | Pointage (GPS + photo + historique) | JU-03 | Jules | ⬜ À faire | — | — |
| M4 | Absences + Avances + Tâches + Bulletins | JU-04 | Jules | ⬜ À faire | — | — |

### FRONTEND WEB — Vue.js / Inertia

| # | Tâche | Prompt | Agent | Statut | Date début | Date fin |
|---|-------|--------|-------|--------|-----------|---------|
| F1 | Init Vue.js + layouts + RTL arabe + dashboard | CU-01 | Cursor | ⬜ À faire | — | — |

### INFRASTRUCTURE (toi — avant toute session IA)

| Tâche | Statut | Notes |
|-------|--------|-------|
| Acheter domaine leopardo-rh.com | ⬜ | Namecheap / OVH |
| DNS api. / app. / admin. → o2switch | ⬜ | Après achat domaine |
| SSL Let's Encrypt (cPanel) | ⬜ | Après DNS |
| PostgreSQL o2switch (leopardo_db + user) | ⬜ | Voir SPRINT_0_CHECKLIST.md |
| Redis activé | ⬜ | |
| Firebase projet créé | ⬜ | console.firebase.google.com |
| google-services.json récupéré | ⬜ | Pour Flutter Android |
| GoogleService-Info.plist récupéré | ⬜ | Pour Flutter iOS |
| Repo GitHub créé (monorepo) | ⬜ | |
| Secrets GitHub Actions configurés | ⬜ | Voir SPRINT_0_CHECKLIST.md |

---

## 📝 LOG DES SESSIONS IA

> Copier-coller ce template après chaque session et le remplir.

```
════════════════════════════════
SESSION #[N] — [NOM DE LA TÂCHE]
════════════════════════════════
Date         : JJ/MM/AAAA HH:MM
Agent        : Claude Code / Jules / Cursor / Claude Chat
Prompt utilisé : CC-XX / JU-XX / CU-XX
Durée        : ~X heures
Statut final : ✅ Terminée | 🔄 Incomplète | ❌ Bloquée

CE QUI A ÉTÉ ACCOMPLI :
- [x] ...
- [x] ...

PROBLÈMES RENCONTRÉS :
- Problème : ...
  Solution appliquée : ...

DÉCISIONS PRISES EN COURS DE SESSION :
- ...

FICHIERS MODIFIÉS / CRÉÉS :
- api/app/...
- api/database/...

TESTS QUI PASSENT :
- ...

PROCHAINE SESSION :
- Tâche : B[N+1] — [Nom]
- Prompt : CC-XX
- Prérequis vérifiés : ✅ / ❌
════════════════════════════════
```

### Historique des sessions

*(Aucune session de code — Conception terminée le 31/03/2026 — Prêt à coder)*

---

## 🔴 BLOCAGES EN COURS

*(Aucun — en attente de l'infrastructure pour démarrer CC-01)*

---

## ✅ DÉCISIONS ARCHITECTURALES FIGÉES (NE PAS REMETTRE EN QUESTION)

| # | Sujet | Décision | Raison | Date |
|---|-------|----------|--------|------|
| D1 | FK hiérarchie employees | `manager_id` (auto-ref) | Unifié SQL/ERD/Dart | 31/03 |
| D2 | Statut employees | `status VARCHAR` : active/suspended/archived | Plus riche que is_active | 31/03 |
| D3 | FCM tokens | Table `employee_devices` (pas JSONB dans employees) | Scalable multi-device | 31/03 |
| D4 | Notifs temps réel web | SSE (Server-Sent Events) | Compatible o2switch, pas de Reverb | 31/03 |
| D5 | Stockage fichiers MVP | Local Laravel → Cloudflare R2 Phase 2 | Zéro coût MVP | 31/03 |
| D6 | salary_base vs gross_salary | `salary_base` = employees (fixe) / `gross_salary` = payrolls (calculé) | Deux choses différentes | 31/03 |
| D7 | Langues | fr/ar/en/tr (JAMAIS es) | Marchés cibles | 31/03 |
| D8 | Grâce abonnement | 3 jours lecture seule après expiration | Voir CheckSubscription spec | 31/03 |
| D9 | Audit logs | Observer Eloquent (pas manuel) | Cohérence, zéro oubli | 31/03 |
| D10 | Timezone pointage | Calcul en timezone entreprise (pas UTC) | Voir REGLES_METIER §4 | 31/03 |
| D11 | Multi-tenant | JAMAIS `WHERE company_id` dans les controllers tenant | TenantMiddleware gère tout | 31/03 |
| D12 | Horodatage | `now()` serveur — JAMAIS timestamp du client | Sécurité / fraude | 31/03 |

---

## 📁 INDEX RAPIDE — FICHIERS PAR USAGE

### Démarrer une session Backend (Claude Code)
```
docs/GESTION_PROJET/CONTEXTE_SESSION_IA.md          ← LIRE EN PREMIER
docs/PROMPTS_EXECUTION/backend/CC-01_INIT_LARAVEL.md
docs/PROMPTS_EXECUTION/backend/CC-02_MODULE_AUTH.md
docs/PROMPTS_EXECUTION/backend/CC-03_A_CC-06_MODULES.md
docs/PROMPTS_EXECUTION/99_prompts_execution/01_PROMPT_MASTER_CLAUDE_CODE.md
```

### Démarrer une session Mobile (Jules)
```
docs/GESTION_PROJET/CONTEXTE_SESSION_IA.md          ← LIRE EN PREMIER
docs/PROMPTS_EXECUTION/mobile/JU-01_A_JU-04_FLUTTER.md
docs/PROMPTS_EXECUTION/06_PROMPT_MASTER_JULES_FLUTTER.md
```

### Démarrer une session Frontend (Cursor)
```
docs/GESTION_PROJET/CONTEXTE_SESSION_IA.md          ← LIRE EN PREMIER
docs/PROMPTS_EXECUTION/frontend/CU-01_ET_AGENTS.md
```

### Référence technique
```
docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md  ← 82+ endpoints
docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md                ← BDD
docs/dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql             ← SQL
docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md                 ← Métier
docs/dossierdeConception/07_securite_rbac/10_RBAC_COMPLET.md                  ← RBAC
api/openapi.yaml                                                                ← OpenAPI 3.0.3
```
