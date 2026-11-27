# Positionnement vidéo — Leopardo

**Statut** : document de travail marketing · **Date** : 2026-11-28 · **Portée** : les 38 scripts de `docs/marketing/`
**Aligné sur** : `docs/GOTO_MARKET/REPOSITIONNEMENT_2026.md` (v2, grands comptes, #10024), `docs/REFERENTIEL_PRODUIT/MESSAGE.md` (P03), `POSITIONNEMENT_SUITE_METIER.md` (#7428), `docs/brand/BRAND_GUIDELINES.md`, `docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md`, `docs/architecture/adr/0014-plan-pricing-canonical-decisions.md`.

> Ce document ne crée **aucune** promesse nouvelle. Il reformule ce que le dépôt affirme déjà et corrige
> les endroits où les scripts vidéo s'en écartaient. En cas de divergence, les fichiers cités ci-dessus gagnent.

---

## 1. Le problème de positionnement constaté

L'audit des scripts a relevé **six écarts** entre ce que les films racontent et ce que le dépôt dit du produit.

| # | Écart | Preuve dans le dépôt | Effet |
|---|---|---|---|
| 1 | **Catégorie flottante** : « Business OS » employé comme catégorie | `MESSAGE.md` impose « suite métier » ; « BUSINESS OS » n'est qu'un **slogan** (`BRAND_GUIDELINES.md` §3) ; `REPOSITIONNEMENT_2026.md` l'interdit explicitement | Le spectateur ne sait pas dans quelle catégorie ranger le produit |
| 2 | **Cible périmée** : « Grand public, dirigeants de PME de terrain » dans les prompts IA de VID-01 | `REPOSITIONNEMENT_2026.md` v2 : cible **groupe multi-entités 500-5 000 salariés** ; la PME est explicitement abandonnée | Un film qui parle à tout le monde ne convainc personne |
| 3 | **Prompts désynchronisés** : les `ai_agent_prompt` de VID-01/02/03 décrivaient encore l'ancien script PME | Les champs `beats` avaient été réécrits en v7, pas les prompts | Le tournage aurait suivi le mauvais script |
| 4 | **Chiffres périmés** : « 994 endpoints » | Mesure du 2026-11-28 : **1 984 chemins** dans `api/openapi.yaml` | Chiffre faux = crédibilité perdue |
| 5 | **Comptage de verticales faux** : « Huit verticales » | Le dépôt porte bien plus de packs (restaurant, voyage, pharmacie, hôtellerie, santé, école, carburant, VTC, agriculture, négoce…) | Un chiffre trop bas minimise le produit |
| 6 | **Aucun accent** dans tout le JSON | `scripts-video.json` ne contenait pas un seul `é` | La voix off se lit « equipes », « metier » : défaut de prononciation en tournage |

Deux points **ne sont pas** des défauts et ont été conservés :

- **« Packs métier offerts »** est une promesse réelle : la vitrine a une entrée « Packs offerts »
  (`front/web/src/modules/vitrine/lib/vitrine-locale.ts`). On la garde, mais on la rend **précise**
  (« vous payez la plateforme, pas le secteur »).
- **« 21 pays de règles de paie en pilote »** est exact **à condition de garder « en pilote »** : `MESSAGE.md`
  interdit de présenter la conformité pays comme validée.

---

## 2. Le positionnement retenu

### 2.1 Énoncé de positionnement (une phrase)

> **Pour les groupes multi-entités de 500 à 5 000 salariés d'Algérie, du Maroc et de Tunisie, Leopardo est la
> suite métier qui réunit la paie de toutes les entités dans un seul moteur et prouve les heures de chaque
> site — parce qu'aucune suite internationale ne fait la paie DZ/MA/TN et qu'aucun ERP local ne fait ni
> pointage ni multi-pays.**

### 2.2 La catégorie, et pourquoi elle compte

| Élément | Formulation autorisée | Formulation interdite |
|---|---|---|
| Catégorie (FR) | **suite métier** | logiciel RH, SaaS RH, logiciel de gestion RH |
| Catégorie (EN) | **business suite** | HR SaaS, HR software |
| Slogan | **BUSINESS OS** (majuscules, jamais traduit, jamais en position de catégorie) | — |
| Marque | **Leopardo** (mot seul) | « Leopardo RH » en phrase de présentation |

**Pourquoi c'est le cœur du problème.** Le dépôt s'appelle `leopardo-hr` et le domaine est `leopardo-rh.com` :
ce sont des **identifiants techniques figés**, pas une définition du produit (`POSITIONNEMENT_SUITE_METIER.md` §4).
Un film qui laisse entendre « logiciel RH » oblige ensuite à réexpliquer tout le reste — comptabilité, CRM,
restaurant, pharmacie, agriculture. La catégorie « suite métier » **ouvre** la conversation au lieu de la fermer.

### 2.3 La cible, en clair

| Segment | Taille | Ce qu'il vit | Ce que le film doit lui dire |
|---|---|---|---|
| **DAF de groupe** (entrée) | 5-12 entités | Il consolide six paies sur un tableur | « Une paie par entité, un total groupe » |
| **DSI de groupe** | 500-5 000 salariés | Il doit prouver sécurité et intégration | « RBAC, audit, hébergement chez vous » |
| **DRH groupe** | multi-sites | Chaque site a sa badgeuse | « Chaque heure est prouvée » |

**Ce que la cible n'est pas** : le grand public, ni la PME de 5 à 150 salariés. Un particulier n'achète pas un
pointage biométrique ; une PME ne finance pas un cycle de vente (`REPOSITIONNEMENT_2026.md` §1).

**Règle d'entrée** : on entre par le **DAF**, jamais par la DRH. Dans un groupe, la DRH gère les personnes ;
le DAF paie et subit les erreurs.

### 2.4 La différenciation, en trois preuves

Chaque pilier est adossé à un fait du dépôt, pas à un adjectif.

| Pilier | Formulation pour l'écran | Preuve dans le dépôt |
|---|---|---|
| **Paie multi-entités** | « Une paie par société, un total groupe. » | Moteur multi-pays, runs DZ/MA vérifiés |
| **Preuve des heures** | « Chaque heure est prouvée. » | Pointage mobile/GPS/kiosque, `EdgeSync` hors-ligne |
| **Contrôle & conformité** | « Contrôle, auditable, hébergé chez vous. » | RBAC, journal d'audit, auto-hébergement MIT |

### 2.5 La hiérarchie du message (ce qu'on dit, dans quel ordre)

1. **Le problème** — six paies, aucun total. (reconnaissance immédiate)
2. **Le basculement** — un seul moteur, une paie par entité. (soulagement)
3. **La preuve** — heures prouvées, contrôle, audit. (crédibilité)
4. **L'objection** — migration, sécurité, hébergement. (levée de frein)
5. **L'action** — pilote payant 12 semaines. (conversion)

Un film qui saute l'étape 1 ne crée pas de tension. Un film qui saute l'étape 4 ne convertit pas.

**Règle de production** : une vidéo = **une seule** promesse. Si le script a besoin de deux phrases pour dire
ce qu'elle vend, elle est mal découpée.

---

## 3. Ce que les films ont le droit de dire

### 3.1 Promesses autorisées (à réutiliser telles quelles)

- **Pilote payant 12 semaines**, une entité, un site, 150 à 400 salariés, trois critères écrits.
- **Démonstration sur vos données** (et non « essai gratuit » pour un groupe).
- **Export paie**, **isolation multi-tenant**, **RBAC**, **journal d'audit**, **auto-hébergement**.
- **Packs métier offerts** : « vous payez la plateforme, pas le secteur ».
- **21 pays de règles de paie en pilote** — le mot « pilote » est obligatoire.

### 3.2 Promesses interdites (garde anti-sur-promesse #7058)

- ❌ « Conformité légale validée » — les règles pays sont en **pilote**.
- ❌ « SSO SAML », « SCIM », « certifié » — seul **OIDC** est implémenté.
- ❌ Tout chiffre d'adoption non sourcé (« des milliers d'entreprises »).
- ❌ « Mise en route en 30 minutes » pour un groupe — un groupe ne se déploie pas en 30 minutes.
- ❌ Tout chiffre produit **sans date de mesure** (`METRIQUES_VITRINE.md`).

