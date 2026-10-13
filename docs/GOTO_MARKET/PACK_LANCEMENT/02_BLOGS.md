# Blogs à rédiger — calendrier et plans SEO

**Issue :** #8445 · **Concept :** `00_CONCEPT.md`

> 10 articles, classés par priorité. Chaque plan : mot-clé cible, angle,
> structure. Le premier est **rédigé** en annexe — c'est l'article vitrine.
> Langue de rédaction : français, puis déclinaison en/ar/tr (jamais de
> traduction littérale : adapter les exemples au marché).

## Calendrier

| # | Titre (provisoire) | Mot-clé cible | Angle |
|---|---|---|---|
| 1 | **Votre restaurant peut tourner sans internet (la preuve)** | logiciel restaurant hors ligne | offline-first, démo chiffrée — **rédigé ci-dessous** |
| 2 | Ouvrir un restaurant : la checklist complète pour être opérationnel en 1 journée | ouvrir un restaurant checklist | guide pratique + checklist de mise en service Leopardo |
| 3 | POS, caisse, cuisine, serveurs : pourquoi vos outils ne se parlent pas (et comment régler ça) | logiciel caisse restaurant intégré | intégration native vs patchwork d'outils |
| 4 | Gérer les rotations d'équipe sur plusieurs sites sans Excel | planning rotation employés | multi-sites natif, exemple station-service + restaurant |
| 5 | SIRH ou Business OS : ce que les PME de terrain devraient exiger en 2026 | SIRH PME terrain | catégorie émergente, critères de choix |
| 6 | 4 langues, une seule plateforme : gérer une équipe fr/en/ar/tr | logiciel RH multilingue arabe | i18n réel + RTL, rareté concurrentielle |
| 7 | Le vrai coût d'une coupure internet en plein service (chiffres) | coupure internet restaurant caisse | étude de coût + témoignage |
| 8 | De l'onboarding au premier service : journal d'un restaurateur (jour par jour) | onboarding logiciel restaurant | storytelling produit |
| 9 | Pointage biométrique, planning, paie : automatiser la RH sans service RH | logiciel pointage paie PME | HR module, kiosque ZKTeco |
| 10 | Étude de cas : 2 restaurants, 1 station-service, 0 tableur — le bilan de Karim après 90 jours | témoignage logiciel gestion restaurant | cas client chiffré (à construire sur pilote) |

## Règles éditoriales

- 1 200–1 800 mots, H2/H3 scannables, 1 visuel maison par article (prompts
  dans `01_PROMPTS_SUPPORTS_COM.md`).
- Chaque article se termine par le même CTA : « Créer mon espace
  gratuitement » + démo offline.
- Maillage interne vers les pages solutions (restaurant, station-service…).
- Mesure : convention UTM (`../CONVENTION_UTM.md`).

---

## Annexe — Article #1 rédigé

### Votre restaurant peut tourner sans internet (la preuve)

**Il est 19 h 42. Salle pleine. Et la box internet vient de lâcher.**

Chez la plupart des restaurateurs, c'est le début d'une soirée compliquée :
la caisse se fige, les tickets cuisine n'arrivent plus, les serveurs
repassent au papier, et le lendemain, quelqu'un devra tout ressaisir — en
priant pour ne rien oublier.

Chez un restaurateur équipé de Leopardo, il ne se passe **rien**. Le service
continue. Voici pourquoi — et comment vérifier vous-même.

#### Le vrai problème : vos outils dépendent du réseau

Un POS « cloud » classique est un navigateur déguisé : pas de réseau, pas de
caisse. Or une coupure ne prévient pas. Elle arrive un samedi soir, quand la
salle est pleine et que chaque minute d'arrêt coûte des couverts.

La bonne question n'est donc pas « est-ce que votre logiciel est dans le
cloud ? » mais « **est-ce que votre service s'arrête quand internet
s'arrête ?** ». Si la réponse est oui, votre système est fragile par
construction.

#### Ce que « offline-first » veut vraiment dire

Chez Leopardo, l'établissement embarque un **nœud local** (le bridge Edge) :
vos apps — serveur, cuisine, POS — dialoguent avec lui, en priorité, sur
votre réseau local. Internet tombe ? Rien ne change à l'écran :

- le **serveur** continue de prendre les commandes sur mobile ;
- la **cuisine** reçoit et traite ses tickets ;
- le **POS** encaisse, ticket après ticket ;
- le **pointage** des employés continue au kiosque.

Chaque opération est enregistrée localement avec une clé unique. Quand le
réseau revient, tout se **resynchronise tout seul**, sans doublon : une
vente encaissée hors-ligne n'apparaîtra jamais deux fois dans vos rapports.

#### Ce que ça change, en chiffres

Sur un samedi soir type (120 couverts, ticket moyen 25 €) :

- **30 minutes** de coupure = jusqu'à **1 500 €** de chiffre d'affaires en
  risque, plus la tension en salle et en cuisine.
- Avec un système offline-first : **0 €** perdu, **0** ressaisie, **0**
  stress supplémentaire.

#### Vérifiez-le vous-même — en 2 minutes

On ne vous demande pas de nous croire sur parole :

1. Lancez la démo restaurant Leopardo.
2. **Coupez votre wifi.**
3. Prenez une commande, envoyez-la en cuisine, encaissez-la.
4. Rallumez le wifi : la vente est dans vos rapports.

C'est ce test — pas nos slides — qui définit un logiciel fait pour le
terrain.

#### Le logiciel doit s'adapter à votre réalité, pas l'inverse

Un restaurant, une station-service, une boutique : ce sont des entreprises
de terrain. Le terrain, c'est le réseau qui tombe, l'équipe qui tourne, le
deuxième site qui ouvre. Votre outil doit encaisser tout ça sans
que vous ayez à y penser.

**Créez votre espace gratuitement — et testez la démo hors-ligne.**
*Votre entreprise tourne. Même hors-ligne.*
