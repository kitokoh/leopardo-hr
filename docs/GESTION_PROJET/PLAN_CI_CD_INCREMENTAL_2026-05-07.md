# Plan CI/CD Incremental - Leopardo RH

> Statut : plan operatif propose
> Date : 2026-05-07
> Source : retour d'execution reel sur les PR #268, #299, #303, #304, #305, #306, #309 et #310

## 1. Intention

Ce plan vise a rendre la CI/CD plus fiable, plus rapide a lire et plus utile pour la prevention des regressions, sans rearchitecture prematuree.

Le principe retenu n'est pas de "refondre la plateforme", mais de renforcer ce qui a deja prouve sa valeur sur le depot :

- GitHub Actions comme source de verite
- changements minimaux par PR
- filtres de chemins pour eviter la CI inutile
- corrections guidees par les checks rouges reels

## 2. Ce que l'experience recente a montre

### Ce qui marche deja

- Les workflows web filtres par chemins ont reduit le bruit CI sur les PR backend, docs et mobile.
- La boucle `gh pr checks` puis `gh run view --log-failed` est plus rapide et plus fiable que des validations locales longues sur Windows.
- Les branches federatrices propres depuis `origin/main` evitent de reintroduire des regressions historiques.
- Les checks de gouvernance, backend, mobile et CodeQL donnent un signal utile pour decider un merge.

### Ce qui ne doit pas etre refait

- Ne pas casser les filtres `paths:` au nom d'une simplification YAML.
- Ne pas traiter le statut externe `Vercel` comme un blocage code tant qu'il n'y a pas de log applicatif utile.
- Ne pas imposer un seuil de coverage backend trop tot sans mesurer le niveau reel.
- Ne pas melanger tests Playwright et unit tests Jest si cela brouille le diagnostic.
- Ne pas cacher `vendor/` de maniere brute ; preferer le cache Composer officiel base sur `composer.lock`.

## 3. Priorites recommandees

Ordre recommande, par rentabilite reelle :

1. Ajouter les garde-fous qui attrapent des regressions produit utiles
2. Rendre la qualite visible avant de la rendre bloquante
3. Simplifier ensuite les workflows quand on sait exactement ce qu'on preserve

En consequence, la fusion des workflows web n'est pas la priorite numero 1. Le gain le plus immediate vient plutot de :

- Playwright en CI
- coverage backend publiee
- tests backend critiques
- scan de secrets

## 4. Plan final recommande

## Phase A - Signal rapide et utile (2 a 3 jours)

### A1. Ajouter un job Playwright dedie

Objectif :
- attraper les regressions UI que lint, TypeScript et Jest ne voient pas

Regle d'implementation :
- creer un job `web-e2e-playwright` dedie
- le declencher seulement sur `web/**` et sur son workflow
- ne pas le melanger au job Jest existant
- archiver screenshot, trace et video en artifact

Pourquoi maintenant :
- le projet a deja gagne du temps quand les erreurs web etaient visibles directement dans GitHub Actions
- separer E2E et unit tests rend les echecs beaucoup plus lisibles

### A2. Publier la coverage backend sans gate initial

Objectif :
- connaitre le niveau reel avant d'introduire une contrainte

Regle d'implementation :
- activer Xdebug ou PCOV uniquement dans le job coverage
- produire `clover.xml` et un rapport HTML ou artifact lisible
- afficher le pourcentage dans le resume du job

Pourquoi maintenant :
- un seuil fixe a l'aveugle risque de bloquer des PR utiles
- le depot a encore des zones heterogenes entre backend ancien et modules plus recents

## Phase B - Couvrir les parcours a risque (3 a 4 jours)

### B1. Ajouter les tests backend critiques manquants

Priorite :
- Auth : echec de login, lockout, RBAC basique, register public si encore expose
- RH : creation payroll, workflow d'absence, permissions de validation
- Mobile/API contract : garder etendre les tests de contrats qui ont deja servi pour l'historique attendance

Regle d'implementation :
- commencer par les parcours qui casseraient la beta ou la prod
- privilegier les tests Feature sur les regles metier et les permissions
- documenter chaque lot dans `CHANGELOG.md`

