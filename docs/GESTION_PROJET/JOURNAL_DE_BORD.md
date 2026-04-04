# ðŸ“‹ JOURNAL DE BORD â€” LEOPARDO RH
# SOURCE DE VÃ‰RITÃ‰ OPÃ‰RATIONNELLE
# Mettre Ã  jour aprÃ¨s CHAQUE session IA â€” sans exception
# Version 4.0 | 31 Mars 2026

---

## âš¡ Ã‰TAT INSTANTANÃ‰ DU PROJET

```
DATE MAJ         : 31 Mars 2026
PHASE            : PHASE 0 â€” Conception âœ… TERMINÃ‰E (v3.3.3)
CODE             : 0% â€” PAS ENCORE DÃ‰MARRÃ‰
PROCHAINE TÃ‚CHE  : CC-01 â€” Init Laravel (Backend)
PROCHAINE TÃ‚CHE  : JU-01 â€” Init Flutter (Mobile) â€” EN PARALLÃˆLE
BLOQUANTS        : Infrastructure o2switch + Firebase + GitHub (Ã  faire par le chef de projet)
```

---

## ðŸ‘¥ QUI FAIT QUOI â€” MATRICE COMPLÃˆTE

| Agent | RÃ´le | Prompts | Quand |
|-------|------|---------|-------|
| **Claude Code** | Backend Laravel 11 (API REST) | CC-01 â†’ CC-02 â†’ CC-03 | Sprint 1 (Semaine 1+) |
| **Jules** (ou Claude Code) | App Flutter mobile | JU-01 â†’ JU-04 | Sprint 1 (parallÃ¨le CC-01) |
| **Cursor** (ou Claude Code) | Frontend Vue.js/Inertia | CU-01 | Sprint 2 (aprÃ¨s CC-02 validÃ©) |
| **Claude (chat)** | Review, debug, conception | â€” | Ã€ la demande |
| **Toi** | Chef de projet : infra, validation, ce journal | â€” | En permanence |

> **RÃˆGLE ABSOLUE** : Chaque session IA reÃ§oit UNE seule tÃ¢che avec son prompt.
> Elle lit ce journal en PREMIER pour connaÃ®tre l'Ã©tat du projet.
> Elle met Ã  jour ce journal en DERNIER avant de terminer.

---

## ðŸ“Š TABLEAU D'AVANCEMENT (mise Ã  jour en temps rÃ©el)

### BACKEND â€” Laravel 11

| # | TÃ¢che | Prompt | Agent | Statut | Date dÃ©but | Date fin |
|---|-------|--------|-------|--------|-----------|---------|
| B1 | Init Laravel + PostgreSQL + Redis + Seeders | CC-01 | Claude Code | â¬œ Ã€ faire | â€” | â€” |
| B2 | Auth (login, register, FCM, guards) | CC-02 | Claude Code | â¬œ Ã€ faire | â€” | â€” |
| B3 | Config entreprise (depts, postes, plannings, sites) | CC-03 | Claude Code | â¬œ Ã€ faire | â€” | â€” |
| B4 | EmployÃ©s + import CSV + PlanLimit | CC-04 | Claude Code | â¬œ Ã€ faire | â€” | â€” |
| B5 | Pointage (check-in/out, ZKTeco, GPS, HS) | CC-05 | Claude Code | â¬œ Ã€ faire | â€” | â€” |
| B6 | Absences + Types d'absence | CC-06 | Claude Code | â¬œ Ã€ faire | â€” | â€” |
| B7 | Avances sur salaire | CC-07 | Claude Code | â¬œ Ã€ faire | â€” | â€” |
| B8 | Paie (PayrollService + PDF + export banque) | CC-08 | Claude Code | â¬œ Ã€ faire | â€” | â€” |
| B9 | TÃ¢ches + Projets + Ã‰valuations | CC-09 | Claude Code | â¬œ Ã€ faire | â€” | â€” |
| B10 | Notifications (DB + FCM + SSE) | CC-10 | Claude Code | â¬œ Ã€ faire | â€” | â€” |
| B11 | Super Admin + Facturation + Crons | CC-11 | Claude Code | â¬œ Ã€ faire | â€” | â€” |
| B12 | Tests complets Pest (isolation, paie, pointage) | CC-12 | Claude Code | â¬œ Ã€ faire | â€” | â€” |

