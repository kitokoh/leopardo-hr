# REGISTRE_PROTOCOLES — Leopardo RH

> **Rôle :** vue unique et opposable du cadre `docs/PROTOCOLES/` — qui possède quel protocole,
> à quelle cadence il est vérifié, ce qui est réellement implémenté, et ce qui reste à faire.
> Toute modification d'un protocole **doit** mettre cette table à jour dans la même PR (miroir,
> cf. `README.md` §6). Ce fichier est la source de vérité de l'**état** du cadre ; les fichiers
> P0x restent la source de vérité du **contenu** des règles.

**Dernière revue :** 2026-09-11 · **Prochaine revue :** dernier jour ouvré de 2026-09

---

## 1. Tableau de bord

| ID | Protocole | Propriétaire | Cadence | Statut | Implémentation | Preuves principales |
|---|---|---|---|---|---|---|
| **P01** | Validation & mise sur le marché | PM (décision) + gardien technique (preuves) | À chaque palier externe | ratifié v1.0 | ~95 % | `recette_version.yml`, `release-readiness.ps1`, `RELEASE_READINESS_GATE.md`, `release.yml`/`deploy-prod.yml` |
| **P02** | Onboarding agent | gardien technique + PM (affectation) | À chaque arrivée + reprise > 7 j | ratifié v1.0 | ~85 % | `00_AGENT_QUICK_CARD.md`, `14_ONBOARDING_AGENT.md`, `16_FIN_DE_SESSION.md`, `AGENT-START-HERE.md` |
| **P03** | Vitrine & présentation | PM (message) + gardiens de surface | Mensuelle (dernier jour ouvré) | ratifié v1.0 | 100 % | `MESSAGE.md`, `TERMES.md`, `METRIQUES_VITRINE.md`, `check-public-promises.sh` + `public-promises-guard.yml` |
| **P04** | Issues & tâches par expérience | PM (affectation) + gardien technique | Continue + moisson 1er du mois | ratifié v1.0 | ~90 % | `constat_lecon.yml`, `retex.yml`, `RETEX_FLUX.md`, `MOISSON_LECONS.md`, `issue-governance-guard.yml` |
| **P05** | Harmonisation design | gardien design (PM valide) | Mensuelle + à chaque PR UI | ratifié v1.0 | ~80 % | `check-design-token-sync.sh` + `design-token-sync.yml`, `web-design-tokens.yml`, goldens Flutter, APV L.05/L.07 |
| **P06** | Desktop Windows/macOS par BC | gardien mobile/desktop + PM | À chaque tranche verticale | ratifié v1.0 | 100 % | `melos.yaml` (`build:windows/macos`), `desktop-ci.yml`, `desktop-distribute.yml`, `DESKTOP_BC_ACCOUNTING_DECISION.md`, `RUNBOOK_DESKTOP_ACCOUNTING.md` |
| **P07** | Architecture dev/prod | gardien infra + PM | Hebdo (parité) + mensuelle (audit) | ratifié v1.0 | ~80 % | `DOMAINS.md`, `RENDER_DEV_PROD_TOPOLOGY.md`, `AUDIT_PARITE_HEBDO.md`, `check-render-env-parity.sh`, `deploy-main.yml`/`deploy-prod.yml` |

**Statuts de référence (définitions de fait du README §3) :**
`proposé` → `ratifié` → `en révision` → `abrogé`.

---

## 2. Travaux ouverts (delta du cadre)

| Réf. | Objet | Protocole | Priorité | État |
|---|---|---|---|---|
| O-01 | Miroir : référencer le corpus dans `AGENTS.md` + carte rapide + `README.md` | P02/README §6 | **P1** | à faire (patch fourni) |
| O-02 | Ratification v1.0 des 7 fichiers + ligne `CHANGELOG` | tous | **P1** | à faire |
| O-03 | Gabarit d'issue « relais » (délégation) | P04 §7 | P2 | fichier fourni |
| O-04 | `check-render-live-vs-yaml.sh` (parité live/yaml) | P07 §6 | P2 | fichier fourni |
| O-05 | Workflow `infra-audit.yml` (hebdo) | P07 §6 | P2 | esquisse fournie ; dépend du secret `RENDER_API_KEY` |
| O-06 | Workflow `release-report.yml` (preuves auto) | P01 §5 | P3 | esquisse fournie |
| O-07 | T1 — diff visuel Playwright (`toHaveScreenshot`) | P05 §5 | P2 | à ouvrir (issue fille #7105) |
| O-08 | Aligner `recette_version.yml` ↔ nom `release-gate` du protocole | P01 §5 | P3 | à trancher |

---

## 3. Rituel mensuel (rappel opposable)

- **Dernier jour ouvré** — *Revue des protocoles* : parcourir P01→P07, décider
  conserver / modifier / abroger / ajouter ; livrables : entrées `CHANGELOG`, issues créées,
  ce registre mis à jour, compte-rendu `docs/GESTION_PROJET/REVUE_PROTOCOLES_YYYY-MM.md`.
  Gabarit : `.github/ISSUE_TEMPLATE/revue_mensuelle.md`.
- **1er du mois** — *Moisson des leçons* (`docs/GESTION_PROJET/MOISSON_LECONS.md`) : trier les
  issues `lecon`/`retex`, promouvoir en garde ce qui a coûté 2 fois, en note ce qui a coûté 1 fois.

---

## 4. Historique

| Version | Date | Changement |
|---|---|---|
| v1.0 | 2026-09-11 | Création du registre + ratification v1.0 du corpus (audit de l'état réel du dépôt) |