### 3.3 Chiffres autorisés, avec leur date

| Chiffre | Valeur | Date | Usage à l'écran |
|---|---|---|---|
| Modules métier | **42** | 2026-11-28 | « 42 modules » |
| Chemins d'API | **1 984** | 2026-11-28 | « 1 984 chemins d'API » |
| Apps mobiles | **11 + core** | 2026-11-27 | « 11 applications mobiles » |
| Modules activables (catalogue) | **39** | 2026-11-28 | « 39 modules activables » |
| Pays de paie | **21** (pilote) | 2026-09-09 | « 21 pays, en pilote » |

> **Règle d'écriture** : un chiffre à l'écran porte toujours sa date en note de production. Un chiffre sans date
> ne sort pas.

---

## 4. Le ton

| On est | On n'est pas |
|---|---|
| Concret, ancré dans une scène de travail réelle | Abstrait (« transformez votre entreprise ») |
| Sobre, presque documentaire | Enthousiaste publicitaire |
| Honnête sur le statut (« en pilote ») | Vendeur qui arrondit |
| Direct au « vous » du DAF | Corporatif au « nous » |

**Test de lecture à voix haute** : si une phrase ne peut pas être dite par un DAF à son DG sans gêne, elle est
réécrite.

---

## 5. Application aux quatre films de la vague V1