### MOBILE â€” Flutter

| # | TÃ¢che | Prompt | Agent | Statut | Date dÃ©but | Date fin |
|---|-------|--------|-------|--------|-----------|---------|
| M1 | Init Flutter + packages + mocks + RTL | JU-01 | Jules | â¬œ Ã€ faire | â€” | â€” |
| M2 | Auth + Login + Profil + FCM | JU-02 | Jules | â¬œ Ã€ faire | â€” | â€” |
| M3 | Pointage (GPS + photo + historique) | JU-03 | Jules | â¬œ Ã€ faire | â€” | â€” |
| M4 | Absences + Avances + TÃ¢ches + Bulletins | JU-04 | Jules | â¬œ Ã€ faire | â€” | â€” |

### FRONTEND WEB â€” Vue.js / Inertia

| # | TÃ¢che | Prompt | Agent | Statut | Date dÃ©but | Date fin |
|---|-------|--------|-------|--------|-----------|---------|
| F1 | Init Vue.js + layouts + RTL arabe + dashboard | CU-01 | Cursor | â¬œ Ã€ faire | â€” | â€” |

### INFRASTRUCTURE (toi â€” avant toute session IA)

| TÃ¢che | Statut | Notes |
|-------|--------|-------|
| Acheter domaine leopardo-rh.com | â¬œ | Namecheap / OVH |
| DNS api. / app. / admin. â†’ o2switch | â¬œ | AprÃ¨s achat domaine |
| SSL Let's Encrypt (cPanel) | â¬œ | AprÃ¨s DNS |
| PostgreSQL o2switch (leopardo_db + user) | â¬œ | Voir SPRINT_0_CHECKLIST.md |
| Redis activÃ© | â¬œ | |
| Firebase projet crÃ©Ã© | â¬œ | console.firebase.google.com |
| google-services.json rÃ©cupÃ©rÃ© | â¬œ | Pour Flutter Android |
| GoogleService-Info.plist rÃ©cupÃ©rÃ© | â¬œ | Pour Flutter iOS |
| Repo GitHub crÃ©Ã© (monorepo) | â¬œ | |
| Secrets GitHub Actions configurÃ©s | â¬œ | Voir SPRINT_0_CHECKLIST.md |

---

## ðŸ“ LOG DES SESSIONS IA

> Copier-coller ce template aprÃ¨s chaque session et le remplir.

```
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
SESSION #[N] â€” [NOM DE LA TÃ‚CHE]
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
Date         : JJ/MM/AAAA HH:MM
Agent        : Claude Code / Jules / Cursor / Claude Chat
Prompt utilisÃ© : CC-XX / JU-XX / CU-XX
DurÃ©e        : ~X heures
Statut final : âœ… TerminÃ©e | ðŸ”„ IncomplÃ¨te | âŒ BloquÃ©e

CE QUI A Ã‰TÃ‰ ACCOMPLI :
- [x] ...
- [x] ...

PROBLÃˆMES RENCONTRÃ‰S :
- ProblÃ¨me : ...
  Solution appliquÃ©e : ...

DÃ‰CISIONS PRISES EN COURS DE SESSION :
- ...

FICHIERS MODIFIÃ‰S / CRÃ‰Ã‰S :
- api/app/...
- api/database/...

TESTS QUI PASSENT :
- ...

PROCHAINE SESSION :
- TÃ¢che : B[N+1] â€” [Nom]
- Prompt : CC-XX
- PrÃ©requis vÃ©rifiÃ©s : âœ… / âŒ
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
```

### Historique des sessions

*(Aucune session de code â€” Conception terminÃ©e le 31/03/2026 â€” PrÃªt Ã  coder)*

