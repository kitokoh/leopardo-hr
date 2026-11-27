# Kit partenaire — cabinet comptable

> **Statut** : kit opérationnel prêt à l'emploi · **Date** : 2026-11-27 · **Issue** : #10018
> **Phase** : P2 du `../REPOSITIONNEMENT_2026.md` — le canal cabinet comptable.
> **Public** : commercial Leopardo, ou fondateur en premier rendez-vous.
> **Cadre bloquant** : `../kits/README.md` (règles communes), `docs/REFERENTIEL_PRODUIT/MESSAGE.md` (promesses interdites).

---

## 0. Pourquoi ce kit existe

Un cabinet comptable gère **20 à 200 PME**. S'il adopte Leopardo, il ne vend pas un logiciel : il **industrialise sa production de paie** et devient un canal de distribution à coût d'acquisition quasi nul.

Ce kit contient tout ce qu'il faut pour ouvrir, convaincre et signer un cabinet — sans autre document. Il se lit dans l'ordre d'un rendez-vous : argumentaire (§1), chiffres (§2), grille de marge (§3), script (§4), objections (§5), onboarding (§6).

**Règle d'or** : on ne vend pas au cabinet une « suite métier de 41 modules ». On lui vend **la fin d'une corvée** : la paie de ses clients, calculée et prouvée, sans ressaisie.

---

## 1. Argumentaire — ce que le cabinet y gagne

### 1.1 La phrase d'ouverture

> « Aujourd'hui, pour produire la paie d'un client, vous ressaisissez des heures qui viennent d'un carnet ou d'un WhatsApp. Leopardo supprime cette ressaisie : les heures arrivent déjà pointées, et la paie se calcule avec les règles algériennes. »

### 1.2 Les trois gains, dans l'ordre où un associé les entend

| # | Gain | Ce que ça change concrètement | Comment le prouver |
|---|---|---|---|
| 1 | **Productivité** | Un dossier de paie cesse d'être un travail de ressaisie. Le temps libéré se réinvestit en dossiers supplémentaires, pas en embauche. | Démo : un employé saisi une fois, visible dans paie + pointage + congés |
| 2 | **Conformité** | La paie DZ est dans l'outil (IRG, CNAS, cotisations), pas dans un tableur que le cabinet maintient lui-même. | Run de paie calculé, validé, verrouillé, bulletin PDF |
| 3 | **Marge** | Le cabinet facture le service à son client et garde une commission sur l'abonnement. Revenu récurrent, sans coût d'acquisition. | Grille §3 |

### 1.3 Ce que le cabinet ne prend pas comme risque

C'est le cœur de l'argument. Un cabinet ne prend pas de **risque technologique** — il prend un risque de **productivité**. Il faut donc désamorcer la peur technique avant de parler de marge :

- **Pas de migration forcée** : le cabinet peut démarrer sur **un seul dossier**, celui d'un client volontaire.
- **Pas d'engagement de volume** : le partenariat ne l'oblige pas à basculer tout son portefeuille.
- **Pas de perte de contrôle** : le cabinet garde la main sur la validation. Leopardo calcule, le cabinet valide et verrouille.
- **Pas de dépendance à une promesse** : les référentiels sont en statut **pilote**, et le premier dossier sert précisément à les valider avec lui.

### 1.4 Le pitch en 30 secondes (à savoir par cœur)

> « Vos clients vous demandent déjà de produire leur paie. Aujourd'hui vous ressaisissez leurs heures. Avec Leopardo, les heures arrivent pointées depuis le mobile ou la borne, la paie algérienne se calcule, vous validez, le run se verrouille. Vous traitez plus de dossiers sans embaucher, et vous gardez une commission sur chaque abonnement. On commence par un dossier, pas par tout votre portefeuille. »

---

## 2. Les chiffres à utiliser — et ceux à ne pas utiliser

### 2.1 Chiffres vérifiés (utilisables tels quels)

| Fait | Valeur | Vérifié le | Source |
|---|---|---|---|
| Run de paie DZ, 9 salariés | 9 bulletins, 842 059,10 DZD brut, 626 085,54 DZD net | 2026-11-27 | Exécution locale |
| Coût employeur du même run | 1 060 994,47 DZD | 2026-11-27 | Exécution locale |
| Bulletin PDF | 879 730 octets générés | 2026-11-27 | `/api/v1/pay-slips/1/pdf` |
| Verrouillage du run | `validated` → `locked` | 2026-11-27 | `/validate` puis `/lock` |
| Anomalies détectées | 0 sur le run de test | 2026-11-27 | `/payroll-runs/1/anomalies` |
| Pays couverts par le moteur | 21 | 2026-11-27 | Résolution des moteurs pays |
| Licence | MIT | 2026-09-20 | `LICENSE` |
| Modules API | 41 (techniques) / 3 en pack + 38 en catalogue (commercial) | 2026-11-27 | `api/app/Modules` |

