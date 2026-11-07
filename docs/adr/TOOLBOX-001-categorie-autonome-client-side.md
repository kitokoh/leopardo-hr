# ADR TOOLBOX-001 — Leopardo Toolbox : catégorie autonome, calcul côté client, sans backend

- **Statut** : accepté
- **Date** : 2026-11-05
- **Périmètre** : `front/web/src/modules/toolbox/**`, `front/web/src/app/toolbox/**`,
  `front/web/src/app/sitemap.ts`, `front/web/src/modules/vitrine/components/Footer.tsx`,
  `front/web/src/modules/vitrine/lib/vitrine-locale.ts`
- **Branche** : `feat/vitrine-toolbox` (préfixe `vitrine-` requis par le verrou vitrine CI)
- **Documentation** : `docs/leopardo-toolbox/`

## Contexte

Leopardo HR évolue vers un Business OS. La mission « Leopardo Toolbox » demande une
catégorie autonome d'outils numériques universels (calculateurs, convertisseurs),
accessible **sans authentification** depuis la vitrine publique, extensible, légère
et fiable, sans déstabiliser le Business OS existant.

Contraintes imposées par la mission :

- Toolbox n'est **pas** un module RH ni une sous-catégorie métier : catégorie autonome.
- Authentification **jamais obligatoire** pour les outils publics ; pas de compte
  artificiel pour les visiteurs.
- Pas de troisième identité utilisateur, pas de modification de l'identité, des
  paiements, des rôles superadmin ou des permissions globales sans décision explicite.
- Pas de Bounded Context supplémentaire sans justification architecturale.
- Calculs côté client quand c'est approprié ; **pas d'API backend par calculatrice**.
- Si Toolbox est indisponible, la vitrine et les autres verticales continuent.

## Décision

1. **Module frontend autonome** `front/web/src/modules/toolbox/` — **aucun module
   backend, aucun Bounded Context, aucune migration, aucun endpoint API**.
2. **Calcul 100 % côté client** — les formules sont des fonctions pures TypeScript.
3. **Catalogue déclaratif** : `data/catalog.ts` (métadonnées pures) séparé de la
   logique (`tools/<slug>/logic.ts`). Ajouter un outil = 1 dossier + 1 entrée.
4. **Routes publiques** `/toolbox` et `/toolbox/[slug]`, **hors** `PROTECTED_PREFIXES`
   → aucun gate de session, aucun compte requis.
5. **Copie i18n locale au module** (`data/copy.ts`, fr/en/tr/ar) plutôt que dans
   `shared/i18n` — ce dernier est propagé vers le backend et le mobile, où la
   Toolbox n'existe pas.
6. **Aucune persistance serveur** au premier périmètre (favoris/historique =
   décision séparée, voir issue 5).

## Justification

- **Risque nul pour le Business OS** : aucune modification de l'API, de l'identité,
  des permissions, des paiements ou des tenants. Le module est isolé ; une panne
  Toolbox n'affecte que `/toolbox` (404/erreur locale).
- **Coût minimal** : pas de migration, pas de déploiement backend, pas de nouveau
  service à opérer.
- **Extensibilité** : le catalogue déclaratif permet d'accueillir des dizaines ou
  des centaines d'outils sans toucher à l'architecture (1 dossier + 1 entrée).
- **Fiabilité** : les formules sont écrites et testées à la main (aucune dépendance
  de calcul externe), avec gestion explicite des cas limites.

## Alternatives écartées

| Alternative | Motif du rejet |
|---|---|
| Module backend DDD + endpoints par outil | Contredit « pas d'API backend par calculatrice » ; coût et risque inutiles |
| Nouveau Bounded Context `Toolbox` | Aucune justification architecturale : pas de logique métier serveur |
| Copie dans `shared/i18n` | Propagation vers backend/mobile où la Toolbox n'existe pas |
| Bibliothèque de calcul externe | Dépendance lourde, surface de confiance accrue, formules non documentées |
| API de taux de change en ligne | Service externe payant / dépendance réseau ; taux statiques documentés préférés |

## Conséquences

- **Positives** : livraison rapide, zéro risque de régression métier, indexation SEO
  immédiate, coût d'exploitation nul.
- **Négatives / limites** : pas de synchronisation multi-appareils des favoris
  (localStorage seulement) ; l'ajout d'un outil nécessite un déploiement front.
- **Décisions différées** (validation explicite requise avant implémentation) :
  persistance « Mes outils » (issue 5) et administration des métadonnées (issue 6).

## Garde-fous

- `/toolbox` ne doit **jamais** être ajouté à `PROTECTED_PREFIXES`.
- Aucune donnée personnelle collectée ; les saisies ne quittent pas le navigateur.
- Tout résultat estimatif/théorique porte un avertissement visible.
- Le verrou vitrine CI impose une branche `(feat|fix|chore|mkt|agent)/vitrine-*`
  pour toute modification de `modules/vitrine/**` ou `(landing)/**`.
