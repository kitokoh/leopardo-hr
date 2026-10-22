# ADR — Publication publique par verticale vs Pages : où s'arrête l'un, où commence l'autre

**Date :** 2026-10-21
**Statut :** **proposition — en attente de validation.** Aucune verticale supplémentaire
ne devient découvrable, aucun provider supplémentaire n'est créé, tant que ce document
n'est pas validé.
**Contexte :** `docs/design/etablissements-pages.md` (chantier Établissements & Pages) et
la sémantique de publication livrée pour Trade (`docs/audits/LEOPARDO_PUBLIC_PHASE1_AUDIT.md`
§13.9, PR #8803 / #8808).

---

## 1. Le problème que cet ADR tranche

Deux mécanismes publics coexistent aujourd'hui, sans qu'aucun document ne dise lequel
fait quoi :

1. **La publication par verticale** — chaque module métier porte son propre opt-in
   (`is_public` + `public_slug`, ou `status = active`, ou `enabled`), un `*DiscoverableProvider`
   pour la recherche, un `*ProfileResolver` pour la résolution slug → tenant, un contrôleur
   public et une page SSR dans `front/web`.
2. **Les Pages** (chantier à venir) — une entité `Page` rattachée à un Établissement
   (`Site`), avec blocs de contenu, SEO éditable, états `draft`/`published`/`verified`/`suspended`,
   et un site autonome premium.

Sans arbitrage, la conséquence est mécanique : **un second système de découverte**, deux
sources de vérité sur « ce profil est-il public ? », et à terme deux réponses différentes à
la même question selon le chemin d'entrée.

## 2. Ce que la sémantique par verticale garantit déjà (et qui est repris tel quel)

La sémantique livrée pour Trade est **normative pour toute verticale** et n'est **pas**
remise en cause par les Pages. Elle tient en quatre règles, chacune adossée à un test :

| Règle | Pourquoi | Preuve |
|---|---|---|
| **Opt-in explicite**, `is_public` **faux par défaut** | Publier sans consentement est inacceptable ; aucune heuristique du type « profil complet donc publiable » | `TradePublicPublicationTest` |
| **Fail-closed à la lecture** — le prédicat est **dans la requête** *et* **dans la règle du modèle** | Deux barrières : la première protège la requête, la seconde tout appelant qui construirait le DTO autrement | idem |
| **Slug public unique GLOBAL** (index unique cross-tenant) | Une URL publique ne doit jamais dépendre du tenant courant | migration `2026_10_21_000001_8802_...` |
| **404 uniforme** — slug inconnu, profil dépublié et société suspendue sont **indistinguables** | Un 403 révélerait l'existence du slug (énumération) | idem |

Ces quatre règles sont **reprises telles quelles** par les Pages. Une Page n'a pas le droit
d'être publique sans opt-in, ne peut pas exposer un slug qui n'est pas globalement unique,
et ne peut pas distinguer « inexistant » de « dépublié » dans sa réponse HTTP.

## 3. La frontière

### 3.1 Ce que la publication par verticale **reste** seul à faire

La verticale est **propriétaire de la sémantique métier de sa découvrabilité** :

- **Qui** est découvrable — un restaurant (l'établissement), une boutique (la company), un
  opérateur VTC (une ligne d'annuaire), une entreprise Trade (le profil commercial). Le
  grain diffère par métier et c'est légitime.
- **Sous quelles conditions** — `is_public` **et** feature active **et** société ni
  `suspended` ni `expired`. La verticale seule connaît ses propres préconditions.
- **Quelles actions** sont déclarées (`contact`, `order`, `booking`) — et une action
  déclarée sans contrat public derrière est un bug, pas une option (règle #8761, appliquée
  en #8795 en retirant `booking` chez Restaurant).
- **Quel DTO minimal** sort — nom public, ville, pays, description, catégories. Jamais
  `company_id`, jamais une donnée interne.

Autrement dit : **le vertical décide de ce qui est publiable et de ce qu'on en montre.**

### 3.2 Ce que les Pages **deviennent** propriétaires

La Page est **le contenant éditorial et la surface publique canonique** :

- **Le contenu riche** — blocs typés, ordre, `schema_version`, médias, SEO éditable,
  locales. La publication par verticale n'a **aucune** ambition éditoriale et ne doit pas
  en acquérir.
- **Les horaires d'ouverture** — jamais les horaires RH de `schedules` (confusion
  explicitement écartée par `docs/design/etablissements-pages.md`).
- **L'adresse structurée et la géolocalisation** — le vertical n'a aujourd'hui que des
  coordonnées optionnelles ou absentes (Retail et VTC publient `latitude: null` faute
  d'opt-in géolocalisé).
- **Les états de modération** — `draft`/`published`/`verified`/`suspended`, avec cette
  précision : **`verified` décrit la vérification du lieu, pas une publication automatique.**
- **Le rendu premium** — le site autonome sur domaine propre consomme la **même** Page
  publiée, sans dupliquer ses données.

Autrement dit : **la Page décide de la forme éditoriale et de la modération du lieu.**

### 3.3 La ligne de partage, en une phrase

> **Le vertical dit « ce professionnel accepte d'être trouvé, et voici le minimum
> présentable ». La Page dit « voici ce que le public voit de son établissement, et dans
> quel état de modération ».**

## 4. Comment les Pages alimenteront la découverte **sans** second système

C'est le point qui décide si cet ADR évite ou crée la duplication.

**Les Pages ne créent pas un second `PublicDiscoverableProvider`.** Le contrat de
découverte reste unique (`App\Shared\Contracts\Discovery\PublicDiscoverableProvider`,
`public.discoverable`), et une verticale qui possède des Pages continue d'exposer **un**
provider. Ce provider change seulement de **source de vérité** : au lieu de lire sa table
métier, il lit la Page publiée de l'Établissement.

```
Aujourd'hui :  Vertical ──► sa table (is_public, public_slug) ──► Provider ──► /decouvrir
Après Pages :  Vertical ──► Site ──► Page (published) ─────────► Provider ──► /decouvrir
                                          │
                                          └──► rendu web (même Page, autre adaptateur)
```

Trois garanties rendent la migration sûre :

1. **Un seul contrat public de recherche.** `PublicDiscoveryService` agrège des providers,
   pas des tables. Ajouter les Pages ne change **ni le contrat HTTP** (`GET /public/discover`),
   ni `meta.actions`, ni le format des `items`.
2. **Un seul résolveur par verticale.** `PublicProfileResolver` continue de résoudre
   (`target_type`, `target_slug`) → tenant. Avec les Pages, il résout vers la Page plutôt
   que vers la ligne métier, mais **l'appelant ne le sait pas** : c'est exactement le rôle
   de cette inversion de dépendance.
3. **Le routage ne vit pas dans la base.** `PublicContactRequest` et
   `PublicConversation` reçoivent (`target_type`, `target_slug`) et **jamais** un
   `company_id` : la surface publique reste inchangée quand la source de vérité change.

### 4.1 Ce qui est repris tel quel des Pages vers la découverte

- Les quatre règles du §2 (opt-in, fail-closed, slug global unique, 404 uniforme).
- Le DTO minimal : un item de découverte ne doit **jamais** transporter un bloc de contenu
  de Page, seulement les champs nécessaires à l'affichage d'un résultat.
- Le rejet des préfixes dashboard : une surface publique ne peut pas vivre sous un
  `DASHBOARD_PREFIXES` (`front/web/src/proxy.ts`) — leçon de #8808, où `/commerce` rendait
  la fiche Trade inaccessible (307 → `/auth/login`) alors que tous les tests passaient.

## 5. Ce que cet ADR **interdit** jusqu'à validation

1. **Aucune nouvelle verticale découvrable.** Les 6 verticales actuelles (Restaurant,
   Hospitality, Showcase, Retail, Vtc, Trade) restent le périmètre. En ajouter une avant
   l'arbitrage, c'est figer un modèle qu'on s'apprête à changer.
2. **Aucun nouveau provider de découverte**, même trivial.
3. **Aucune colonne de publication ajoutée à une verticale** — notamment Pharmacy, dont la
   publication est de toute façon bloquée par une question **légale** (publicité
   pharmaceutique, régime variable par pays), pas technique.

## 6. Questions ouvertes à trancher

| # | Question | Options |
|---|---|---|
| Q1 | Grain de la découvrabilité quand un tenant a plusieurs Établissements | un résultat par Établissement / un par tenant avec le principal mis en avant |
| Q2 | Devenir des colonnes `is_public`/`public_slug` des verticales après migration vers Pages | conservées comme drapeau métier + Page comme source de rendu / supprimées après bascule |
| Q3 | Qui édite la Page — le pro lui-même ou un back-office assisté | self-service / assisté / les deux selon la verticale |
| Q4 | `verified` est-il un prérequis de `published`, ou seulement un bonus de classement | prérequis (plus sûr, plus lent) / bonus (publication libre, tri favorise vérifié) |
| Q5 | Le site premium sur domaine propre est-il un produit distinct ou une option de la Page | option de la Page / produit distinct |

## 7. Conséquence si l'ADR est refusé

Sans cet arbitrage, la seule option tenable est de **geler la découverte à 6 verticales**
et de ne pas démarrer les Pages — c'est-à-dire de garder le défaut actuel : la publication
transverse n'avance pas. Ce document existe pour que ce choix soit explicite plutôt que
subi.