| Film | Rôle dans le positionnement | Ce qui a changé |
|---|---|---|
| **VID-01** (90 s, pilier) | Fixe le vocabulaire : catégorie, cible, trio paie/pointage/contrôle | Prompt IA régénéré (il décrivait encore la cible PME) ; accents rétablis ; « huit verticales » → « packs métier activables » |
| **VID-02** (30 s, cut) | Un seul message : une paie pour toutes les entités | Prompt IA régénéré depuis le script v7 ; CTA unique |
| **VID-03** (15 s, bumper) | Installe le **nom** et la promesse | Prompt IA régénéré ; marque avant la seconde 10 |
| **VID-04** (3 min, explainer) | Lève les objections : migration, sécurité, multi-pays | « 994 endpoints » → « 1 984 chemins d'API » ; accents rétablis |

### 5.1 Le fil rouge entre les quatre

```
VID-03  →  le nom
VID-01  →  le problème, la catégorie, le trio
VID-02  →  le même problème, compressé, un seul CTA
VID-04  →  les objections, une par une, avec preuve
```

Les quatre films partagent **la même phrase de catégorie** et **le même CTA final**. C'est ce qui fait une
campagne plutôt que quatre vidéos.

---

## 6. Garde de non-régression

Avant toute publication d'un script, vérifier :

```bash
# 1. Aucun chiffre périmé
grep -nE "31 modules|994 endpoints|8 apps|Huit verticales" docs/marketing/scripts-video.json

# 2. Aucune catégorie interdite hors lignes d'interdiction
grep -niE "logiciel RH|SaaS RH|HR SaaS|HR software|Business OS" docs/marketing/scripts-video.json

# 3. Le mot « pilote » accompagne toujours les 21 pays
grep -o "21 pays[^.]*" docs/marketing/scripts-video.json

# 4. Les accents sont présents
grep -c "é" docs/marketing/scripts-video.json

# 5. Les prompts IA sont synchronisés avec les beats
python3 -c "
import json,unicodedata
d=json.load(open('docs/marketing/scripts-video.json'))
sa=lambda t:''.join(c for c in unicodedata.normalize('NFD',t) if unicodedata.category(c)!='Mn')
for s in d['scripts'][:4]:
    assert sa(s['beats'][0]['vo'])[:30] in s['ai_agent_prompt'], s['id']
print('prompts OK')"
```

Les commandes 1 et 2 doivent renvoyer **rien** (hors lignes d'interdiction), la 3 doit contenir « pilote »,
la 4 un compte > 0, la 5 « prompts OK ».

---

## 7. Ce que ce document ne tranche pas

- **Le prix affiché en vidéo** : l'ADR-0014 fixe Free 0 €, Pilot 29 €, Operations 79 €, Enterprise sur devis.
  Les films V1 ne citent **aucun prix** — décision volontaire, le prix vit sur la page tarifs. Une campagne
  prix demanderait une validation produit.
- **L'URL du CTA** : `leopardo-rh.com` est l'identifiant figé (P03 §4) mais `PRODUCT_BRIEF.md` signale qu'il
  est **actuellement indisponible**. Les films affichent l'URL comme identifiant, jamais comme nom de produit ;
  une URL canonique stable doit être désignée avant tout lancement public.
- **Les traductions TR/AR** : elles restent des propositions d'agent, à relire par un locuteur natif avant
  diffusion (`POSITIONNEMENT_SUITE_METIER.md` §2).
- **Le nom du dépôt** : `leopardo-hr` reste figé. Aucun film ne le prononce.
