# Kit exécutable — Cadence sociale : LinkedIn/X + communautés locales (#7876)

> Distribution continue sur 4 semaines : LinkedIn (B2B n°1), X (dev/OSS), groupes
> Facebook/WhatsApp Maghreb & Afrique de l'Ouest. Cadre : `README.md` de ce dossier.
> **Jamais de publication automatisée non relue ; comptes fournis par le fondateur ;
> valeur d'abord, jamais de spam.**

## 0. Pré-vol

- [ ] Page LinkedIn entreprise + profil fondateur actifs (comptes fondateur).
- [ ] Compte X actif.
- [ ] Liens raccourcis avec UTM prêts :
      LinkedIn `?utm_source=linkedin&utm_medium=social&utm_campaign=growth-2026` ·
      X `?utm_source=x&utm_medium=social&utm_campaign=growth-2026` ·
      groupes `?utm_source=<groupe>&utm_medium=community&utm_campaign=growth-2026`.
- [ ] 1er carrousel LinkedIn dérivé d'une page `/alternatives` (PDF 5-7 slides) — dépend
      de la mise en ligne des pages alternatives (V1 de la stratégie).

## 1. Cadence hebdomadaire (structure fixe)

| Jour | Canal | Format |
|---|---|---|
| Lundi | LinkedIn page | Post produit / carrousel comparatif |
| Mardi | X | Thread technique (3-6 tweets) |
| Mercredi | Groupes FB/WhatsApp | Réponses aux questions réelles + veille (pas de post promo) |
| Jeudi | LinkedIn fondateur | Build-in-public FR |
| Vendredi (S2, S4) | Blog/dev.to | Contenu long + republication |

Règles de rédaction : pitch et wording de `MESSAGE.md` (« suite métier », jamais
« logiciel RH » comme catégorie) ; chiffres datés uniquement ; paie pays = « statut
pilote » systématique ; pas d'iOS ; FR sur LinkedIn/groupes, EN sur X.

## 2. Calendrier 4 semaines — posts rédigés (prêts-à-coller)

### Semaine 1 — thème « pointage terrain »

**Lun · LinkedIn page (FR)** :

> Vos équipes sont sur des chantiers, dans des camions, en station ou en salle — pas
> derrière un bureau. Pourquoi votre gestion, elle, vit encore dans Excel ?
>
> Leopardo est la suite métier des entreprises de terrain : RH & paie, pointage (QR,
> GPS, bornes biométriques), absences, CRM, comptabilité et opérations — web, mobile
> et bornes. Open source (MIT), auto-hébergeable, mode hors ligne.
>
> Essai 14 jours, sans carte bancaire → [lien UTM]
> #opensource #PME #Maghreb #AfriqueDeLOuest

**Mar · X thread (EN)** :

> 1/ Attendance for field teams is a hard problem: no desks, patchy connectivity,
> multiple sites. Here's how we handle it in Leopardo (MIT-licensed) 🧵
> 2/ Three capture modes: QR codes per site, GPS-fenced mobile check-in, and biometric
> kiosks via a ZKTeco bridge.
> 3/ The hard part is offline: kiosks keep working without network and sync back
> through an edge mode. Conflict resolution is the interesting bit.
> 4/ Everything lands in one multi-tenant backend (PostgreSQL isolation), feeding
> payroll prep for 21 countries (pilot status, measured 2026-09).
> 5/ Repo: github.com/kitokoh/leopardo-hr — feedback welcome, especially on offline
> sync. #opensource #selfhosted #buildinpublic

**Jeu · LinkedIn fondateur (FR)** :

> Pourquoi j'ai commencé Leopardo par l'Afrique francophone et pas par l'Europe.
>
> Parce que c'est là que le problème est le plus dur : paie locale (IRG/CNAS, CNSS…),
> connectivité instable, équipes 100 % terrain. Si la suite tient là, elle tient partout.
> Les règles de paie couvrent 21 pays — en statut pilote, et je préfère le dire que de
> promettre une « conformité validée » qu'on n'a pas encore.
>
> Build in public : le dépôt est ouvert, les chiffres sont datés. [lien GitHub]