### 2.2 Chiffres interdits

| Interdit | Pourquoi | Dire à la place |
|---|---|---|
| « Conformité validée » | Les référentiels sont en statut `pilot` | « Le moteur applique les règles DZ ; la validation par un conseil fiscal fait partie du pilote » |
| « X cabinets nous font confiance » | Aucun cabinet signé à ce jour | « Nous ouvrons deux cabinets partenaires sur Alger » |
| « 21 pays opérationnels » | 21 pays résolvent, un seul est au lancement | « 21 pays couverts par le moteur, l'Algérie au lancement » |
| Tout chiffre d'adoption non sourcé | Non mesuré | Ne pas en parler |

---

## 3. Grille de marge

### 3.1 Le modèle

Trois modèles possibles. **Le modèle recommandé est le B** (commission récurrente) : il aligne les intérêts et ne demande aucun investissement au cabinet.

| Modèle | Mécanique | Avantage cabinet | Avantage Leopardo | Quand le proposer |
|---|---|---|---|---|
| **A — Apport d'affaires** | Le cabinet présente, Leopardo facture, le cabinet touche une commission unique | Zéro effort, zéro risque | Contrôle de la relation client | Cabinet méfiant, premier contact |
| **B — Revendeur (recommandé)** | Le cabinet facture son client, Leopardo facture le cabinet au tarif partenaire, le cabinet garde la différence | Marge récurrente, relation client maîtrisée | Volume prévisible, CAC quasi nul | Cabinet déjà structuré sur la paie |
| **C — Marque blanche** | Le cabinet présente l'outil sous sa marque | Différenciation forte | Verrouillage long terme | Cabinet > 100 dossiers, projet stratégique |

### 3.2 Grille chiffrée — modèle B (revendeur)

Base : offre **Pilot à 29 €/mois** (paie + pointage + RH), offre **Operations à 79 €/mois** (modules opérationnels).

| Volume de dossiers | Tarif partenaire / dossier / mois | Prix public conseillé | Marge cabinet / dossier | Marge mensuelle cabinet |
|---|---|---|---|---|
| 1-9 dossiers | 20 € | 29 € | **9 €** (31 %) | 9 € à 81 € |
| 10-29 dossiers | 17 € | 29 € | **12 €** (41 %) | 120 € à 348 € |
| 30-59 dossiers | 14 € | 29 € | **15 €** (52 %) | 450 € à 885 € |
| 60+ dossiers | 12 € | 29 € | **17 €** (59 %) | 720 € et plus |

**Exemple concret, à montrer en rendez-vous** — un cabinet avec **25 dossiers** en Pilot :

| Ligne | Calcul | Montant |
|---|---|---|
| Facturation aux clients | 25 × 29 € | 725 €/mois |
| Coût partenaire | 25 × 17 € | 425 €/mois |
| **Marge brute cabinet** | 725 − 425 | **300 €/mois** |
| Sur 12 mois | 300 × 12 | **3 600 €/an** |

Et si 8 de ces 25 dossiers passent en Operations (79 € public, 55 € partenaire) :

| Ligne | Calcul | Montant |
|---|---|---|
| Facturation clients (8 × 79) | | 632 €/mois |
| Coût partenaire (8 × 55) | | 440 €/mois |
| Marge sur ces 8 | | 192 €/mois |
| **Marge totale cabinet** | 300 + 192 | **492 €/mois — 5 904 €/an** |

### 3.3 Ce que le cabinet peut facturer en plus (et qui ne nous concerne pas)

Le cabinet reste libre de facturer à son client :

- la **mise en place** du pointage (paramétrage, formation des équipes) ;
- la **reprise des données** (employés, contrats, historiques) ;
- l'**accompagnement du premier run de paie** ;
- le **support de premier niveau**.

C'est souvent là que se trouve la marge réelle du cabinet — et c'est un argument : Leopardo ne lui prend pas ce revenu, il le lui rend possible.

### 3.4 Conditions du partenariat (à annoncer clairement)

