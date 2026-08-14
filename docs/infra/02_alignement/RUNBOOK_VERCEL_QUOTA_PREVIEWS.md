# Runbook — Quota Vercel `api-deployments-free-per-day` (checks rouges sur PR)

> Issue #2010 — le check externe « Vercel » échoue sur (quasi) chaque PR de la
> vague multi-pays : `Deployment failed for project [leopardo] — Resource is
> limited — try again in 24 hours (more than 100, code:
> "api-deployments-free-per-day")`. Ce runbook documente le diagnostic et les
> leviers (dashboard Vercel + repo), dans la même famille que #1834 (CF Pages)
> et #1914 (Workers Cloudflare) : un état du projet Vercel, pas un bug de code.

## Contexte (vérifié 2026-08-14)

- Le site vitrine `front/web` est déployé sur Vercel via l'**intégration Git
  Vercel** (app GitHub « Vercel »), qui crée le check « Vercel Preview
  Comments » sur chaque PR et un déploiement par push.
- Le quota du **plan gratuit** est de **100 déploiements/jour** :
  ~100 déploiements de preview consommés par les pushes de la vague multi-pays
  → quota épuisé avant la fin de journée → tous les déploiements suivants
  (PR **et** main) échouent avec `api-deployments-free-per-day`.
- Le check « Vercel » **n'est PAS dans les required checks** de main
  (Backend Coverage, PHPStan Strict, Module Structure Validator, Frontend
  ESLint+TS, actionlint) : un rouge Vercel ne bloque pas le merge — mais il
  masque les vraies erreurs et bloque les déploiements réels.
- Côté repo, le garde `front/web/vercel.json` existe déjà :
  - `ignoreCommand` (fix #1724) : saute le build quand `front/web/` n'a pas
    changé (fallback `HEAD^` si `$VERCEL_GIT_PREVIOUS_SHA` est absent) ;
  - `git.deploymentEnabled` : `main` et `staging` = true, `develop` = false.

## Procédure de diagnostic / réparation (dashboard Vercel)

1. Aller sur https://vercel.com/dashboard → projet **`leopardo`** → onglet
   **Settings → Git**.
   - Vérifier la **branche de production** : doit être `main`.
   - Vérifier **Preview deployments** : si le flux PR previews n'est pas un
     besoin, les **désactiver** (levier n°1 ci-dessous) — c'est la seule vraie
     protection contre le quota free tier.
2. Vérifier le plan (Settings → Overview) : le quota `api-deployments-free-per-day`
   = 100 déploiements/jour ne peut pas être relevé dans le repo. Deux options :
   - **A. Réduire le volume** : désactiver les previews PR (recommandé, gratuit) ;
   - **B. Passer à un plan payant** (Pro/Enterprise) si le volume PR previews
     est un besoin.
3. Si un déploiement main réel a échoué pendant la fenêtre de quota :
   - attendre la fenêtre de 24 h OU passer sur le plan payant,
   - puis **Redeploy** le dernier déploiement main réussi (Deployments →
     ⋯ → Redeploy).

## Leviers côté repo (déjà en place / à ne pas casser)

- `front/web/vercel.json` :
  - `ignoreCommand` (fix #1724) — **ne pas** le simplifier vers `exit 1`
    (build systématique) : c'est lui qui évite un build par push backend/docs ;
  - `git.deploymentEnabled` — garder `develop: false`.
- Ne **pas** ajouter de workflow GitHub de déploiement Vercel tant que
  l'intégration Git Vercel existe (doublon de déploiement).

## Règle pour les agents

- **Ne pas tenter de réparer ce check depuis une PR** — c'est un état du
  projet Vercel (plan + intégration Git), pas du code du repo.
- Si le check « Vercel » apparaît rouge sur une PR : vérifier qu'il ne bloque
  pas le merge (il n'est pas requis), puis **le signaler** avec un lien vers
  ce runbook. Ne pas retenter un push juste pour « re-déclencher » Vercel —
  chaque push consomme du quota.
- Toute modification de `front/web/vercel.json` doit garder les gardes
  ci-dessus verts (voir les pièges Vercel dans `AGENTS.md`).
