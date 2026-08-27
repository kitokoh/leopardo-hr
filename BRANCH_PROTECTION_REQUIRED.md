# Branch Protection — main

> **Mise à jour : 2026-08-26** — enforce_admins activé (CI verte depuis #5691).  
> Référence : audit ratio fix/feat (5.24 → cible ≤ 2.5), post-audit 2026-08-26.

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

| Check | Workflow | Depuis |
|---|---|---|
| `Backend Coverage (PHP 8.4 + PostgreSQL 16)` | `coverage-gate.yml` | Phase 2 |
| `PHPStan — Strict (Core/Modules/Shared, level 8)` | `phpstan-baseline.yml` | Phase 3 |
| `Module Structure Validator` | `architecture-check.yml` | #5584 |
| `Frontend — ESLint + TypeScript` | `web-ci.yml` | Phase 1 |
| `actionlint (+ shellcheck)` | `actionlint.yml` | #2131 |
| `Ratio fix/feat (cible ≤ 2.5)` *(signal fort, non requis)* | `fix-feat-ratio-guard.yml` | 2026-08-26 |

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
| 2026-08-26 | `enforce_admins=true` activé + `Ratio fix/feat` ajouté aux required checks | Audit post-sprint |
| 2026-08-26 | `merge-quota-guard.yml` créé (#5634) — quota journalier signal fort | #5634 |
| Phase 2 | 5 checks initiaux (Coverage, PHPStan, Modules, Frontend, Actionlint) | — |

---

## Roadmap protection (décisions PM requises)

- [ ] `required_approving_review_count: 1` — à activer quand l'équipe dépasse 2 développeurs actifs
- [ ] `required_linear_history: true` — à valider (rebases obligatoires = historique propre)
- [ ] Merge Queue GitHub — à activer quand >15 PRs/jour régulièrement