Pourquoi maintenant :
- c'est le meilleur levier contre les regressions silencieuses
- plusieurs merges recents ont montre que les tests de contrat et les checks cibles gagnent plus de temps que de gros audits manuels

### B2. Ajouter un seuil coverage progressif

Objectif :
- renforcer la discipline sans freiner artificiellement le flux

Regle d'implementation :
- mesurer d'abord la baseline
- introduire un seuil plancher faible mais realiste
- augmenter ensuite par palier

Valeur recommandee :
- ne viser `60%` qu'apres confirmation que le depot peut l'atteindre sans bloquer la maintenance courante

## Phase C - Securite simple et rentable (1 a 2 jours)

### C1. Ajouter un scan de secrets en CI

Objectif :
- bloquer les fuites de cles avant merge

Regle d'implementation :
- ajouter TruffleHog ou equivalent sur PR et push
- limiter le job a un scan clair avec sortie lisible
- documenter la marche a suivre si faux positif

Pourquoi maintenant :
- tres bon retour sur investissement
- zero besoin de changer l'architecture

### C2. Requalifier la strategie backup

Objectif :
- verifier l'existence d'une procedure exploitable, pas recreer une doc deja presente

Etat reel :
- un runbook existe deja : `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`

Action utile :
- verifier qu'il est encore aligne avec Neon/Render et les pratiques actuelles
- clarifier la version "backup manuel obligatoire" si on veut un mode d'exploitation plus simple
- ajouter au besoin une checklist courte "backup hebdo / restore mensuel"

Pourquoi maintenant :
- la documentation existe, donc le bon travail est de la simplifier ou la remettre a jour, pas de la dupliquer

## Phase D - Simplification structurelle apres stabilisation (1 a 2 jours)

### D1. Fusionner les workflows web en un `web-ci.yml`

Objectif :
- gagner en lisibilite et en maintenance

Prerequis :
- filtres `paths:` preserves
- Playwright deja isole
- contenu exact des jobs stabilise

Regle d'implementation :
- remplacer `build.yml`, `lint.yml` et `test.yml` par un workflow unique
- garder des jobs distincts dans le meme fichier :
  - `web-lint`
  - `web-typecheck`
  - `web-unit-tests`
  - `web-build`
  - `web-e2e-playwright`

Pourquoi en dernier :
- aujourd'hui, les trois workflows se comportent deja mieux qu'avant grace aux filtres de chemins
- fusionner trop tot fait courir un risque pour un gain principalement organisationnel

### D2. Ajouter un cache Composer propre

Objectif :
- reduire le temps des jobs backend sans instabilite

Regle d'implementation :
- utiliser `composer config cache-files-dir` ou le cache gere par l'action PHP/Composer
- key basee sur OS + version PHP + `api/composer.lock`
- ne pas versionner ni rehydrater `vendor/` de facon opaque entre jobs

## 5. Decoupage en PR recommande

Pour limiter les regressions et faciliter les reviews :

1. PR CI-1 : job Playwright dedie
2. PR CI-2 : publication coverage backend
3. PR CI-3 : premier lot de tests backend critiques
4. PR CI-4 : gate coverage progressif
5. PR CI-5 : secret scan
6. PR CI-6 : mise a jour ou simplification du runbook backup
7. PR CI-7 : fusion finale des workflows web
8. PR CI-8 : cache Composer propre

## 6. Criteres de succes

- les PR backend/docs/mobile n'executent plus de CI web inutile
- les regressions UI critiques sont detectees par Playwright
- la coverage backend est visible dans GitHub Actions
- un seuil coverage existe, mais il est realiste
- les parcours metier critiques ont des tests automatiques
- les secrets commites sont bloques avant merge
- la procedure backup/restore reste claire et a jour

## 7. Ce qui est explicitement hors scope maintenant

- Kubernetes
- microservices
- staging complexe
- feature flags globaux
- APM lourde
- multi-cloud
- refonte infra AWS
- load balancing avance

Le bon objectif pour Leopardo RH aujourd'hui est :

> fiabiliser la livraison et reduire les regressions avant d'augmenter la sophistication de l'infrastructure.
