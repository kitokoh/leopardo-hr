# ADR-0037 — Levée du gel de scope MVP

- **Statut** : Acceptée
- **Date** : 2026-11-22
- **Décideur** : propriétaire du produit (délégation fondateur)
- **Issues** : #9803 (cette ADR), #7428 (catégorie « suite métier »)
- **Amende** : `docs/GESTION_PROJET/GO_NO_GO_MVP.md` (décision du 2026-04-21)

## Contexte

Le 2026-04-21, `docs/GESTION_PROJET/GO_NO_GO_MVP.md` a acté une décision **GO MVP** assortie
d'un **gel de scope** :

> Aucune nouvelle feature hors correction bloquante ne doit être ajoutée avant la première
> boucle de retours beta.

Cette décision était juste à sa date : le produit était un noyau RH (auth, employés, pointage,
estimation, reçu PDF, dashboard manager, mobile) et l'objectif était de **ne pas diluer** un
pilote encadré.

Depuis, deux faits mesurables l'ont rendue caduque :

1. **Le périmètre livré a dépassé le MVP.** `api/app/Modules/` contient **41 modules**, dont
   **13 entièrement nouveaux** postérieurs à avril 2026 : `FarmOs`, `Trade`, `Retail`,
   `Showcase`, `Pages`, `Catalog`, `SearchIntelligence`, `Fundraising`, `FuelStation`,
   `Pharmacy`, `HospitalityManager`, `HealthManager`, `EduManager`.
2. **La catégorie produit a changé.** La décision **#7428** (2026-09-16) acte
   « suite métier / business suite » et proscrit « logiciel RH », « HR SaaS » et
   « Mobile-First Company OS » comme catégorie
   (`docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md`).

Le gel est donc **factuellement mort mais juridiquement actif**. C'est le pire des deux états :
un contributeur qui lit le document canonique de décision MVP y trouve une interdiction en
vigueur, contredite par sept mois de livraisons. Un document de décision qui n'est plus cru
désapprend à l'organisation à lire ses propres décisions — c'est exactement le défaut relevé par
l'audit du 2026-11-22.

## Décision

**Le gel de scope du 2026-04-21 est levé à compter du 2026-11-22.**

Il est remplacé par le cadre ci-dessous. Une levée sans règle de remplacement laisserait le vide
qui a produit la dérive ; le cadre est donc la partie normative de cette ADR.

### R1 — Critère d'entrée d'un nouveau module

Un nouveau module sous `api/app/Modules/` n'est ouvert que si **l'un** des deux est vrai :

- un **problème client nommé** est documenté (verticale, cas d'usage, issue de rattachement) ;
- une **décision propriétaire tracée** l'autorise (ADR ou issue avec validation owner).

Un module ouvert « pour compléter la suite » sans l'un des deux est refusé en revue.

### R2 — Obligation de registre

Tout module livré est déclaré dans le **registre unifié modules/features/solutions**
(ADR-0026). Un module absent du registre n'est pas considéré comme livré, même si son code
existe.

### R3 — Obligation de tests

Un module sans dossier de tests dédié sous `api/tests/{Feature,Unit}/<Module>/` est un module
**non livré**. L'état est mesuré et publié par
`dev-hub/tools/generate-architecture-status.py` (§3 de `docs/ARCHITECTURE_STATUS.md`), donc
visible en permanence plutôt que découvert en audit.

> État à la date de cette ADR : 5 modules sans dossier de tests dédié — `Absence` (façade
> assumée, ADR-0031), `FarmOs`, `HospitalityManager`, `RestaurantManager`, `TravelAgency`.
> Ce sont des **dettes déclarées**, pas des silences.

### R4 — Revue périodique du portefeuille

Le portefeuille de modules est revu à chaque cycle de release, avec trois issues possibles par
module : **maintenir**, **geler** (plus d'évolution, correctifs seulement), **retirer**
(dépréciation documentée). Un module que personne ne maintient est un coût, pas un actif.

### R5 — Ce que le gel protégeait reste protégé

La levée ne rouvre **pas** la porte à la dilution du noyau. Les garde-fous qui protégeaient le
pilote restent en vigueur :

- les parcours cœur (auth, employés, pointage, paie, absences) restent prioritaires sur toute
  extension ;
- une régression P0/P1 sur le noyau prime sur toute feature nouvelle ;
- les promesses publiques restent bornées par `docs/REFERENTIEL_PRODUIT/MESSAGE.md` et
  `METRIQUES_VITRINE.md` (aucun chiffre sans date de mesure).

## Conséquences

**Positives**

- Le document de décision MVP redevient **croyable** : il dit ce qui est vrai.
- Les 13 modules postérieurs au gel cessent d'être une violation tacite et deviennent un
  périmètre assumé, avec des règles.
- La dette de tests de 5 modules devient mesurée et publiée, donc traitable.

**Négatives / coûts**

- Le cadre R1–R4 ajoute une charge de revue : un nouveau module demande une justification
  écrite. C'est voulu — c'est le prix de la levée.
- R4 (revue périodique) crée une obligation récurrente qui n'existait pas.

**Neutres**

- Aucun code n'est modifié par cette ADR. Elle change une règle, pas une implémentation.

## Alternatives écartées

1. **Laisser le gel en place et l'ignorer.** C'est l'état actuel, et c'est le pire : une règle
   écrite que tout le monde viole désapprend à lire les règles.
2. **Réécrire `GO_NO_GO_MVP.md`.** Rejeté : le document est la **trace** de la décision
   d'avril. Réécrire l'histoire rend l'audit impossible. On ajoute un encadré de révision qui
   renvoie ici.
3. **Lever le gel sans cadre.** Rejeté : c'est précisément ce qui a produit 13 modules sans
   critère d'entrée ni revue.

## Références

- `docs/GESTION_PROJET/GO_NO_GO_MVP.md` — décision du 2026-04-21 et gel de scope
- `docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md` — décision #7428
- `docs/architecture/adr/0026-registre-unifie-modules-features-solutions.md` — R2
- `docs/architecture/adr/0031-absence-module-is-a-facade.md` — exception `Absence`
- `docs/ARCHITECTURE_STATUS.md` §3 — mesure des modules sans tests (R3)
- `dev-hub/tools/generate-architecture-status.py` — générateur de cette mesure