| Point | Règle |
|---|---|
| Engagement minimum | **1 dossier** pendant 3 mois. Pas de volume imposé. |
| Exclusivité | Aucune au démarrage. Une exclusivité de zone s'étudie au-delà de 50 dossiers. |
| Durée | 12 mois renouvelables, résiliable avec 60 jours de préavis. |
| Facturation | Mensuelle, à terme échu, en euros ou en dinars selon accord. |
| Formation | 2 sessions incluses : une sur la paie, une sur le pointage. |
| Support | Le cabinet assure le premier niveau ; Leopardo le second, sous 24 h ouvrées. |

---

## 4. Script du premier rendez-vous (45 minutes)

### 4.1 Avant le rendez-vous — 10 minutes de préparation

- [ ] Identifier le **nombre de dossiers de paie** du cabinet (site web, LinkedIn, ou question d'ouverture).
- [ ] Identifier **qui décide** : l'associé signataire, pas le collaborateur qui fera la saisie.
- [ ] Préparer **un seul cas d'usage** : une PME de 20 à 50 salariés avec du personnel de terrain.
- [ ] Vérifier que la démo tourne (environnement de démo, pas de production).

### 4.2 Déroulé

**0-5 min — Cadrage**

> « Merci de me recevoir. Je vais vous montrer une chose précise : comment la paie d'un de vos clients peut se calculer sans que vous ressaisissiez les heures. Si ça ne vous intéresse pas au bout de dix minutes, on arrête et je ne vous dérange plus. »

*Objectif : obtenir la permission de montrer, pas de vendre.*

**5-15 min — Découverte (on écoute, on ne pitche pas)**

Quatre questions, dans cet ordre :

1. « Combien de dossiers de paie traitez-vous aujourd'hui ? »
2. « Comment récupérez-vous les heures de vos clients ? » *(attendre : carnet, WhatsApp, Excel)*
3. « Combien de temps vous prend un dossier de paie par mois, tout compris ? »
4. « Qu'est-ce qui vous coûte le plus : la saisie, les corrections, ou les questions des clients ? »

*Objectif : faire dire au cabinet, avec ses mots, la douleur que le produit supprime. Ne pas l'aider.*

**15-30 min — Démonstration (15 minutes, pas une de plus)**

| Min | Ce qu'on montre | Ce qu'on dit |
|---|---|---|
| 15-19 | Création d'un employé, puis le même employé dans paie, pointage, congés | « Saisi une fois, utilisé partout. C'est ça qui supprime la ressaisie. » |
| 19-24 | Pointage mobile, puis borne ; coupure réseau simulée | « L'heure est prouvée, même sans réseau. » |
| 24-29 | Simulation de paie DZ, bulletin PDF, verrouillage | « IRG, CNAS, cotisations. Vous validez, le run se verrouille. » |
| 29-30 | Le catalogue, en 30 secondes, sans insister | « Et il y a d'autres modules, mais ce n'est pas le sujet aujourd'hui. » |

**30-38 min — Le modèle économique**

> « Voilà comment ça marche pour vous. Vous facturez le service à votre client. Nous vous facturons un tarif partenaire. La différence, c'est votre marge. Sur 25 dossiers, ça fait 300 € par mois, 3 600 € par an — sans embaucher. »

*Montrer le tableau §3.2. Laisser le silence après le chiffre.*

**38-43 min — Objections** (voir §5)

**43-45 min — Closing**

> « Ce que je vous propose : on prend **un** dossier, celui d'un client qui vous pose le plus de problèmes de pointage. On le met en route ensemble, on valide le premier run de paie avec vous. Si ça ne vous fait pas gagner du temps, on s'arrête là. Si ça marche, on regarde le deuxième. »

*Demander une date, pas un « oui ». Un « oui » sans date n'est pas un oui.*

### 4.3 Après le rendez-vous — dans les 24 heures

- [ ] Envoyer le récapitulatif : le dossier choisi, la date de mise en route, le contact technique.
- [ ] Envoyer la grille de marge (§3.2) en PDF.
- [ ] Créer la ligne dans `../REGISTRE_CANAUX.md`.
- [ ] Planifier la session de formation 1 (paie) sous 7 jours.

---

## 5. Objections du cabinet — et les réponses

| Objection | Ce qu'elle cache | Réponse |
|---|---|---|
| **« On a déjà un logiciel de paie. »** | Peur de changer un outil qui marche | « On ne remplace pas votre logiciel de paie. On remplace la ressaisie des heures qui l'alimente. Si votre logiciel actuel vous convient, on peut même n'utiliser que le pointage. » |
| **« Mes clients ne voudront pas. »** | Peur de perdre un client | « On commence par le client qui vous pose le plus de problèmes de pointage. C'est lui qui a intérêt à changer, pas les autres. » |
| **« C'est encore un outil à apprendre. »** | Peur du temps de formation | « Deux sessions, une sur la paie, une sur le pointage. Et le premier run, on le fait avec vous. » |
| **« Et la conformité ? »** | Peur de l'erreur qui engage sa responsabilité | « Le moteur applique les règles DZ. Les référentiels sont en statut pilote — c'est justement pour ça qu'on valide le premier run avec vous, pas tout seul. » |
| **« Combien ça me rapporte vraiment ? »** | Calcul non fait | Montrer §3.2 : 25 dossiers = 300 €/mois, 3 600 €/an. Puis : « et vous facturez la mise en place en plus, ça reste chez vous. » |
| **« Pourquoi pas Odoo / un grand éditeur ? »** | Peur de l'inconnu | « Odoo n'a pas de paie algérienne officielle : il faut la faire développer, et c'est vous qui portez le risque. Ici, la paie DZ est dans l'outil. » |
| **« Vous êtes une petite structure. »** | Peur de l'abandon | « C'est vrai, et c'est pour ça que vous êtes deux cabinets, pas deux cents. Vous avez un interlocuteur direct, et le produit est en open source sous licence MIT : si nous disparaissions, le code reste. » |
| **« Laissez-moi en parler à mon associé. »** | Vrai besoin de temps, ou refus poli | « Bien sûr. Je vous propose qu'on fixe déjà la date du dossier pilote : si votre associé dit non, on l'annule, ça ne coûte rien. » |

---

## 6. Onboarding d'un dossier client — checklist

À dérouler avec le cabinet, sur le premier dossier. Objectif : **premier run de paie validé en 15 jours**.

### Semaine 1 — Cadrage et données

- [ ] Dossier client créé dans l'espace cabinet.
- [ ] Liste des employés fournie par le client (nom, poste, contrat, salaire de base).
- [ ] Import ou saisie des employés ; vérification des contrats et horaires.
- [ ] Choix du mode de pointage : mobile, borne, ou les deux.
- [ ] Paramétrage des règles de paie du dossier (IRG, CNAS, primes, retenues).

### Semaine 2 — Pointage et premier run

- [ ] Formation des équipes du client au pointage (30 minutes sur site ou visio).
- [ ] Une semaine de pointage réel enregistrée.
- [ ] **Simulation de paie** lancée, sans validation.
- [ ] Relecture du bulletin par le cabinet, ligne par ligne.
- [ ] Corrections appliquées, simulation relancée.

### Semaine 3 — Validation

- [ ] Run validé par le cabinet.
- [ ] Run verrouillé.
- [ ] Bulletins PDF générés et transmis au client.
- [ ] **Compte rendu de validation du référentiel** : ce qui a été vérifié, ce qui reste à confirmer par un conseil fiscal.
- [ ] Décision : deuxième dossier, ou arrêt.

### Critère de succès du pilote

Le pilote est réussi si **les trois** conditions sont réunies :

1. Le cabinet a validé un run de paie réel sans ressaisie manuelle des heures.
2. Le temps passé sur ce dossier a diminué par rapport à la méthode précédente (mesuré, pas estimé).
3. Le cabinet accepte de prendre un deuxième dossier.

Si l'une des trois manque, on ne pousse pas le volume : on corrige d'abord.

---

## 7. Ce qu'il ne faut jamais dire

| Interdit | Pourquoi |
|---|---|
| « Conformité garantie / validée » | Référentiels en statut `pilot` |
| « On a X cabinets partenaires » | Aucun signé à ce jour |
| « Ça remplace votre logiciel de paie » | Faux, et ça déclenche la peur de changer |
| « C'est simple, tout est automatique » | La paie n'est jamais entièrement automatique ; c'est le cabinet qui valide |
| Un prix en dinars non validé | Le prix DZD se fixe avec le premier pilote |
| « 41 modules » en ouverture | Le cabinet achète la paie, pas un catalogue |

---

## 8. Résumé en une page (à imprimer)

> **Leopardo pour les cabinets comptables**
>
> **Le problème** : vous ressaisissez les heures de vos clients depuis un carnet ou un WhatsApp.
> **La solution** : les heures arrivent pointées (mobile, GPS, borne), la paie algérienne se calcule, vous validez, le run se verrouille.
> **Votre gain** : plus de dossiers sans embaucher, et une commission récurrente.
> **Le chiffre** : 25 dossiers = 300 €/mois de marge, 3 600 €/an — plus ce que vous facturez en mise en place.
> **L'engagement** : un dossier, 15 jours, premier run validé ensemble. Pas de volume imposé.
> **La limite assumée** : les référentiels de paie sont en statut pilote ; la validation se fait avec vous, sur le premier dossier.
