# ⚡ CONTEXTE SESSION IA — LEOPARDO RH
# Copier-coller ce fichier EN DÉBUT de chaque nouvelle session IA
# Il contient tout ce qu'un agent doit savoir pour travailler sans ambiguïté
# Version 4.0 | 31 Mars 2026

---

## CE QU'EST CE PROJET

**Leopardo RH** — SaaS RH multi-entreprises pour PME Afrique du Nord, Turquie, France.

| Couche | Techno | Agent |
|--------|--------|-------|
| Backend API | Laravel 11 + PostgreSQL 16 (multi-schéma) | Claude Code |
| Mobile | Flutter 3.x — app employés (iOS + Android) | Jules |
| Web | Vue.js 3 + Inertia.js — dashboard managers | Cursor |
| Infra | o2switch VPS — Nginx + PHP-FPM + Supervisor | Chef de projet |

---

## ÉTAT ACTUEL (lire JOURNAL_DE_BORD.md pour détail)

```
Conception : ✅ TERMINÉE v3.3.3
Code       : ⬜ PAS DÉMARRÉ — prochaine tâche = CC-01
```

---

## 12 RÈGLES ABSOLUES (violer = bug en production)

```
1.  HORODATAGE        → now() Laravel côté serveur. JAMAIS le timestamp du client.
2.  MULTI-TENANT      → JAMAIS WHERE company_id dans les controllers tenant.
                        TenantMiddleware + Global Scope gèrent tout.
3.  manager_id        → Nom du champ FK dans employees (PAS supervisor_id).
4.  status VARCHAR    → Champ statut employees : 'active'|'suspended'|'archived'.
                        PAS is_active BOOL.
5.  salary_base       → Champ dans employees (salaire de base fixe mensuel).
    gross_salary      → Champ dans payrolls (brut calculé = salary_base + HS + primes).
6.  LANGUES           → fr / ar / en / tr. JAMAIS es (espagnol).
7.  employee_devices  → Table FCM tokens. PAS fcm_tokens JSONB dans employees.
8.  SSE               → Stratégie notifications web temps réel. PAS WebSocket/Reverb.
9.  TESTS FIRST       → Écrire les tests Pest AVANT d'écrire les controllers.
10. TIMEZONE          → Calculer pointage en timezone entreprise. PAS UTC brut.
11. GRÂCE ABO         → 3 jours lecture seule après expiration. Puis 403 SUBSCRIPTION_EXPIRED.
12. AUDIT LOGS        → Via Observer Eloquent sur les modèles sensibles. PAS manuel.
```

---

## STRUCTURE DU REPO

```
gestionemployerBackend-main/
├── api/                         ← Laravel 11 (à créer lors de CC-01)
│   ├── app/
│   ├── database/
│   │   ├── migrations/
│   │   ├── seeders/
│   │   └── factories/
│   └── .env.example
├── mobile/                      ← Flutter (à créer lors de JU-01)
│   └── assets/mock/             ← Données de test locales (déjà présentes)
├── docs/
│   ├── GESTION_PROJET/          ← Ce dossier (journal, suivi, contexte)
│   │   ├── JOURNAL_DE_BORD.md   ← État du projet — lire EN PREMIER
│   │   ├── SUIVI_PROMPTS.md     ← Checklist détaillée par session
│   │   └── CONTEXTE_SESSION_IA.md  ← Ce fichier
│   ├── PROMPTS_EXECUTION/       ← Prompts par agent
│   └── dossierdeConception/     ← Specs techniques complètes
├── CHANGELOG.md
└── README.md
```

---

## FICHIERS DE RÉFÉRENCE — ordre de lecture recommandé

```
1. docs/GESTION_PROJET/JOURNAL_DE_BORD.md           ← État actuel + décisions
2. docs/PROMPTS_EXECUTION/v2/backend/CC-XX.md        ← Ta tâche du jour
3. docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md
4. docs/dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql
5. docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md
6. docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md
7. docs/dossierdeConception/07_securite_rbac/10_RBAC_COMPLET.md
8. docs/dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md
```

---

## DÉCISIONS CLÉS (résumé — voir journal pour détail complet)

| Sujet | Décision |
|-------|----------|
| FK hiérarchie | `manager_id` dans employees (auto-référentielle) |
| Statut employé | `status` VARCHAR : active/suspended/archived |
| FCM | Table `employee_devices` (pas JSONB) |
| Notifs web | SSE (Server-Sent Events) |
| Stockage MVP | Local Laravel (storage/app/) |
| Timezone | Calcul en tz entreprise via `Carbon::setTimezone()` |
| Audit | Observer Eloquent sur Employee, Payroll, Absence, SalaryAdvance |
| Tests | Pest PHP — écrire AVANT le code |

---

## COMMIT CONVENTION

```
feat(scope): description
fix(scope): description
test(scope): description
docs(scope): description

Scopes : auth | employees | attendance | payroll | absences | advances
         tasks | notifications | billing | tenant | admin | ci | docs

Exemples :
feat(auth): add multi-tenant login with user_lookups lookup
fix(payroll): use salary_base instead of non-existent gross_salary field
test(tenant): add isolation test company A cannot see company B data
```

---

## APRÈS TA SESSION — METTRE À JOUR

1. Cocher les items dans `docs/GESTION_PROJET/SUIVI_PROMPTS.md`
2. Remplir un bloc SESSION dans `docs/GESTION_PROJET/JOURNAL_DE_BORD.md`
3. Committer : `git commit -m "docs: update journal session CC-XX"`

---

## OVERRIDE CANONIQUE (04 Avril 2026)

Ce bloc prevaut sur toute section plus ancienne de ce fichier.

Etat projet :
- Conception : terminee et harmonisee jusqu'a `4.1.1`
- Phase active : Phase 1 (petites structures - persona Murat)
- Objectif execution : MVP operationnel en 20-24 semaines

Source de verite documentaire :
1. `ORCHESTRATION_MAITRE.md`
2. `docs/dossierdeConception/*`
3. `docs/PROMPTS_EXECUTION/v2/*`
4. `docs/GESTION_PROJET/*`
5. Archives historiques

Ordre de lecture minimal par session d'implementation :
1. `ORCHESTRATION_MAITRE.md`
2. prompt d'execution de la tache (`CC-*`, `JU-*`, `CU-*`)
3. `02_API_CONTRATS_COMPLET.md`
4. `03_ERD_COMPLET.md` et `07_SCHEMA_SQL_COMPLET.sql`
5. `05_REGLES_METIER.md`

Gate avant merge (obligatoire) :
- tests module OK
- tenant isolation verifiee
- RBAC verifie
- changelog + journal racine mis a jour