### Semaine 2 — thème « alternative open source »

**Lun · LinkedIn page (FR)** — carrousel dérivé de `/alternatives/odoo` (ou à défaut post) :

> Odoo est une excellente suite — pour certains profils. Si vos équipes sont sur le
> terrain au Maghreb ou en Afrique de l'Ouest, les critères changent : paie locale,
> pointage biométrique, mode hors ligne, mobile d'abord.
>
> On a fait le comparatif honnête, y compris les cas où Odoo reste le bon choix →
> [lien /alternatives/odoo UTM]

**Mar · X thread (EN)** :

> 1/ We built a business suite as a modular monolith — 27 DDD modules (measured
> 2026-09-18) in one Laravel codebase. Here's why we didn't do microservices 🧵
> 2/ Our customers self-host on a single VPS. One docker-compose beats a k8s cluster
> they'd never operate.
> 3/ Module boundaries are enforced at the code level (DDD contexts), so we keep the
> option to split later. 798 OpenAPI endpoints (measured 2026-09-18) already define
> the contracts.
> 4/ Tradeoffs we accepted, and what broke along the way: [lien blog/dev.to]
> #opensource #softwarearchitecture

**Jeu · LinkedIn fondateur (FR)** :

> Chiffre de la semaine : 798 endpoints dans notre couche OpenAPI (mesuré le
> 2026-09-18, méthode dans le dépôt). Pas un chiffre marketing — un grep reproductible.
> C'est notre règle : aucun chiffre public sans date et méthode de mesure. Ça évite de
> se raconter des histoires, à nous d'abord. [lien]

**Ven · contenu long** : article blog « Self-host de Leopardo en 30 minutes avec Docker
Compose » → republication dev.to (canonical → vitrine) + résumés LinkedIn/X.

### Semaine 3 — thème « paie locale »

**Lun · LinkedIn page (FR)** :

> IRG, CNAS, CNSS, IPRES… chaque pays a sa paie, et vos tableurs le savent trop bien.
>
> Leopardo prépare la paie pour 21 pays (statut pilote, mesuré 2026-09) — dont
> l'Algérie, le Maroc, le Sénégal et la Côte d'Ivoire — avec exports contrôlés.
> Statut pilote = on le dit, et on itère avec nos utilisateurs pilotes.
>
> Guide complet de la paie en Algérie sur le blog → [lien UTM]

**Mar · X thread (EN)** :

> 1/ Payroll rules are the least sexy, most valuable code we write. 21 countries in
> pilot (measured 2026-09), starting with Algeria, Morocco, Senegal, Côte d'Ivoire 🧵
> 2/ Every rule is data + tests, not hardcoded logic. Adding a country = adding rule
> sets and fixtures.
> 3/ "Pilot status" means real payrolls run in parallel with the legacy process until
> outputs match. We publish that status instead of claiming "compliance".
> 4/ Repo: github.com/kitokoh/leopardo-hr

**Jeu · LinkedIn fondateur (FR)** :

> Ce que « statut pilote » veut dire chez nous : la paie tourne en double avec le
> processus existant du client jusqu'à ce que les résultats concordent. C'est plus lent
> que de vendre de la « conformité garantie ». C'est aussi la seule façon honnête de
> construire de la confiance sur un sujet où l'erreur se paie cash.

### Semaine 4 — thème « verticaux & self-host »

**Lun · LinkedIn page (FR)** :

> Une agence de voyage, un restaurant, une station-service et une école n'ont pas les
> mêmes journées. Leopardo active des packs verticaux par tenant : mêmes fondations
> (RH & paie, pointage, compta), écrans métier différents.
>
> Démo guidée, onboarding < 30 min → [lien UTM]

**Mar · X thread (EN)** :

> 1/ One backend, 7 Flutter apps + a shared core (measured 2026-09-18). How we keep
> them consistent without a mono-app 🧵
> 2/ leopardo_core owns auth, sync, offline storage and design system. Apps stay thin.
> 3/ Kiosk app is the stress test: runs on cheap Android hardware, offline-first,
> biometric bridge.
> 4/ Code: github.com/kitokoh/leopardo-hr #flutter #opensource

