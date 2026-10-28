# Registre de décisions — session du 2026-10-28

Ce document consigne les arbitrages pris pendant la session d'implémentation du
2026-10-28, sur les issues restées ouvertes après les livraisons de code.

Il ne remplace pas `CHANGELOG.md` (qui décrit *ce qui a été livré*) ni les ADR
(qui décrivent *pourquoi une frontière est là*). Il décrit **ce qui a été
décidé de ne pas faire, et pourquoi** — parce qu'une issue laissée ouverte
sans motif écrit est indistinguable d'une issue oubliée.

## Méthode

Pour chaque issue, la question posée n'était pas « peut-on coder ça ? » mais
**« est-ce qu'un agent peut la fermer sans mentir ? »**. Trois catégories en
ressortent :

- **Fermable par du code** → implémentée, une branche par groupe, CI requise verte.
- **Non fermable par du code** — l'issue dépend d'un acte qui n'est pas dans le
  dépôt (dashboard d'un fournisseur, secret de production, décision humaine).
  La coder *serait* le mensonge : on livrerait un contournement qui laisse croire
  que le problème est réglé.
- **Bloquée par une dépendance amont déclarée** — l'issue le dit elle-même
  (`blocked`), et la lever demande le travail d'une autre issue.

## Issues fermables par du code — traitées

| Issue | Livraison | État |
|---|---|---|
| #8725 / #8726 (dette de fixture) | `CreatesMvpSchema` : 4 tables Pages rattrapées → garde `Hygiene Guards` verte | PR #8968 |
| #7962 (tranche) | `ProvisionKiosk` extrait du `KioskController` (859 → 782) | PR #8970 |
| #7959 (points 2 et 4) | Pile de middlewares tenant unifiée + ADR-0031 sur `Absence` | PR #8973 |
| #8396 (point 2) | Garde de dates figées livrée par #8396 lui-même — **vérifiée, non refaite** | déjà sur `main` |
| #7959 (point 3) | Gardes d'isolation livrées par #5584/#8059/#5860 — **vérifiées, non refaites** | déjà sur `main` |
| #7655 (point 3) | TTL Sanctum 30 j glissants + rotation — livré par ADR-0025 — **vérifié, non refait** | déjà sur `main` |
| 11 PR Dependabot | Mergées après déblocage de `Hygiene Guards` | mergées |

**Note de méthode importante** : trois chantiers que cette session avait
planifiés se sont révélés **déjà livrés sur `main`**. La réaction correcte
était de vérifier, constater, et **ne pas produire de doublon** — pas de
« réimplémenter pour avoir une PR ». J'ai créé puis supprimé une garde de
dates figées avant de découvrir `check-tests-no-frozen-dates.sh`, qui fait
strictement mieux (diff-based, exemptions documentées, tests propres). La
supprimer était la bonne décision ; la garder aurait été de la dette déguisée
en progrès.

## Issues non fermables par du code — décision : rester ouvertes, avec motif

### Actions propriétaire (secrets, dashboards, comptes)

| Issue | Ce qui manque | Décision |
|---|---|---|
| #7657 | `BRANCH_PROTECTION_TOKEN`, secrets backup S3 | **Reste ouverte.** Créer un secret exige le dépôt GitHub et les identifiants du propriétaire. Un agent qui écrirait un secret bidon rendrait les deux gardes vertes en apparence et fausses en réalité — pire que rouge. |
| #8285 | Panne Cloudflare « Workers Builds : gestionemploye » | **Reste ouverte.** Le correctif est dans le dashboard Cloudflare du propriétaire. Aucune ligne de code du dépôt ne peut l'éteindre. |
| #7994 | Jobs Vercel prod jamais déclenchés (travel-web, marketplace) | **Reste ouverte.** Deux causes distinctes : (a) le job est en `skip` permanent → corrigeable en YAML, mais (b) il faut un **token Vercel en secret de dépôt** pour que le déploiement aboutisse. Sans le secret, activer le job produit un échec à chaque push. Le YAML est prêt à être activé dès que le secret existe ; l'activer avant serait remplacer un skip silencieux par un échec bruyant. |
| #7967 | Render prod : queue + scheduler sur `plan: free` avec spin-down | **Reste ouverte.** Le correctif est un changement de plan d'hébergement (facturation). Coder un contournement (cron externe qui réveille le service) déplacerait le problème sans le résoudre et créerait une dépendance non documentée. |
| #8191 | Audit `scheduler-duplicates` sur **copie de prod** | **Reste ouverte.** Exige un accès à une copie de la base de production. La commande d'audit existe ; l'exécution est un acte d'exploitation. |
| #8880 | Pilote Dakar : désigner le responsable terrain | **Reste ouverte.** Décision de direction sur une personne. |

### Décisions humaines et campagnes de mesure

| Issue | Nature | Décision |
|---|---|---|
| #8232 | Pilote Léa mesuré → décision GA / itération / retrait | **Reste ouverte.** La mesure demande des utilisateurs réels ; la décision est un arbitrage produit. |
| #8231 | UI onboarding Léa (front/web) | **Reste ouverte.** Dépend de #8232 : construire l'écran avant de savoir si le parcours est retenu serait du travail jetable. |
| #7941 | Campagne de validation experte par marché (CM, CI, FR, TR, CA) | **Reste ouverte.** Exige des juristes par juridiction. Aucune valeur ne peut être inventée ici : un barème légal faux est pire qu'un barème absent. |
| #7924 | EPIC conformité légale multi-marchés | **Reste ouverte** (EPIC). Se dépile par marché, adossée à #7941. |

### Dépendances amont déclarées

| Issue | Bloquée par | Décision |
|---|---|---|
| #8210 | Le lot « primitive de signature » est **livré** (voir CHANGELOG : `OutboundWebhookSigner`) ; reste le dispatcher commun et le raccordement à l'outbox BOS-020 | **Reste ouverte.** Le dispatcher dépend de l'outbox, qui n'est pas dans ce dépôt à ce stade. Tranche suivante naturelle après BOS-020. |
| #8727, #8728, #8729, #8730 | Socle Pages **livré** (#8723–#8726). Ces quatre issues sont en `status:draft` et `blocked` : ce sont des specs de surface (migration Showcase, tenant, superadmin, public SSR) à dériver du socle | **Restent ouvertes en `status:draft`.** Les implémenter sans validation de la spec reviendrait à inventer des écrans. Le socle étant vert, elles sont désormais **déblocables** — c'est un travail de plusieurs lots, pas d'une tranche. |
| #8448 | KDS cuisine : app dédiée offline-first | **Reste ouverte.** App mobile distincte ; dépend du socle `EdgeSync` livré (#8609) mais demande un projet Flutter à part entière. |

## Ce que la session a explicitement refusé de faire

1. **Forcer un merge.** Aucune PR mergée sans ses checks requis verts. Les 11
   PR Dependabot ont attendu que `Hygiene Guards` redevienne réellement vert —
   c'est-à-dire que la cause racine soit corrigée, pas contournée.
2. **Élargir une allowlist pour faire passer une PR.** Les gardes d'isolation
   (#5584), de parité BC (#5860) et de pureté des couches (#6568) ont été
   respectées : le code a été **découpé** pour leur plaire, pas l'inverse.
3. **Créer un secret ou un token de production.** Voir #7657/#7994.
4. **Dupliquer une garde existante.** Voir la note de méthode ci-dessus.
5. **Toucher au cluster #8199 / #8953 / #8954 / #8955.** Un autre agent y
   travaillait ; deux agents sur la même branche produisent des conflits que
   personne ne relit.

## État final de `main`

- Les 11 PR Dependabot sont **mergées** (`2901502c3` en tête après les merges).
- `Hygiene Guards` était rouge sur `main` avant la session ; sa cause racine est
  corrigée par la PR #8968. Tant que #8968 n'est pas mergée, `main` porte encore
  la dette de fixture — c'est le seul rouge de `main` imputable à cette session.
- Aucune branche poussée n'a été forcée, aucune branche d'autrui modifiée.
