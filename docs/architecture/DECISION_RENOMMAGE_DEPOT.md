# Décision propriétaire — Renommage du dépôt `leopardo-hr` (#7848)

> **Statut : EN ATTENTE d'arbitrage propriétaire.** Ce document prépare la décision demandée
> par l'issue #7848 (audit externe 2026-09-20). Il n'acte rien : il inventorie l'impact et
> propose des options. Trancher, puis cocher l'option retenue et dater.

## Contexte

- Le dépôt s'appelle **`leopardo-hr`**, mais la charte de positionnement (décision #7428,
  `docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md`) impose la catégorie
  « **suite métier** » et interdit « logiciel RH » / « HR SaaS » comme catégorie.
- Le nom du repo est le premier élément de présentation publique : il contredit la charte.
- Le titre de `.specify/constitution.md` (« Leopardo HR Constitution ») a été corrigé en
  « Leopardo Constitution » le 2026-09-23 (tranche code de #7848 — à faire « dans tous les cas »).

## Inventaire d'impact (mesuré le 2026-09-23, `rg -l "kitokoh/leopardo-hr"`)

| Zone | Fichiers avec référence en dur |
|---|---|
| `docs/` | 112 |
| `dev-hub/` | 8 |
| `api/` | 7 |
| `front/` | 5 |
| `.github/workflows/` | 5 |
| racine (`README`, `SECURITY`, `SUPPORT`, `DEVELOPMENT`, `CONVENTIONS`, `melos.yaml`) + `site/` + `scripts/` | 8 |
| **Total** | **142 fichiers** |

GitHub **redirige automatiquement** les anciennes URLs (clones, remotes, API, badges) après un
renommage — la migration peut donc se faire **par vagues** sans rupture : d'abord workflows +
racine + code (`api/`, `front/`, `dev-hub/`, `scripts/`), puis `docs/` en masse.

⚠️ Points de vigilance connus : webhooks/intégrations externes configurés sur l'ancien nom
(Vercel, Render, Dependabot — les redirections couvrent l'API mais vérifier chaque intégration),
badges README, URL canonique de la vitrine, et `git remote set-url` sur les postes/agents.

## Options

- [ ] **Option A — Renommer en `leopardo`** (aligne le nom sur la marque ; le plus court).
- [ ] **Option B — Renommer en `leopardo-suite`** (explicite la catégorie « suite » ; évite
      une collision éventuelle avec d'autres projets « leopardo »).
- [ ] **Option C — Conserver `leopardo-hr`** et documenter ici pourquoi le nom historique est
      conservé (ex. : SEO/backlinks existants, coût de migration des intégrations) — l'issue
      #7848 exige alors ce paragraphe de justification, puis peut être fermée.

## Plan d'exécution si renommage (A ou B)

1. Propriétaire : renommer sur GitHub (Settings → General → Rename).
2. Vague 1 (même jour) : `.github/workflows/` (5 fichiers), racine, `api/`, `front/`,
   `dev-hub/`, `scripts/`, `site/`, `melos.yaml` — PR unique.
3. Vague 2 : `docs/` (112 fichiers, sed mécanique) — PR dédiée.
4. Vérifier intégrations : Vercel, Render, Dependabot, badges, protection de branche.

---
*Préparé le 2026-09-23 (session agent, lot docs/gouvernance #7982 #7844 #7848 #7871). Refs #7848, #7428.*
