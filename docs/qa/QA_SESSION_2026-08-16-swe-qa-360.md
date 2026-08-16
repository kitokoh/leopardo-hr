# QA Leopardo RH — Session swe-qa-360 du 2026-08-16

Agent : **swe-qa-360** (expert SWE + QA senior) — mission 3 phases : implémentation
des issues ouvertes + merge des branches (pré-requis), audit 360° avec issues
spec-kit, puis implémentation des constats. Protocole anti-doublon #2400,
constitution `.specify/constitution.md` et spec-kit appliqués.

---

## Phase préalable — Drain du backlog & merge des branches

### PRs traitées (bilan de session, en coordination avec les agents concurrents)

La file est passée de **68 PRs ouvertes → 7** pendant la session (effort combiné
avec les agents concurrents « swe-qa », « expert21 », bots). Contribution directe
de cette session :

| Action | Détail |
|---|---|
| Rebase/merge de `main` dans ~15 branches de PR | `fix/4401`, `fix/4400`, `fix/4396`, `fix/4318`, `fix/4406`, `fix/4407`, `fix/4414`, `fix/4433`, `fix/4315-4316`, `fix/4334`, `fix/4383`, `fix/4397`, `fix/4447`, `fix/4446`, `fix/4408`, `fix/4476`, `fix/4307` — conflits CHANGELOG résolus |
| Doublons fermés (protocole anti-doublon #2400) | **#4550** (doublon #4307 — oubliait `salary_base` + 0 test, renvoi vers #4308/#4552), **#4385** (bundle P0+P1 dont les 3 composants sont déjà sur main ou dans #4308), **#4560** (doublon strict #4552 — mêmes SHAs de fichiers) |
| Déblocage file GitHub Actions | ~50 runs `queued`/`pending` orphelins ou supersédés annulés (miroir de `cancel-orphan-runs.sh --superseded`, script python/API — la file était à 377 runs queued, aucun check requis ne démarrait) |
| Nudges CI | Pushes vides sur 5 branches dont l'événement `synchronize` n'avait pas déclenché de runs (voir Leçons) |

### Issue P0 #4307 (EmployeeService — POST /api/v1/employees 500 en prod)

Verdict après analyse des 3 candidats (commit reverté `4077b267`, PR #4550, PR
#4308/#4552) : la PR canonique est **#4552** (`fix/4307-employee-role-nonfillable`,
ex-#4308) — elle couvre `salary_base` (absent du commit reverté → données paie
corrompues), pose les 5 clés sensibles explicitement (pattern #3677/#4151) et
ajoute le test `EmployeeServiceCreateFillableTest` (205 lignes). Le commit
reverté sur main oubliait `salary_base` et échouait « Workers Builds » → le
revert était justifié ; #4552 est la bonne version. (Fermeture de #4550/#4560
avec commentaire de renvoi, #4552 laissée à son auteur actif.)

---

## Phase 1 — Audit 360°

Méthode : scanners officiels du repo (`dev-hub/tools/*`) + greps ciblés par
surface + sondes live. Résultats :

- ✅ **API** : 0 `dd()/dump()/var_dump`, 0 `env()` hors config, 0 TODO/FIXME,
  0 secret en clair, 144 contrôleurs routés, OpenAPI 634/723 (allowlist OK),
  parité `.env.example` OK, migrations sans collision, catalogue pays OK.
- ✅ **Mobile** : 0 `.withOpacity(`, 0 `apiClient.dio.*` direct, 0 cast
  `as List`, 0 `await runApp`.
- ✅ **Admin** : gouvernance i18n des lots #4206 en cours — **1 seul** pattern
  de carte legacy restant (voir #4575).
- 🟡 **Dette i18n résiduelle** : littéraux FR dans `SelfServiceTrialController`
  (`next_steps`), `AuthController` (Google SSO), `PlatformUserController` —
  **déjà corrigés sur main** par le batch #4292 (issues fermées avec preuve).
- 🔴 **Portail client web** `front/web/src/app/(dashboard)/**` : **100 % FR**,
  aucune infra i18n, ~13 sections concernées — aucune doc ne spécifie FR-only →
  incohérence produit face à vitrine ×4, admin ×4 (en cours), mobile ×4.

### Issues créées (spec-kit, labels `QA` + `qa-audit-swe360-2026-08-16`)

| Issue | Priorité | Surface | Sujet |
|---|---|---|---|
| #4571 | P2 | api/i18n | Google SSO message FR (fermé : déjà corrigé par #4292, preuve code) |
| #4572 | P2 | api/i18n | `next_steps` signup trial FR (fermé : idem) |
| #4573 | P3 | api/i18n | « Impossible de désactiver votre propre compte » (fermé : idem) |
| #4574 | P2 | web/i18n | **Portail client localisé ×4 — tracking par lots** (ouvert) |
| #4575 | P3 | admin/ui | **UsersView dernier pattern carte legacy → glass-*** (ouvert, implémentée) |

---

## Phase 3 — Implémentations

| Issue | PR | Contenu | Validation |
|---|---|---|---|
| #4575 | **#4590** | UsersView.vue : `<code>` jeton impersonation `bg-white dark:bg-slate-900` → token `glass-bg` (design system v4.16.250+). Dernier fichier admin avec `rounded-lg bg-white` | ESLint vert, `npm run build` admin vert, `rg "rounded-lg bg-white"` → 0 hit |

---

## Leçons (pour les prochaines sessions)

1. **La file GitHub Actions sature vite sous pushes concurrents** (377 runs
   queued observés) : les événements `synchronize` de certaines branches ne
   créent AUCUN run (zéro check suite `github-actions`). Correctifs efficaces :
   (a) annuler les runs `queued` supersédés (même branche/workflow, head
   différent) via l'API — libère les groupes de concurrence ; (b) **nudge
   commit vide** (`git commit --allow-empty -m "ci: nudge"`) pour forcer un
   nouvel événement. Le check « Workers Builds: gestionemploye » échoue sur
   toutes les PR (déploiement Cloudflare hors PR) — ce n'est PAS un check
   requis, ne pas le traiter comme rouge bloquant.
2. **Main avance très vite** (~1 merge/min) : tout constat d'audit doit être
   re-vérifié contre `origin/main` au moment de créer l'issue — 3 constats sur
   5 étaient déjà corrigés au moment de la création (batch #4292). Vérifier
   aussi `git fetch` avant toute analyse de fichier local.
3. **Ne pas merger `main` dans une branche de PR sans vérifier que la PR est
   encore ouverte** (branche supprimée → re-création accidentelle de branche
   morte). Toujours re-lister l'état PR juste avant.
4. **Les branches de PR fermées ne sont pas toujours supprimées** → runs
   orphelins. Le cron `cleanup-orphan-runs.yml` (2 h) + `cancel-orphan-runs.sh
   --superseded` restent les outils canoniques.
5. **Vérifier les SHAs de fichiers entre PRs concurrentes** pour prouver un
   doublon (contents API `?ref=<sha>`) — plus fiable que la comparaison de
   diff textuel.
6. **`gh` CLI absent du sandbox** : utiliser l'API REST + `jq` (ou installer
   `gh` via apt quand sudo est disponible).

## Handoff

- PR #4590 (UsersView glass) en attente de CI/merge.
- #4574 (portail client ×4) : issue de tracking prête, lots découpés dans le
  body — premier lot = infra locale (réutiliser `useVitrineLocale`).
- #4552 (#4307) : PR canonique en attente de CI verte puis merge.
- Prod : les domaines `leopardo-rh.com` sont NXDOMAIN (#3452) — aucune sonde
  prod possible ; #2812/#2813/#3259/#2646 restent ouverts (dépendent du déploiement).
