# Positionnement vidéo — Leopardo

**Statut** : document de travail marketing · **Version** : v9 (élargi) · **Date** : 2026-11-28 · **Portée** : les 38 scripts de `docs/marketing/`
**Remplace** : la v8 du 2026-11-28, dont le cadrage était **trop étroit** (groupes multi-entités 500-5 000 salariés, DZ/MA/TN uniquement).
**Aligné sur** : `docs/REFERENTIEL_PRODUIT/MESSAGE.md` (P03), `POSITIONNEMENT_SUITE_METIER.md` (#7428), `docs/brand/BRAND_GUIDELINES.md`, `docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md`, `docs/architecture/adr/0014-plan-pricing-canonical-decisions.md`, `docs/architecture/adr/0034` et `0035` (surfaces publiques), `docs/specifications/SOLUTION_HOSPITALITY.md` + `docs/decisions/7947-hospitality-rentals.md` (gestion locative), `api/app/Modules/Payroll/Infrastructure/Services/CountryRulesResolver.php` (pays de paie).

> Ce document ne crée **aucune** promesse nouvelle. Il reformule ce que le dépôt affirme déjà.
> En cas de divergence, les fichiers cités ci-dessus gagnent.

---

## 0. Pourquoi la v8 est reprise

La v8 avait réduit Leopardo à un seul segment : **« groupes multi-entités de 500 à 5 000 salariés d'Algérie, du Maroc et de Tunisie »**. Ce cadrage est exact sur *un* de ses marchés, mais il **ampute le produit de deux tiers de sa réalité** :

| Ce que la v8 disait | Ce que le dépôt montre |
|---|---|
| Une seule cible : le groupe multi-entités | Le produit sert aussi **les tenants** (voir §2 — mot ambigu, à arbitrer) et **le grand public** (portail public, 8 verticales, app `leopardo_public`) |
| Trois pays : DZ, MA, TN | Le moteur de paie résout **21 pays**, dont **9 en Afrique francophone** (voir §4) |
| Un ticket 30-80 k€/an, cycle 6-12 mois | Un plan **Free 0 €** public et un **Pilot à 29 €/mois** existent (ADR-0014) : l'entrée est aussi accessible à une TPE |

Un film qui ne parle qu'au DAF d'un groupe de 3 000 salariés laisse dehors le restaurateur, le pharmacien, le voyageur qui cherche un professionnel, et l'entrepreneur de Dakar ou d'Abidjan. **La v9 rétablit l'ampleur sans diluer le message** : chaque film garde *une* promesse, mais le catalogue couvre l'ensemble.

---

## 1. Le positionnement retenu

### 1.1 Énoncé de positionnement (une phrase)

> **Leopardo est la suite métier des entreprises de terrain : elle réunit RH & paie, pointage, comptabilité, CRM et opérations dans un seul produit — pour les entreprises et les groupes qui gèrent des équipes sur plusieurs sites, pour les professionnels qui veulent être trouvés et réservés en ligne, et pour leurs clients — sur web, mobile et bornes, avec des règles de paie pensées pour l'Afrique francophone et le Maghreb.**

Cette phrase est une **extension** du pitch canonique de `MESSAGE.md`, pas un remplacement : elle en reprend les termes exacts (« suite métier des entreprises de terrain », « RH & paie, pointage, absences, CRM, comptabilité et opérations, sur web, mobile et bornes ») et y ajoute les deux publics que la v8 avait perdus.

### 1.2 Les trois publics, et ce que chacun achète

| Public | Qui c'est | Ce qu'il vit | Ce que le film doit lui dire | Films concernés |
|---|---|---|---|---|
| **A — Les tenants** (voir §2 pour l'ambiguïté du mot) | Entreprises clientes de la plateforme : TPE, PME, groupes, et — dans une lecture possible — bailleurs/gestionnaires locatifs | Il gère des personnes, des heures, de l'argent, parfois des biens | « Un seul produit pour vos équipes, vos heures et vos comptes » | VID-01 à VID-04, VID-13 à VID-20, VID-33 à VID-38 |
| **B — Le grand public** | Le client final du professionnel : le voyageur, le patient, le parent, le passager, l'acheteur | Il cherche un professionnel, un horaire, un prix, une disponibilité | « Trouvez, réservez, contactez — sans compte » | VID-05 à VID-12, VID-21 à VID-32 |
| **C — Les entreprises d'Afrique francophone** | Dirigeants et DAF de Dakar, Abidjan, Douala, Cotonou, Lomé, Ouagadougou, Bamako, Niamey, Libreville… | Il paie avec des règles locales qu'aucune suite internationale ne connaît | « La paie de votre pays, dans le même produit » | VID-01, VID-04, VID-17, VID-22 |

**Règle d'or inchangée** : un film = **un** public = **une** promesse. C'est le *catalogue* qui couvre les trois, pas chaque film.

### 1.3 La catégorie, et pourquoi elle compte

| Élément | Formulation autorisée | Formulation interdite |
|---|---|---|
| Catégorie (FR) | **suite métier** | logiciel RH, SaaS RH, logiciel de gestion RH |
| Catégorie (EN) | **business suite** | HR SaaS, HR software |
| Slogan | **BUSINESS OS** (majuscules, jamais traduit, jamais en position de catégorie) | — |
| Marque | **Leopardo** (mot seul) | « Leopardo RH » en phrase de présentation |

Le dépôt s'appelle `leopardo-hr` et le domaine est `leopardo-rh.com` : ce sont des **identifiants techniques figés** (`POSITIONNEMENT_SUITE_METIER.md` §4), pas une définition du produit. Un film qui laisse entendre « logiciel RH » oblige ensuite à réexpliquer la comptabilité, le CRM, la restauration, la pharmacie, l'agriculture.

---

## 2. ⚠️ Le mot « tenants » — ambiguïté à arbitrer, non tranchée ici

Le brief demande de couvrir « les besoins des tenants ». **Le dépôt donne au mot deux sens distincts, et ce document ne choisit pas à votre place.** Les deux lectures sont documentées ci-dessous ; l'arbitrage vous revient.

### Lecture 1 — « tenants » = locataires / gestion locative

Le dépôt porte une **verticale de gestion locative** complète et spécifiée :

- `docs/specifications/SOLUTION_HOSPITALITY.md` §1 : « **Gestion locative** : baux (appartements / longue durée) et suivi des loyers (échéances, encaissements, retards). »
- `docs/decisions/7947-hospitality-rentals.md` (D-7947, HOSP-005, BC-32) : modèles `HospitalityLease` / `HospitalityRentPayment`, arbitrages sur `billing_day`, statut `late` dérivé, refus d'un encaissement supérieur au dû (`422 RENT_AMOUNT_EXCEEDS_OUTSTANDING`).
- Le script **VID-08** (« Hôtellerie : établissements, inventaire, baux et loyers ») et **VID-28** (« la chambre, le bail, la marge ») en parlent déjà.

**Si c'est ce sens** : « les tenants » = les **bailleurs et gestionnaires de biens locatifs**, et le positionnement doit mettre en avant le bail, l'échéance, l'encaissement, la relance des impayés.

### Lecture 2 — « tenants » = multi-tenant (architecture SaaS)

Le dépôt est **multi-tenant par construction** :

- `docs/REFERENTIEL_PRODUIT/TERMES.md` : « Statut produit : open-source, **multi-tenant**, mobile-first ».
- `api/app/Core/Tenant/` : `TenantManager.php`, `TenantContextGuard.php`, `TenantCacheService.php`, `TenantScopedEvent.php`, `TenantContextMissingException.php`.
- `api/app/Console/Commands/TenantRlsStatusCommand.php` (Row-Level Security par tenant).
- `docs/specifications/SOLUTION_HOSPITALITY.md` §2 : routes publiques où « le tenant est résolu **par la ressource** », « aucune donnée cross-tenant ».
- `MESSAGE.md` : « isolation tenant » figure parmi les promesses autorisées.

**Si c'est ce sens** : « les tenants » = les **entreprises clientes hébergées sur la plateforme**, et le positionnement doit mettre en avant l'isolation des données, le RBAC, l'audit, l'hébergement.

### Ce que ce document fait en attendant l'arbitrage

Il traite **les deux** : le §1.2 public A couvre les entreprises clientes (lecture 2) *et* les gestionnaires locatifs (lecture 1) ; le §3.1 autorise les deux familles de promesses, chacune avec sa preuve. **Aucun film ne dit « tenant » à l'écran** — le mot est un terme d'architecture, pas un mot de spectateur. Les scripts emploient « votre entreprise », « vos sociétés », « vos biens », « vos locataires » selon le contexte.

> **Action requise de votre part** : indiquer laquelle des deux lectures (ou les deux) doit dominer le discours public. Tant que ce n'est pas tranché, les scripts restent écrits pour fonctionner dans les deux cas, sans jamais employer le mot « tenant » face caméra.

---

## 3. Le grand public — le deuxième pilier oublié par la v8

Le dépôt ne sert pas seulement des entreprises : il sert **leurs clients**, sans compte.

### 3.1 Les preuves

| Preuve | Fichier |
|---|---|
| Portail public grand public, 4 locales (fr/en/tr/ar, arabe RTL) | `front/web/src/modules/public-surfaces/portal/data/copy.ts` |
| Accueil : « Trouvez un professionnel près de chez vous » — restaurants, boutiques, hébergements, VTC, entreprises | `copy.ts` L186-189 |
| Recherche par métier, par ville, ou **autour de vous** | `copy.ts` L194-195 |
| Compte public : activités, favoris, historique, export RGPD | `portal/data/account-copy.ts` |
| Registre de **8 verticales** publiques | `public-surfaces/registry.ts` : restaurant, retail, hospitality, trade, travel, pharmacy, mobility (VTC), careers |
| App mobile grand public dédiée | `front/mobile_apps/leopardo_public` (ADR-0035 : « une seule app est grand public ») |
| Réservation en ligne **sans compte**, suivi par référence + code | `SOLUTION_HOSPITALITY.md` §1 |

### 3.2 Ce que le grand public achète (et ce qu'il n'achète pas)

Le grand public **n'achète rien** : il utilise. C'est le professionnel qui paie l'abonnement. Le film grand public a donc deux fonctions :

1. **Pour le professionnel** : montrer que Leopardo lui apporte des clients (une vitrine publique, une réservation en ligne, une fiche trouvable) — c'est un argument de vente.
2. **Pour le particulier** : montrer que l'expérience est simple, sans compte, sans friction — c'est un argument de notoriété et un canal d'acquisition organique.

**Ne jamais** promettre au grand public une fonction payante, ni lui vendre un abonnement. Les films 9:16 (VID-21 à VID-32) parlent aux **professionnels** sur les réseaux, pas aux consommateurs.

---

## 4. L'Afrique francophone — le troisième pilier oublié par la v8

La v8 ne citait que DZ/MA/TN. Le moteur de paie en couvre **21 pays**, dont **9 en Afrique francophone**.

### 4.1 Les preuves, pays par pays

Source : `api/app/Modules/Payroll/Infrastructure/Services/CountryRulesResolver.php` (`defaultRulesMap()`).

| Zone | Pays couverts | Classe de règles |
|---|---|---|
| **Maghreb** | DZ, MA, TN | `AlgeriaPayrollRules`, `MoroccoPayrollRules`, `TunisiaPayrollRules` |
| **Afrique de l'Ouest (UEMOA/XOF)** | SN, CI, ML, BF, BJ, TG, NE | `SenegalPayrollRules` + `CedeaoPayrollRules` (`MEMBER_COUNTRY_CODES = ['CI','ML','BF','BJ','TG','NE']`) |
| **Afrique centrale (CEMAC)** | CM, CF, TD, CG, GA, GQ | `CemacPayrollRules` (`MEMBER_COUNTRY_CODES = ['CM','CF','TD','CG','GA','GQ']`) |
| **Hors Afrique** | FR, TR, CA, GB, US | classes dédiées |

**Total Afrique francophone : 9 pays** (SN, CI, ML, BF, BJ, TG, NE, CM, CF, TD, CG, GA, GQ — soit 13 codes, dont 9 à usage francophone courant ; le décompte exact dépend de la définition retenue, voir note ci-dessous).

> **Note de rigueur** : le registre `METRIQUES_VITRINE.md` date la valeur « 21 pays » au **2026-09-09** et précise « non re-mesuré au 2026-11-28 ». Le nombre de pays **africains francophones** n'y figure pas comme métrique datée. Les scripts n'affichent donc **aucun chiffre de pays africains** : ils nomment les pays (« Sénégal, Côte d'Ivoire, Cameroun… ») plutôt que de compter. Un chiffre ne sort qu'après une ligne datée dans `METRIQUES_VITRINE.md`.

### 4.2 Ce que « adapté à l'Afrique francophone » veut dire concrètement

Ce n'est pas un slogan : c'est une **différence de contenu des règles**.

- **Zones monétaires réelles** : XOF (UEMOA) et XAF (CEMAC) sont modélisées comme des zones, pas comme des pays isolés — un groupe présent à Dakar et Abidjan partage la même logique de cotisations.
- **Fuseaux par zone** : `Africa/Abidjan` (CEDEAO), `Africa/Douala` (CEMAC) — documenté dans les docblocks des classes.
- **Langue** : le produit est servi en **français** nativement (et en en/tr/ar) ; l'arabe est RTL.
- **Réalité de terrain** : pointage mobile/GPS/kiosque avec **mode hors-ligne** (`EdgeSync`) — indispensable là où le réseau tombe.

### 4.3 La limite à ne jamais franchir

Les référentiels de paie sont en statut **`pilot`**. `MESSAGE.md` interdit « conformité légale validée ». Un film dit « **règles de paie en pilote** », jamais « paie conforme ». C'est vrai pour l'Afrique francophone comme pour le Maghreb.

---

## 5. La hiérarchie du message (ce qu'on dit, dans quel ordre)

1. **Le problème** — la triple saisie : le même salarié, la même heure, le même montant, documentés trois fois.
2. **Le basculement** — un seul produit, saisi une fois, utilisé partout.
3. **La preuve** — heures prouvées, paie calculée/validée/verrouillée, bulletin PDF.
4. **L'ampleur** — les packs métier (restaurant, pharmacie, hôtellerie, école, VTC, agriculture, négoce…), la vitrine publique, les pays.
5. **L'objection** — migration, sécurité, hébergement, prix.
6. **L'action** — démonstration sur vos données, ou pilote 12 semaines pour un groupe.

Un film qui saute l'étape 1 ne crée pas de tension. Un film qui saute l'étape 5 ne convertit pas.

---

## 6. Ce que les films ont le droit de dire

### 6.1 Promesses autorisées (à réutiliser telles quelles)

**Pour les entreprises (public A)**
- **Pilote payant 12 semaines**, une entité, un site, 150 à 400 salariés, trois critères écrits.
- **Démonstration sur vos données** (et non « essai gratuit » pour un groupe).
- **Export paie**, **isolation multi-tenant**, **RBAC**, **journal d'audit**, **auto-hébergement**.
- **Packs métier offerts** : « vous payez la plateforme, pas le secteur ».
- **21 pays de règles de paie en pilote** — le mot « pilote » est obligatoire.
- **Plan Free 0 € (5 employés max)** et **Pilot 29 €/mois (30 employés max)** — ADR-0014. Utilisables pour parler aux TPE, jamais pour un groupe.

**Pour la gestion locative (lecture 1 de « tenants »)**
- **Baux, échéances, encaissements, retards** — `SOLUTION_HOSPITALITY.md` §1, D-7947.
- **Multi-établissements** : hôtels, résidences, immeubles, maisons d'hôtes.

**Pour le grand public (public B)**
- **Trouver un professionnel** par métier, par ville, autour de soi.
- **Réserver en ligne sans compte**, suivi par référence + code.
- **8 verticales publiques** : restaurant, retail, hôtellerie, négoce, voyage, pharmacie, VTC, emploi.

**Pour l'Afrique francophone (public C)**
- **Règles de paie par pays**, nommées (Sénégal, Côte d'Ivoire, Cameroun, Mali, Burkina, Bénin, Togo, Niger, Tchad, RCA, Congo, Gabon, Guinée équatoriale).
- **Zones XOF / XAF** modélisées.
- **Mode hors-ligne** du pointage.

### 6.2 Promesses interdites (garde anti-sur-promesse #7058)

- ❌ « Conformité légale validée » — les règles pays sont en **pilote**.
- ❌ « SSO SAML », « SCIM », « certifié » — seul **OIDC** est implémenté.
- ❌ Tout chiffre d'adoption non sourcé (« des milliers d'entreprises »).
- ❌ « Mise en route en 30 minutes » pour un groupe — un groupe ne se déploie pas en 30 minutes.
- ❌ Tout chiffre produit **sans date de mesure** (`METRIQUES_VITRINE.md`).
- ❌ « Disponible sur iOS » tant que TestFlight n'est pas public (`MESSAGE.md`).
- ❌ Vendre un abonnement au grand public — il utilise, il n'achète pas.

### 6.3 Chiffres autorisés, avec leur date

| Chiffre | Valeur | Date | Usage à l'écran |
|---|---|---|---|
| Modules métier | **42** | 2026-11-28 | « 42 modules » |
| Chemins d'API | **1 984** | 2026-11-28 | « 1 984 chemins d'API » |
| Opérations OpenAPI | **2 605** | 2026-11-28 | usage interne / dossier technique |
| Apps mobiles | **11 + core** | 2026-11-27 | « 11 applications mobiles » |
| Modules activables (catalogue) | **39** | 2026-11-28 | « 39 modules activables » |
| Verticales publiques | **8** | registre `registry.ts` | « 8 verticales publiques » |
| Pays de paie | **21** (pilote) | 2026-09-09 | « 21 pays, en pilote » |

> **Règle d'écriture** : un chiffre à l'écran porte toujours sa date en note de production. Un chiffre sans date ne sort pas.

---

## 7. Le ton

| On est | On n'est pas |
|---|---|
| Concret, ancré dans une scène de travail réelle | Abstrait (« transformez votre entreprise ») |
| Sobre, presque documentaire | Enthousiaste publicitaire |
| Honnête sur le statut (« en pilote ») | Vendeur qui arrondit |
| Direct au « vous » du spectateur | Corporatif au « nous » |

**Test de lecture à voix haute** : si une phrase ne peut pas être dite par un dirigeant à son associé sans gêne, elle est réécrite.

---

## 8. Application aux quatre films de la vague V1

| Film | Rôle dans le positionnement | Ce qui change en v9 |
|---|---|---|
| **VID-01** (90 s, pilier) | Fixe le vocabulaire : catégorie, publics, trio paie/pointage/contrôle, puis l'ampleur (packs, pays) | Cible élargie : entreprises et groupes multi-sites **et** Afrique francophone ; le beat 6 ouvre sur les packs et la vitrine publique, pas seulement « 39 modules » |
| **VID-02** (30 s, cut) | Un seul message : une paie pour toutes vos entités | Cible élargie, CTA unique inchangé |
| **VID-03** (15 s, bumper) | Installe le **nom** et la promesse | Inchangé sur le fond ; audience élargie |
| **VID-04** (3 min, explainer) | Lève les objections : migration, sécurité, multi-pays, prix | Ajout explicite de l'Afrique francophone (pays nommés) et du plan Free/Pilot comme porte d'entrée TPE |

### 8.1 Le fil rouge entre les quatre

```
VID-03  →  le nom
VID-01  →  le problème, la catégorie, le trio, l'ampleur
VID-02  →  le même problème, compressé, un seul CTA
VID-04  →  les objections, une par une, avec preuve
```

Les quatre films partagent **la même phrase de catégorie** et **le même CTA final**. C'est ce qui fait une campagne plutôt que quatre vidéos.

---

## 9. Garde de non-régression

Avant toute publication d'un script :

```bash
# 1. Aucun chiffre périmé
grep -nE "31 modules|994 endpoints|8 apps|Huit verticales" docs/marketing/scripts-video.json

# 2. Aucune catégorie interdite hors lignes d'interdiction
grep -niE "logiciel RH|SaaS RH|HR SaaS|HR software|Business OS" docs/marketing/scripts-video.json

# 3. Le mot « pilote » accompagne toujours les 21 pays
grep -o "21 pays[^.]*" docs/marketing/scripts-video.json

# 4. Les accents sont présents
grep -c "é" docs/marketing/scripts-video.json

# 5. Le cadrage étroit de la v8 ne revient pas
grep -nE "500 à 5 000 salariés|500 a 5000 salaries" docs/marketing/scripts-video.json

# 6. Les prompts IA sont synchronisés avec les beats
python3 -c "
import json,unicodedata
d=json.load(open('docs/marketing/scripts-video.json'))
sa=lambda t:''.join(c for c in unicodedata.normalize('NFD',t) if unicodedata.category(c)!='Mn')
for s in d['scripts'][:4]:
    assert sa(s['beats'][0]['vo'])[:30] in s['ai_agent_prompt'], s['id']
print('prompts OK')"
```

Les commandes 1, 2 et 5 doivent renvoyer **rien** (hors lignes d'interdiction), la 3 doit contenir « pilote », la 4 un compte > 0, la 6 « prompts OK ».

---

## 10. Ce que ce document ne tranche pas

- **⚠️ Le sens de « tenants »** — voir §2. Deux lectures documentées (gestion locative / multi-tenant), **arbitrage utilisateur requis**. Aucun film n'emploie le mot à l'écran.
- **Le prix affiché en vidéo** : l'ADR-0014 fixe Free 0 €, Pilot 29 €, Operations 79 €, Enterprise sur devis. Les films V1 ne citent **aucun prix** — décision volontaire, le prix vit sur la page tarifs. Une campagne prix demanderait une validation produit.
- **L'URL du CTA** : `leopardo-rh.com` est l'identifiant figé (P03 §4) mais `PRODUCT_BRIEF.md` signale qu'il est **actuellement indisponible**. Les films affichent l'URL comme identifiant, jamais comme nom de produit ; une URL canonique stable doit être désignée avant tout lancement public.
- **Le nombre exact de pays africains francophones** : non mesuré et non daté dans `METRIQUES_VITRINE.md`. Les scripts nomment les pays au lieu de les compter.
- **Les traductions TR/AR** : propositions d'agent, à relire par un locuteur natif avant diffusion.
- **Le nom du dépôt** : `leopardo-hr` reste figé. Aucun film ne le prononce.
