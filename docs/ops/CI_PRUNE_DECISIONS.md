# Élagage CI — décisions et mesures (2026-10-30)

Contexte : la file GitHub Actions a saturé à plusieurs reprises (jusqu'à 102 runs
`queued`). Le diagnostic des sessions précédentes a traité la boucle
(`pr-external-checks-report`) et les deux plus gros jobs non filtrés
(`coverage-gate`, `mobile-smoke`). Ce document couvre l'analyse **complète** des
89 workflows restants et les décisions prises — y compris celles de ne rien faire.

## Mesure de départ

| Constat | Valeur |
|---|---|
| Fichiers workflow | 89 |
| Déclenchés sur `pull_request` | 51 |
| dont **sans filtre `paths`** | **11** |
| dont avec filtre `paths` | 40 |

Un seul non-filtré ne signifie pas un seul problème : la durée compte autant que
la fréquence. Mesure sur les 30 derniers runs de chaque workflow non filtré :

| Workflow | Runs | dont PR | moyenne | **médiane** |
|---|---|---|---|---|
| `openapi-ci` | 30 | 29 | 953 s | **396 s** |
| `fix-feat-ratio-guard` | 30 | 30 | 717 s | **400 s** |
| `pr-issue-guard` | 30 | 30 | 736 s | **378 s** |
| `merge-quota-guard` | 30 | 27 | 415 s | 47 s |
| `secret-scan` | 30 | 19 | 226 s | 54 s |
| `architecture-check` | 30 | 16 | 202 s | 200 s |
| `merge-health-ci` | 30 | 19 | 206 s | 40 s |
| `repo-hygiene-guard` | 30 | 19 | 184 s | 12 s |
| `cleanup-orphan-runs` | 30 | 25 | 171 s | 18 s |
| `actionlint` | 30 | 16 | 68 s | 68 s |
| `pr-external-checks-report` | 30 | 7 | 14 s | 13 s |

## Décisions

### Modifiés dans cette PR

**`fix-feat-ratio-guard`** — `synchronize` retiré (→ `edited`).
Le job checkout `github.event.pull_request.base.sha` et analyse `git log` sur
cette fenêtre : **le head de la PR n'est jamais lu**. Un push ne peut donc pas
changer le verdict — seuls les commits de `main` le peuvent. Rejouer à chaque
push coûtait ~400 s de médiane pour une réponse identique.
`edited` est conservé car une édition accompagne légitimement un changement de
cadence.

**`pr-issue-guard`** — `synchronize` retiré (→ `edited`).
Les deux gardes n'appellent que `gh pr list/view --json title,body`. Aucun accès
au diff, aucun fichier du dépôt lu. ~378 s de médiane par push pour un verdict
inchangé.

Gain : **~13 min de runner par push de PR**, sans retirer une seule vérification.

### Volontairement NON modifiés

**`openapi-ci`** (396 s de médiane, `pull_request:` nu). L'absence de filtre est
**délibérée et documentée** (`DoD #5280`) : une dérive peut venir des
`schemas/refs`, donc le contrat doit être vérifié sur chaque PR. Filtrer
retirerait une garantie écrite. Candidat à une décision de mainteneur, pas à un
changement d'agent.

**`architecture-check`** — porte 3 des 4 checks requis (PHPStan Strict, Module
Structure Validator, Frontend ESLint). Sans filtre, et **doit le rester** : un
check requis qui ne se déclenche pas ne rapporte aucun check-run, ce qui rend la
PR définitivement non-mergeable (c'est exactement la panne de #9035).

**`actionlint`** — porte le 4ᵉ check requis. Même raison. 68 s, négligeable.

**`merge-quota-guard`**, **`repo-hygiene-guard`**, **`merge-health-ci`**,
**`secret-scan`**, **`cleanup-orphan-runs`** — transverses par nature (quota de
merges, artefacts interdits, cohérence LFS, secrets, runs orphelins) et bon
marché en médiane (12–54 s). Aucun gain réel à les filtrer.

**`pr-external-checks-report`** — déjà corrigé par #9065, 13 s de médiane.

## Ce qui reste ouvert

Les 51 workflows `pull_request` restants sont déjà filtrés à 40/51. Un élagage
plus large (passage en `workflow_dispatch` ou `schedule`) relève d'une décision
de mainteneur : il faut arbitrer entre couverture et coût, ce qu'un agent ne
peut pas trancher à la place du propriétaire.

## Note sur les 7 runs `queued` persistants

Ils ne sont pas une saturation : ce sont des entrées fantômes de branches
supprimées en septembre (noms de workflow qui n'existent plus dans le dépôt).
Elles ne consomment aucun runner, `in_progress=0`, et l'API refuse leur
annulation (HTTP 409). Elles n'affectent aucune PR.
