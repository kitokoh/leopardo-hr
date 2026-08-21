# 📋 PLAN 60 JOURS — Leopardo RH

**Version** : 0.1 · **Date** : 2026-08-20 (J2) · **Validité** : J1-J60 (19/08 → 17/10/2026)
**Statut** : consolidé par l'agent PM depuis les issues #5144→#5160 et `docs/GOUVERNANCE/FREEZE_SCOPE_60J.md` — à valider par le fondateur (les écarts se signalent par une issue `[FREEZE-EXCEPTION]`, jamais par un agent seul).

> ⚠️ **Pourquoi ce fichier existe** : il est cité comme référence canonique par
> `docs/GOUVERNANCE/FREEZE_SCOPE_60J.md` (#5147) et `docs/OPS/BUDGET_AGENTS.md` (#5148),
> mais était absent du dépôt au J2. C'est la consolidation opérationnelle du plan :
> chronologie, gates, KPI, critères de sortie, issues, risques. Il prime sur toute
> planification antérieure (dossiers `docs/PLAN_ACTION*/` sont obsolètes — voir AGENTS.md).

---

## 1. Objectif

Le plan s'achève par une **décision A / B / C au gate J60**, prise sur des **mesures reproductibles** (9 KPI), pas sur des impressions :

- **A (accélérer)** — pack 12 mois + pays n°2 ;
- **B (corriger)** — hypothèses produit/prix/marché à tester ;
- **C (pause propre)** — mise en maintenance documentée.

Quatre piliers :

| Pilier | Cible | Issues portantes |
|---|---|---|
| **Funnel d'acquisition** qui délivre | signup → trial → dashboard **< 2 min** (cible < 30 s) | #5144, #5146, #5161/#5162, #5170-#5174 |
| **Wedge paie DZ** (conformité) | golden tests **≥ 40**, coverage Payroll **≥ 80 %**, clôture 2 étapes | #5149, #5150 |
| **Pilotes DZ** | **3 signés**, **2 actifs/semaine**, onboarding **< 30 min** | #5151, #5152, #5154, #5155, #5156 |
| **Traîne & gouvernance** | issues non-dependabot **≤ 10** à J32, ratio fix/feat **≤ 2,5**, CI verte, budget maîtrisé | #5145, #5153, #5158, #5159, #5160, #5148 |

## 2. Périmètre

Tout ce qui n'est pas listé dans `docs/GOUVERNANCE/FREEZE_SCOPE_60J.md` est **refusé en revue** (règle gravée dans AGENTS.md, issue #5147). Le freeze gèle notamment : nouvelles features des modules périphériques, nouveau pays de paie (hors DZ) avant J60, outils d'analytics externes, refactors d'architecture non liés à un bug.

## 3. Chronologie & gates

| Gate | Date | Condition de passage |
|---|---|---|
| **J16** | 2026-09-03 | **CI verte** (43 workflows, zéro rouge toléré — condition d'entrée Phase 2) ; kiosque punch-methods opérationnel (#5119) |
| **J32** | 2026-09-19 | Issues non-dependabot **≤ 10** ; ratio fix/feat **≤ 2,5** ; **2 pilotes signés** |
| **J46** | 2026-10-03 | **Rétro pilotes** publiée (#5157) + snapshot KPI daté (#5158) |
| **J60** | 2026-10-17 | **Bilan 60 j** (#5159) + handoff opérationnel (#5160) → **décision A/B/C** |

## 4. Batches & issues du plan

### Batch 1 — Funnel + CI + gouvernance (J1-J8)
| Issue | Sujet | Statut 2026-08-20 |
|---|---|---|
| #5144 | QA pass prod (fixes #4947→#4955 en live) | ✅ clos (rapport `docs/qa/QA_PROD_2026-08-19.md`) |
| #5145 | CI fail-closed « 5 jours verts » — inventaire 43 workflows | ✅ clos (inventaire `docs/qa/INVENTAIRE_CI_2026-08-19.md` ; **15 rouges restants à traiter**) |
| #5146 | E2E funnel prospect Playwright (spec livrée, `.specify/features/e2e-funnel-prospect/`) | 🟡 ouvert — implémentation en cours |
| #5147 | Freeze scope 60 jours | ✅ clos (`docs/GOUVERNANCE/FREEZE_SCOPE_60J.md`) |

### Batch 2 — Pilotes + paie DZ (J2-J30)
| Issue | Sujet | Statut 2026-08-20 |
|---|---|---|
| #5148 | Budget & cadence agents | ✅ clos (`docs/OPS/BUDGET_AGENTS.md` — **tableau à chiffrer par le fondateur**) |
| #5149 | Golden tests paie DZ ≥ 40 (inventaire des 38) | 🟡 ouvert |
| #5150 | Clôture DZ bout en bout + benchmark 10 000 employés | 🟡 ouvert |
| #5151 | Onboarding pilote < 30 min — checklist + instrumentation | 🟡 ouvert |
| #5152 | Carnets de feedback pilotes (template + 3 carnets) | 🟡 ouvert |
| #5153 | Traîne cleanup (branches mortes, doublons, orphelines) | ✅ clos (rapport `docs/qa/TRAINE_2026-08-19.md`) |
| #5154 | Kit de prospection pilotes DZ | 🟡 ouvert |
| #5155 | SLA bugs pilotes (canal, triage, hotfix < 24 h) | 🟡 ouvert |
| #5156 | Suivi d'usage pilotes (instrumentation + rapport hebdo) | 🟡 ouvert |

### Batch 3 — Gates & livrables finaux (J30-J60)
| Issue | Sujet | Statut 2026-08-20 |
|---|---|---|
| #5157 | Rétrospective pilotes J46 | 🟡 ouvert |
| #5158 | Extraction automatisée des 9 KPI + snapshot daté | 🟡 ouvert |
| #5159 | Rapport de bilan 60 jours (décision A/B/C) | 🟡 ouvert |
| #5160 | Handoff opérationnel R6 (bus factor 1) | 🟡 ouvert |

### Addendum — Blocage onboarding Google (QA 2026-08-20, hors batch mais dans le périmètre funnel)
| Issue | Sujet | Statut 2026-08-20 |
|---|---|---|
| #5170 | P0 — Google OAuth 500 en prod (env Render sans `GOOGLE_*`) | 🟡 ouvert (ops : clés Render + console Google) |
| #5171 | P0 — Création de compte via Google (UNKNOWN_ACCOUNT) — **décision produit requise** | 🟡 ouvert |
| #5172 | P1 — Aucun worker de queue sur Render (invitations/trials jamais traités) | 🟡 ouvert (ops : provisionner `leopardo-queue-worker` + scheduler) |
| #5162 | P1 — Trial OTP → 503 TRIAL_OTP_SEND_FAILED | 🟡 ouvert (fix en cours) |
| #5173/#5174 | P2 — Erreurs Google silencieuses sur login + zéro e2e OAuth | 🟡 ouverts |

## 5. Les 9 KPI du gate (source : #5158)

Chaque KPI doit être extractible par un moyen **reproductible** (commande/script `pilot:kpi-report` ou doc bash) ; le snapshot daté est publié dans `docs/pilotes/KPI_GATE_<date>.md` avec valeur vs cible, source et verdict ✅/⚠️/❌.

| # | KPI | Cible | Source |
|---|---|---|---|
| 1 | Conversion signup → dashboard | ≥ 30 % | Analytics vitrine + logs trial |
| 2 | Trial provisioning | < 2 min (cible < 30 s) | Télémétrie jobs |
| 3 | CI verte (10 derniers jours ouvrés) | 100 % | GitHub Actions API |
| 4 | Coverage Payroll | ≥ 80 % | `payroll-ci.yml` |
| 5 | Pilotes actifs | ≥ 2 / semaine | `pilot:report` |
| 6 | MRR | > 0 | Stripe API |
| 7 | Issues non-dependabot | ≤ 10 | GitHub API |
| 8 | Ratio fix/feat (60 j) | ≤ 2,5 | `git log` |
| 9 | Coût agents cumulé | ≤ budget | `docs/OPS/BUDGET_AGENTS.md` |

## 6. Les 6 critères de sortie (source : #5159)

1. **Funnel < 2 min** — signup → dashboard mesuré et prouvé ;
2. **CI verte** — zéro run rouge toléré ;
3. **Golden tests ≥ 40** — comptage exact dans la PR #5149 ;
4. **Clôture démontrée** — clôture DZ de bout en bout + benchmark 10 000 employés (#5150) ;
5. **2 pilotes actifs** — usage hebdo vérifié (#5156) ;
6. **MRR > 0** — première ligne de revenu.

## 7. Livrables docs canoniques (dossier `docs/pilotes/`)

| Livrable | Issue | Fichier cible |
|---|---|---|
| Carnet de feedback pilotes (template + 3 carnets) | #5152 | `docs/pilotes/CARNET_TEMPLATE.md` |
| Checklist onboarding pilote | #5151 | `docs/pilotes/ONBOARDING_PILOTE.md` |
| Pitch + fiche de qualification pilote | #5154 | `docs/pilotes/PITCH_PILOTE.md` + fiches |
| Rétrospective J46 | #5157 | `docs/pilotes/RETRO_<date>.md` |
| Snapshot KPI daté | #5158 | `docs/pilotes/KPI_GATE_<date>.md` |
| Bilan 60 jours (décision A/B/C) | #5159 | `docs/pilotes/BILAN_60J_<date>.md` |
| Handoff opérationnel | #5160 | `docs/OPS/HANDOFF_<date>.md` |

## 8. Dépendances & risques

| Risque | Impact | Mitigation |
|---|---|---|
| **Accès Render/Vercel/Stripe requis** (#5170, #5172, #3452, #4952) | Blocage des P0/P1 tant qu'un humain n'agit pas | Tickets ops avec instructions exactes ; escalade dès J2 (le fondateur détient les accès) |
| **CI rouge** (15 workflows au 19/08, #5145) | Gate J16 non franchi → Phase 2 bloquée | Traiter les causes racines par issue fille P1 ; warm-up cold start ; Vercel quota = non-bloquant |
| **Décisions produit** (#5171 création compte Google, #4952 paiement, #5159 A/B/C) | Parcours onboarding Google incomplet | Arbitrages à J2-J3 (le fondateur) ; modèle invitation-first documenté en attendant |
| **Budget agents non chiffré** (#5148) | Plafond inapplicable | Fondateur : renseigner les coûts réels avant le vendredi (rituel bilan) |
| **Concurrence multi-agents** (protocole #2400) | Doublons de PR/branches | Branche = lock (`<type>/<issue>-<slug>`), auto-assignation, 1 PR = 1 issue, garde `plan-action2-claim-guard` |

## 9. Suivi

Ce tableau est mis à jour au fil de l'eau (rituel hebdo) ; le bilan final (#5159) s'appuie dessus.

- **Ouvertes plan** : 4/9 (Batch 2) + 4/4 (Batch 3) + 4 (addendum onboarding) — voir GitHub Issues pour l'état exact (assignations, branches, PRs).
- **Prochain rituel** : vendredi 2026-08-22 — bilan hebdo + renseignement budget #5148.

---

*Document consolidé le 2026-08-20 (J2) — sources : issues #5144→#5160, #5170→#5174, FREEZE_SCOPE_60J.md, BUDGET_AGENTS.md, rapports docs/qa/* du 2026-08-19. Toute correction : PR docs + CHANGELOG.*
