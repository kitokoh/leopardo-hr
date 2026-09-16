# Checks tiers (quota / abonnement) — conduite à tenir

> Issue #7480. Dernière révision : 2026-09-16.
> Voir aussi : `BRANCH_PROTECTION_REQUIRED.md` (checks **requis** au merge),
> `docs/GOUVERNANCE/REGISTRE_GARDES.md`, leçon #3545.

## Le problème

Plusieurs checks sont posés par des **applications tierces**, pas par nos
workflows. Ils échouent pour des raisons **extérieures au code** :

| Check | Application (`app_id`) | Motif d'échec observé |
|---|---|---|
| `Workers Builds: gestionemploye` | `cloudflare-workers-and-pages` (85455) | build du Worker Cloudflare en échec, y compris sur des PR qui ne touchent pas cette surface |
| `Cloudflare Pages` | `cloudflare-workers-and-pages` (85455) | échec d'intégration |
| `Vercel Preview Comments` + statut `Vercel` | `vercel` (8329) | `Resource is limited - try again in 24 hours (more than 100, code: "api-deployments-free-per-day")` — quota de déploiements gratuits du jour atteint |
| revue de sécurité externe | (appli tierce) | « this workspace's trial has ended » |

**Conséquence** : un check rouge **permanent** ne signale plus rien. Un échec de
quota finit par ressembler à un échec de code, et l'équipe apprend à l'ignorer —
exactement le défaut de la leçon #3545 (« un skip silencieux ressemble à un
succès »), vu de l'autre côté.

## Règle 1 — un check tiers n'est JAMAIS requis au merge

Ces checks dépendent d'un quota ou d'un abonnement : ils ne peuvent donc pas
juger la santé du code, et **ne doivent pas figurer dans les checks requis** de
`main`.

L'état réel de la protection et l'invariant sont vérifiés automatiquement :

```bash
# état réel de la protection vs canonique committé + invariant « aucun check tiers requis »
GITHUB_TOKEN=<token admin:read> dev-hub/tools/check-branch-protection.sh kitokoh/leopardo-hr main
```

Le référentiel machine est `dev-hub/tools/branch-protection-canonical.json` ; la
liste des applications tierces interdites de sélection est sa clé
`third_party_checks_never_required`. Ajouter une application à cette liste
suffit à interdire qu'elle devienne requise. Le workflow
`branch-protection-guard.yml` exécute la garde (cron quotidien + PR touchant ces
fichiers).

## Règle 2 — lire le motif, pas la couleur

Avant de conclure « le code est cassé », lire le **message** du check :

| Motif lu | Interprétation | Conduite |
|---|---|---|
| `api-deployments-free-per-day` / `more than 100` | quota Vercel du jour | ne pas toucher au code ; le déploiement de prévisualisation reprendra le lendemain (le statut Vercel est déjà neutralisé par un `ignoreCommand` — `Canceled by Ignored Build Step`) |
| `❌ Deployment failed` sur `Workers Builds: gestionemploye` alors que la PR ne touche pas `edge/` | intégration Cloudflare Workers, pas le code de la PR | vérifier la configuration du Worker, pas la PR |
| `trial has ended` | abonnement de l'outil tiers | renouveler ou désactiver l'outil (voir règle 3) |

## Règle 3 — pas de check tiers rouge permanent sans issue de suivi

Un check qui échoue **en permanence** doit avoir une issue ouverte qui porte le
suivi (sinon la couleur rouge devient du bruit). Si l'outil n'est plus utilisé,
la bonne action est de **désactiver l'intégration** (Settings → GitHub Apps)
plutôt que de laisser un rouge permanent.

## Options de fond (décision propriétaire, non tranchée ici)

- **Vercel** : le quota gratuit (100 déploiements/jour) est atteint lors des
  vagues de merges. Trois options : plan supérieur, prévisualisations
  opt-in par label, ou `ignoreCommand` plus strict (déjà en place pour partie).
- **Cloudflare Workers** : corriger la configuration du Worker `gestionemploye`,
  ou désactiver le déploiement automatique sur PR.
- **Revue de sécurité externe** : renouveler l'abonnement ou passer au
  `Semgrep OSS` déjà exécuté par l'organisation
  (`github-advanced-security`, app 57789 — vert sur les PR récentes).
