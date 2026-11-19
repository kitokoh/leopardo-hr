# ADR 0034 — `public-surfaces` est la norme du front grand public

## Statut

**Acceptée** — 2026-11-19

**Remplace** : ADR-0030 (surfaces publiques `front/web` vs `front/marketplace`), dont la prémisse — deux apps publiques coexistent — n'est plus vraie.

**Décideurs** : owner (validation) — rédigée par l'agent Dev
**Liens** : #9630 (cette décision) · #9627 (reprise marketplace) · #9628 (reprise travel-web) · #9629 (verticale Emploi) · ADR-0004 (open core / marketplace, borne supérieure) · BOS-050 (#8208, plumbing public mutualisé)

---

## Contexte

### Ce qui a changé depuis ADR-0030

ADR-0030 (2026-09-28, statut « Proposée ») posait une règle de **répartition entre deux applications** : `front/web` pour le mono-tenant, `front/marketplace` pour l'agrégation cross-tenant, avec `front/travel-web` en exception documentée. Elle ne disait rien de la **structure interne** du code public.

Entre-temps, une architecture DDD est apparue dans `front/web` sans décision qui la ratifie :

- `front/web/src/modules/public-surfaces/` — créé le 2026-11-17 (`f0ec23c9b`, #9577), 79 fichiers, un noyau + 7 verticales.
- Chaque verticale suit la même découpe : `domain/`, `application/`, `infrastructure/`, `components/`, `identity/`.
- `registry.ts` déclare les verticales ; `kernel/domain/surface-registry.ts` impose trois invariants (doublon → `throw` au démarrage, identité validée, `get()` fail-closed).
- `__tests__/layer-guard.test.ts` **fait échouer la CI** si un fichier de `domain/` importe `next/*`, `react`, `@/lib/*`, `@/components/*`, `@/modules/*` ou une couche supérieure.

La structure était donc réelle et opposable, mais **non décidée**. C'est exactement l'état qu'une ADR doit corriger : une règle appliquée par un test sans décision écrite est une règle qu'on peut supprimer sans débat.

### Ce qui a changé avec cette mission

`front/marketplace` et `front/travel-web` ont été **retirés** (#9627, #9628). Leur contenu a été repris dans les verticales existantes :

| Ancienne app | Contenu repris | Verticale |
|---|---|---|
| `front/marketplace` | catalogue, fiche produit, panier, checkout multi-vendeurs, confirmation, suivi, retour de paiement, compte acheteur, favoris, commandes, avis | `retail` |
| `front/travel-web` | recherche, fiche trajet, plan de sièges, réservation, suivi, annulation, compte voyageur | `travel` |
| `front/travel-web` | annuaire opérateurs, devis, course invitée, suivi de course | `vtc` |

Et une huitième verticale a été ajoutée : `careers` (#9629), qui expose enfin `GET /public/careers` — l'annuaire cross-tenant qui n'alimentait jusqu'ici que le sitemap.

**Conséquence directe** : `front/web` est désormais le **seul** front Next.js du dépôt. La règle de répartition d'ADR-0030 n'a plus d'objet — il n'y a plus deux apps entre lesquelles répartir. La question qui reste ouverte est celle qu'ADR-0030 n'a jamais traitée : **comment le code public est-il structuré à l'intérieur de `front/web` ?**

---

## Décision

**`front/web/src/modules/public-surfaces` est la norme unique du front grand public. Toute surface publique nouvelle y entre comme une verticale ; aucune surface publique ne vit ailleurs.**

### 1. Le périmètre de la norme

Est « front grand public » tout ce qu'un visiteur non authentifié peut atteindre sans compte : annuaires, fiches, parcours transactionnels invités, suivi par référence, recherche d'emploi, candidature.

**Sont dans le périmètre** et doivent passer par `public-surfaces` : les 8 verticales (`restaurants`, `retail`, `hospitality`, `trade`, `travel`, `pharmacy`, `vtc`, `careers`), le noyau, et le bounded context `portal` (chrome commun, accueil, annuaire générique) qui n'est pas une verticale mais partage la même structure.

**Sont hors périmètre**, et cette ADR ne les touche pas :

- le front vitrine destiné aux professionnels et à leurs entreprises (`modules/vitrine`, `/business/*`) ;
- le front Super admin et les consoles du dashboard tenant (`(dashboard)/*`), y compris `/recruitment` qui **publie** les offres — publier est un acte authentifié, chercher est un acte public ;
- `front/admin-dashboard`, `front/web-offline`, `front/zkteco-kiosk`, `front/mobile_apps` — autres produits, autres stacks.

### 2. La structure obligatoire d'une verticale

```
<verticale>/
├── domain/          règles pures — aucun import framework, aucun appel réseau
├── application/     cas d'usage — orchestrent domaine + infrastructure
├── infrastructure/  adaptateurs réseau et persistance
├── components/      rendu React
├── identity/        palette, nom, verticale déclarée
└── index.ts         surface d'export publique de la verticale
```

**`domain/` n'importe rien de l'extérieur.** C'est la règle que `layer-guard.test.ts` vérifie déjà, et c'est la seule de cette ADR qui soit **exécutable** plutôt que déclarative. Une règle d'architecture qu'aucun test ne vérifie est un commentaire.

### 3. Le registre est le point d'extension unique

Ajouter une verticale = ajouter une entrée dans `registry.ts`. Rien d'autre. Le registre est **fail-closed** : une verticale inconnue rend `null`, jamais un défaut générique — une page publique ne doit pas s'afficher avec la mauvaise identité parce qu'un slug était mal orthographié.

### 4. Le noyau porte ce qui est réellement partagé

`kernel/` contient ce que plusieurs verticales utilisent : identité de surface, résolution de domaine, formatage monétaire, primitives de rendu, résolution d'URL d'API. **Une règle utilisée par deux verticales monte dans le noyau ; une règle utilisée par une seule reste dans sa verticale.** Le formatage monétaire est l'exemple canonique : trois verticales affichent des montants, donc il vit dans `kernel/domain/money.ts` — et il gère les devises sans décimales, parce qu'un `XOF` divisé par 100 est faux d'un facteur 100.

### 5. Aucune nouvelle app publique

Il n'y a plus de « 3ᵉ app publique » à interdire : il n'y en a plus qu'une. La règle devient plus simple et plus stricte — **une surface publique nouvelle est une verticale, pas une application.** Créer une app Next.js séparée pour une surface publique est un motif de refus en revue, sans exception à documenter.

### 6. Ce qui reste vrai d'ADR-0030

Deux points survivent et sont repris ici :

- **ADR-0004 reste la borne supérieure** : vitrine commerciale et consoles sont enterprise-only ; toute exposition externe passe par les contrats publics.
- **Le plumbing public mutualisé (BOS-050) reste obligatoire** : `PublicTenantResolver`, `IdempotentGuestWrite`, `TrackingSecretService`, throttle dédoublonné. Une neuvième réimplémentation du parcours panier → commande → suivi est un motif de refus.

Tout le reste d'ADR-0030 — la répartition mono-tenant / cross-tenant entre deux apps, l'exception `front/travel-web`, le statu quo sur l'annuaire restaurants — est **caduc**.

---

## Conséquences

**Positives**

- La règle qui était appliquée par un test est maintenant **décidée**. Supprimer `layer-guard.test.ts` demanderait désormais de renverser une ADR.
- Un seul front Next.js à construire, déployer et surveiller : deux workflows de déploiement, deux entrées de matrice de dérive et deux paires de parité de fichiers partagés ont disparu avec les apps.
- Le candidat n'a plus besoin de connaître le slug d'une entreprise : `/emploi` est le point d'entrée qui manquait.
- Le contenu de deux apps ne peut plus diverger : il n'y a plus deux copies.

**Limites assumées**

- **La migration est désormais quasi complète** (#9633, #9634). Ce qui est entré dans `public-surfaces` :
  - `modules/public-portal` → `public-surfaces/portal` (bounded context du chrome commun : en-tête, pied de page, navigation basse, thème, accueil, annuaire générique, copie i18n). Ce n'est **pas** une verticale : il n'a pas d'identité de surface et ne figure pas dans le registre.
  - `app/shop` → `restaurants/components/ShopOrderSurface` (commande à jeton boutique).
  - `app/order` → `restaurants/components/RestaurantOrderTrackingSurface` (suivi par référence).
  - `app/restaurants` (explorateur, helpers de formatage) → `restaurants/components/`.
  - `app/restaurants/[slug]` (panneau de commande) → `restaurants/components/RestaurantOrderPanel`.
  - `app/stay/[slug]` (panneau de réservation, helpers) → `hospitality/components/`.

  Les pages `app/**` correspondantes ne sont plus que des **adaptateurs Next** de 10 à 15 lignes. Aucun parcours n'a été perdu : panier, commande idempotente, suivi par référence, séjour, menu restaurant, avis.

- **Ce qui reste hors de `public-surfaces`, et pourquoi** :
  - `modules/vitrine` (landings marketing, dont `/business/careers`) — **hors périmètre par décision** : c'est le front commercial destiné aux professionnels et à leurs entreprises, explicitement exclu du périmètre de cette ADR (§ « Sont hors périmètre »). Il n'a pas vocation à entrer.
  - `/[companySlug]/careers` — conservée à côté de `/emploi/[companySlug]` (voir ci-dessous).
  - Les consoles du dashboard tenant et le front Super admin — hors périmètre (authentifiés, pas publics).
- **`/[companySlug]/careers` reste en place** à côté de `/emploi/[companySlug]`. Les deux servent les mêmes offres ; l'ancienne route est conservée pour les liens publics en circulation. Sa suppression est une décision produit distincte (SEO, liens sortants), pas une conséquence de cette ADR.
- **Pas d'espace candidat.** Le contrat API n'a pas de compte candidat : le suivi se fait par e-mail. La verticale `careers` expose le parcours réel, elle ne simule pas un espace qui n'existe pas.
- **`careers` n'a pas de bandeau « Devenir tenant ».** Il n'existe pas de pack d'inscription recrutement ; `TENANT_INVITATIONS` est devenu `Partial` et l'absence d'entrée est le comportement fail-closed — pas de bandeau plutôt qu'un lien vers le mauvais endroit.

## Règles opérationnelles

1. **Avant d'ajouter une surface publique** : est-elle dans le périmètre (accessible sans compte) ? Si oui, elle est une verticale dans `public-surfaces`. Si non, elle n'est pas concernée par cette ADR.
2. **Une verticale = la structure en 5 couches.** Pas de `domain/` qui importe React, `next/*` ou `@/lib/*` — `layer-guard.test.ts` le refuse, et cette ADR le justifie.
3. **Une règle partagée par deux verticales monte dans `kernel/`.** Une règle utilisée par une seule reste dans sa verticale.
4. **Le registre est le seul point d'extension.** Une verticale non déclarée n'existe pas.
5. **Pas de nouvelle app Next.js pour une surface publique.** C'est une verticale.
6. **BOS-050 reste obligatoire** ; ADR-0004 reste la borne supérieure.
7. **Cette ADR est en vigueur.** Elle n'attend pas de validation supplémentaire : le code qu'elle décrit est livré et testé.
