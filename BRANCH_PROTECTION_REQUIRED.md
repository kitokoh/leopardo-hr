# Branch Protection — main

> **Mise à jour : 2026-09-09** — état vérifié via l'API GitHub (audit PM + saturation CI #6928) ;
> le référentiel machine de la garde #2011 (`dev-hub/tools/branch-protection-canonical.json`) est
> synchronisé sur cet état. Référence : audit ratio fix/feat (5.24 → cible ≤ 2.5), post-audit 2026-08-26.
> Correction 2026-09-09 (issue #7096) : la liste des checks requis passe de 5 à **4** — le check
> `Backend Coverage` n'est **pas** requis au merge (fast-path #6928) mais reste exigé à la release.

---

## État actuel de la protection

| Paramètre | Valeur |
|---|---|
| `enforce_admins` | **✅ true** (activé le 2026-08-26) |
| `strict` (branche à jour avant merge) | ✅ true |
| `required_approving_review_count` | 0 (pas encore activé — voir §Roadmap) |
| `dismiss_stale_reviews` | true |
| `allow_force_pushes` | false |
| `allow_deletions` | false |

## Checks requis (bloquants sur toute PR → main)

Vérifié via l'API branche protection le **2026-09-09** : 4 contexts requis seulement.

| Check | Workflow émetteur (PR) | Depuis |
|---|---|---|
| `PHPStan — Strict (Core/Modules/Shared, level 8)` | `architecture-check.yml` | Phase 3 |
| `Module Structure Validator` | `architecture-check.yml` | #5584 |
| `Frontend — ESLint + TypeScript` | `architecture-check.yml` | Phase 1 |
| `actionlint (+ shellcheck)` | `actionlint.yml` | #2131 |
| `Ratio fix/feat (cible ≤ 2.5)` *(signal fort, **non requis**)* | `fix-feat-ratio-guard.yml` | 2026-08-26 |

> **Backend Coverage — PAS requis au merge, exigé à la release.** `Backend Coverage
> (PHP 8.4 + PostgreSQL 16)` (`coverage-gate.yml`) a été retiré des checks requis au merge
> lors du fast-path anti-saturation (#6928, correctifs 2026-09-08/09). Il reste **exigé par
> `release.yml`** sur le commit taggé (liste des checks requis à la release : Backend Coverage
> ≥ 65 % backend, ≥ 80 % Payroll via `payroll-ci.yml`). Un agent/PM ne doit donc pas croire que
> le coverage bloque le merge (il est informatif) ni ignorer qu'il bloque la release (vrai).
> Le job `flutter-analyze` suit le même régime (fast-path #6928, non requis au merge).

### Sémantique exacte d'un check requis vert (#7269)

Les 3 checks portés par `architecture-check.yml` sont **pilotés par les chemins modifiés**
(`detect-changes` : `^api/` → `api_changed`, `^front/web/` → `web_changed`). Un vert ne signifie
donc pas toujours « l'analyse a tourné » :

| Situation | Analyse exécutée | Ce que garantit le vert |
|---|---|---|
| Des fichiers `api/` sont modifiés | oui (PHPStan strict, Module Structure) | le code **a été analysé** |
| Aucun fichier `api/` modifié | non (non applicable) | **le gate a rendu un verdict explicite** (`api_changed=false`) — pas « l'analyse a réussi » |
| Le gate ne rend aucun verdict | non | **impossible** : une assertion placée en tête de chaque job requis échoue si `detect-changes` ne produit pas exactement `true`/`false` (#7269) |

Conséquence pratique : une PR front-only ou docs-only peut afficher ces checks **verts sans qu'aucune
analyse PHP n'ait tourné**. C'est volontaire (économie de runners — saturation documentée dans
`docs/infra/02_alignement/CI_SATURATION.md`) et c'est désormais **explicite** (log `::notice::` dans
le job) et **non contournable par accident** (verdict obligatoire). L'alternative — faire tourner
PHPStan sur tous les commits — a été écartée le 2026-09-13 : elle aggrave la famine de runners,
dont le coût dépasse le bénéfice d'un contrôle non applicable.

Ne pas confondre avec un feu vert sur le code : le seuil réel de validation PHP reste
`Tests - Leopardo RH` (sans gate de chemins) et, à la release, `Backend Coverage`.

## Règles du garde ratio fix/feat

Le workflow `fix-feat-ratio-guard.yml` calcule le ratio `nb_commits_fix / nb_commits_feat`
sur une fenêtre glissante (défaut 30 jours). Variables de pilotage :

| Variable repo (`vars.*`) | Défaut | Rôle |
|---|---|---|
| `FIX_FEAT_RATIO_DAYS` | `30` | Fenêtre d'analyse (jours) |
| `FIX_FEAT_RATIO_WARN` | `2.5` | Seuil d'avertissement visible (non bloquant) |
| `FIX_FEAT_RATIO_MAX` | `3.5` | Seuil de blocage (PR ne peut pas merger) |

### Phase de calibration (2026-08-26)

Le garde tourne en mode **warning uniquement** (`FIX_FEAT_RATIO_ENFORCE=false`).
Il mesure et affiche le ratio sans bloquer les PRs.

**Activation du verrou** : quand `ratio(main, 30 jours) < 3.5` durablement :
1. _Settings → Variables_ → ajouter `FIX_FEAT_RATIO_ENFORCE=true`
2. Ajouter `Ratio fix/feat (cible ≤ 2.5)` dans les required status checks via l'API

### Protocole si le garde est bloquant

1. Vérifier le ratio : `git log --since="30 days ago" --pretty=%s | grep -c "^fix"` vs `grep -c "^feat"`
2. Implémenter des features du backlog pour rééquilibrer la fenêtre.
3. Si urgence sécurité : élever `FIX_FEAT_RATIO_MAX` via _Settings → Variables_ sans modifier de code.

---

## Historique des changements

| Date | Changement | PR/Issue |
|---|---|---|
| 2026-08-26 | `enforce_admins=true` activé ; garde `Ratio fix/feat` créée en **signal fort non requis** (jamais ajoutée aux required checks) | Audit post-sprint |
| 2026-08-26 | `merge-quota-guard.yml` créé (#5634) — quota journalier signal fort | #5634 |
| Phase 2 | 5 checks initiaux (Coverage, PHPStan, Modules, Frontend, Actionlint) | — |

---

## Roadmap protection (décisions PM requises)

- [ ] `required_approving_review_count: 1` — à activer quand l'équipe dépasse 2 développeurs actifs
- [ ] `required_linear_history: true` — à valider (rebases obligatoires = historique propre)
- [ ] Merge Queue GitHub — **non configurée** (vérifié 2026-09-05 : 0 ruleset) ; à activer quand >15 PRs/jour régulièrement