---

## ðŸ”´ BLOCAGES EN COURS

*(Aucun â€” en attente de l'infrastructure pour dÃ©marrer CC-01)*

---

## âœ… DÃ‰CISIONS ARCHITECTURALES FIGÃ‰ES (NE PAS REMETTRE EN QUESTION)

| # | Sujet | DÃ©cision | Raison | Date |
|---|-------|----------|--------|------|
| D1 | FK hiÃ©rarchie employees | `manager_id` (auto-ref) | UnifiÃ© SQL/ERD/Dart | 31/03 |
| D2 | Statut employees | `status VARCHAR` : active/suspended/archived | Plus riche que is_active | 31/03 |
| D3 | FCM tokens | Table `employee_devices` (pas JSONB dans employees) | Scalable multi-device | 31/03 |
| D4 | Notifs temps rÃ©el web | SSE (Server-Sent Events) | Compatible o2switch, pas de Reverb | 31/03 |
| D5 | Stockage fichiers MVP | Local Laravel â†’ Cloudflare R2 Phase 2 | ZÃ©ro coÃ»t MVP | 31/03 |
| D6 | salary_base vs gross_salary | `salary_base` = employees (fixe) / `gross_salary` = payrolls (calculÃ©) | Deux choses diffÃ©rentes | 31/03 |
| D7 | Langues | fr/ar/en/tr (JAMAIS es) | MarchÃ©s cibles | 31/03 |
| D8 | GrÃ¢ce abonnement | 3 jours lecture seule aprÃ¨s expiration | Voir CheckSubscription spec | 31/03 |
| D9 | Audit logs | Observer Eloquent (pas manuel) | CohÃ©rence, zÃ©ro oubli | 31/03 |
| D10 | Timezone pointage | Calcul en timezone entreprise (pas UTC) | Voir REGLES_METIER Â§4 | 31/03 |
| D11 | Multi-tenant | JAMAIS `WHERE company_id` dans les controllers tenant | TenantMiddleware gÃ¨re tout | 31/03 |
| D12 | Horodatage | `now()` serveur â€” JAMAIS timestamp du client | SÃ©curitÃ© / fraude | 31/03 |

---

## ðŸ“ INDEX RAPIDE â€” FICHIERS PAR USAGE

### DÃ©marrer une session Backend (Claude Code)
```
docs/GESTION_PROJET/CONTEXTE_SESSION_IA.md          â† LIRE EN PREMIER
docs/PROMPTS_EXECUTION/v2/backend/CC-01_INIT_LARAVEL.md
docs/PROMPTS_EXECUTION/v2/backend/CC-02_MODULE_AUTH.md
docs/PROMPTS_EXECUTION/v2/backend/CC-03_A_CC-06_MODULES.md
docs/PROMPTS_EXECUTION/99_prompts_execution/01_PROMPT_MASTER_CLAUDE_CODE.md
```

### DÃ©marrer une session Mobile (Jules)
```
docs/GESTION_PROJET/CONTEXTE_SESSION_IA.md          â† LIRE EN PREMIER
docs/PROMPTS_EXECUTION/v2/mobile/JU-01_A_JU-04_FLUTTER.md
docs/PROMPTS_EXECUTION/06_PROMPT_MASTER_JULES_FLUTTER.md
```

### DÃ©marrer une session Frontend (Cursor)
```
docs/GESTION_PROJET/CONTEXTE_SESSION_IA.md          â† LIRE EN PREMIER
docs/PROMPTS_EXECUTION/v2/frontend/CU-01_ET_AGENTS.md
```

### RÃ©fÃ©rence technique
```
docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md  â† 82+ endpoints
docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md                â† BDD
docs/dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql             â† SQL
docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md                 â† MÃ©tier
docs/dossierdeConception/07_securite_rbac/10_RBAC_COMPLET.md                  â† RBAC
api/openapi.yaml                                                                â† OpenAPI 3.0.3
```