**Jeu · LinkedIn fondateur (FR)** :

> 4 semaines de cadence tenue. Ce qui a le mieux marché : [métrique datée]. Ce qui n'a
> pas marché : [honnête]. On ajuste et on continue — le calendrier du mois prochain se
> décide sur les chiffres UTM, pas sur l'intuition. [lien registre public si pertinent]

**Ven · contenu long** : « Guide de la paie au Sénégal » (cluster paie pays) + republication.

## 3. Groupes Facebook/WhatsApp — 10 cibles & règles d'engagement

> Les noms exacts varient : le fondateur confirme les 10 groupes réels au moment du
> rejoint. Typologie cible + règles ci-dessous. **Semaine type : mercredi = réponses
> uniquement.** Aucun lien nu, jamais deux mentions de Leopardo la même semaine dans
> un même groupe.

| # | Groupe (typologie) | Pays | Règle d'engagement spécifique |
|---|---|---|---|
| 1 | Entrepreneurs & PME Algérie | DZ | Lire les règles épinglées ; répondre aux questions paie/CNAS ; mention produit max 1×/sem si pertinente |
| 2 | RH & Paie Algérie | DZ | Valeur experte (IRG, CNAS) ; lien vers guides blog plutôt que produit |
| 3 | Comptables & experts-comptables DZ | DZ | Angle prescripteur : exports paie, contrôles ; pas de pitch direct |
| 4 | Entrepreneurs Maroc (PME/TPE) | MA | Questions CNSS/IR ; FR/darija léger ; produit seulement si on nous le demande |
| 5 | RH Maroc | MA | Partager le guide paie MA quand publié |
| 6 | Entrepreneurs Sénégal | SN | Questions IPRES/CSS ; valoriser offline (connectivité) |
| 7 | Comptables Afrique de l'Ouest (SN/CI) | SN/CI | Angle prescripteur, kit PDF quand disponible |
| 8 | Entrepreneurs Côte d'Ivoire | CI | Questions CNPS ; cas transport/distribution |
| 9 | Gérants transport/logistique Maghreb | DZ/MA | Cas d'usage pointage GPS/tournées — répondre en expérience, pas en pub |
| 10 | Gérants restauration/commerce | DZ/MA/SN | Cas d'usage planning/pointage multi-sites |

**Règles communes (toutes non négociables)** :
1. Lire et respecter les règles épinglées de chaque groupe avant tout message.
2. Ratio 5:1 minimum — cinq contributions de pure valeur pour une mention produit.
3. Toujours répondre à une question réelle ; jamais de post promo à froid.
4. Liens toujours en contexte, avec UTM `utm_medium=community`.
5. Compte du fondateur (ou membre d'équipe identifié) — jamais de faux profils.
6. WhatsApp : pas de démarchage privé non sollicité (illégal et toxique).
7. Consigner chaque mention dans le suivi §4.
8. Communauté WhatsApp « utilisateurs Leopardo » : à créer seulement à ≥ 20 tenants actifs.

## 4. Suivi de cadence (critère « 4 semaines consécutives »)

| Semaine | Lun LI page | Mar X | Mer groupes | Jeu LI fondateur | Ven long | Notes/UTM |
|---|---|---|---|---|---|---|
| S1 | ☐ | ☐ | ☐ | ☐ | — | |
| S2 | ☐ | ☐ | ☐ | ☐ | ☐ | |
| S3 | ☐ | ☐ | ☐ | ☐ | — | |
| S4 | ☐ | ☐ | ☐ | ☐ | ☐ | |

Bilan en fin de S4 dans `../rapports-mensuels/<AAAA-MM>.md` : impressions/clics par
canal (UTM), meilleurs posts, ajustement du calendrier suivant.

## 5. Définition de fait (critères d'acceptation #7876)

- [ ] 4 semaines consécutives tenues (tableau §4 complet).
- [ ] 10 groupes confirmés par le fondateur, règles d'engagement lues et notées (§3).
- [ ] Toute publication relue humainement avant envoi ; comptes fondateur.
- [ ] LinkedIn (page + fondateur) et X actifs, ajoutés au `../REGISTRE_CANAUX.md`.
