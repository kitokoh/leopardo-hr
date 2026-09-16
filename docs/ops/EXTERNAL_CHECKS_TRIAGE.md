# Lire les checks d'une PR : requis, externes, à investiguer (issue #7480)

**Statut** : conduite opérationnelle active · **Issue** : #7480 · **Dernière mise à jour** : 2026-09-16.

---

## 1. Le problème

Trois familles de checks se ressemblent dans l'UI GitHub, alors qu'elles n'ont
**pas du tout** la même signification :

| Famille | Exemples | Signification | Action |
|---|---|---|---|
| **REQUIS** (4) | `PHPStan — Strict (Core/Modules/Shared, level 8)`, `Module Structure Validator`, `Frontend — ESLint + TypeScript`, `actionlint (+ shellcheck)` | le merge est **bloqué** tant qu'ils ne sont pas verts (`BRANCH_PROTECTION_REQUIRED.md`) | corriger le code |
| **EXTERNES** | `Vercel`, `Workers Builds: gestionemploye`, revue **Strix** | quota de déploiement (`api-deployments-free-per-day`), surface Cloudflare hors périmètre de la PR, essai Strix expiré | **ne pas** corriger le code : attendre le quota ou ignorer (documenté ici) |
| **REPO (autres)** | `Governance Gates (changelog + canonical files)`, `Web E2E Playwright`, `Tests module …` | un check du dépôt, non requis au merge | **lire le log** : il peut cacher une vraie régression, et son résumé peut être **vide** (#7466) |

Un rouge **permanent** détruit la valeur du signal : on apprend à l'ignorer. C'est
la même classe de défaut que la leçon #3545 (« un skip silencieux ressemble à un
succès »).

## 2. Le rapport automatique (ce que la CI publie)

Le workflow `PR External Checks Report` publie **un seul commentaire** par PR
(marqué `<!-- external-checks-report -->`, actualisé à chaque mise à jour de la
PR **et** à la fin des grands workflows) qui classe les échecs :

```
### État des checks — PR #7466
⚠️ Aucun check requis en échec (merge possible), mais 1 check(s) du dépôt sont rouges — à lire.
| Famille | Check | Motif / suite à donner |
| REPO  | Governance Gates (changelog + canonical files) (failure) | lire le log du job : le résumé du check peut être vide (#7480) |
| TIERS | Workers Builds: gestionemploye | déploiement Cloudflare Workers hors périmètre PR — #7480 |
```

En local, le même rapport s'obtient avec :

```bash
GH_TOKEN=<token> dev-hub/tools/report-external-checks.sh <pr-number|commit-sha>
# --comment pour publier/mettre à jour le commentaire de PR
```

## 3. Conduite à tenir

### Un check **requis** est rouge
Corriger le code. C'est la seule famille qui bloque le merge — inutile de
chercher ailleurs.

### Le check `Vercel` est rouge avec `api-deployments-free-per-day`
Quota de déploiements gratuits du jour atteint : **aucun rapport avec le code**
(lecheck n'est pas requis). Attendre le lendemain, ou passer au plan supérieur /
rendre les previews opt-in par label (arbitrage propriétaire — #7480, #4868).

### `Workers Builds: gestionemploye` est rouge
Déploiement Cloudflare Workers **hors périmètre de la PR** (le workflow ne se
déclenche pas « pour la PR » mais pour la surface). Ne pas investiguer : ce check
n'est pas requis.

### La revue **Strix** est rouge (« this workspace's trial has ended »)
La revue de sécurité ne tourne plus du tout. Ce n'est pas un verdict sur la PR.
Suivi : #7480 (décision : renouveler, remplacer, ou retirer le check).

### Un check **REPO** est rouge
C'est le cas le plus piégeux : il ne bloque pas le merge, mais il peut signaler une
vraie régression. **Lire le log du job** (`gh run view <run-id> --log`, ou
l'onglet *Details* du check) — le **résumé** du check peut être vide (constaté sur
`Governance Gates`, #7466 : le motif réel, « Web admin feature surface changed but
neither SCENARIOS_TEST_WEB_ADMIN_… nor REGISTRE_SCENARIOS_TESTS was updated »,
n'apparaissait que dans le log).

Si le motif est légitime et récurrent, ouvrir une issue de suivi : un check
durablement rouge sans issue est un défaut de gouvernance.

## 4. Ce que ce dispositif ne fait pas

- Il ne **corrige** pas les quotas tiers (hors de portée du dépôt).
- Il ne **rend pas** un check non requis bloquant, et ne modifie pas la protection
  de branche.
- Il ne remplace pas la revue du log quand un check REPO échoue.

## 5. Liens

- Issue #7480 · script `dev-hub/tools/report-external-checks.sh` ·
  workflow `.github/workflows/pr-external-checks-report.yml`
- Checks requis : `BRANCH_PROTECTION_REQUIRED.md`
- Leçon « skip silencieux » : `AGENTS.md` (#3545) · saturation CI :
  `docs/infra/02_alignement/CI_SATURATION.md`
