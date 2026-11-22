# Textes de référencement

> Textes prêts à coller, **par plateforme**. Ne pas copier-coller d'une plateforme à l'autre :
> les limites de caractères et les règles diffèrent, et un texte trop long est tronqué au milieu
> d'une phrase.
>
> **Contraintes** : pitch canonique et promesses interdites dans
> `docs/REFERENTIEL_PRODUIT/MESSAGE.md` ; aucun chiffre sans date de mesure
> (`METRIQUES_VITRINE.md`). Les `[A COMPLETER]` sont des emplacements, pas du contenu.

## Éléments communs

| Élément | Valeur |
|---|---|
| Nom | Leopardo |
| Catégorie | suite métier / business suite (décision #7428) |
| Catégories **interdites** | « logiciel RH », « HR SaaS », « Mobile-First Company OS » |
| Site | [A COMPLETER — URL canonique] |
| Dépôt | https://github.com/kitokoh/leopardo-hr |
| Cible | PME de 5–250 employés, équipes terrain |

## GitHub (description + topics)

**Description (max 350 caractères)**

```
Suite metier pour entreprises de terrain : RH et paie, pointage, absences, CRM,
comptabilite et operations. Web, mobile et bornes. Multi-tenant, API ouverte.
```

**Topics**

```
business-suite  erp  hr  payroll  attendance  crm  accounting  multi-tenant
laravel  vue  flutter  self-hosted  africa  sme
```

## AlternativeTo / OpenAlternative / LibHunt

**Description courte (max 200 caractères)**

```
Suite metier pour PME de terrain : RH, paie, pointage, absences, CRM et
comptabilite dans un seul outil. Web, mobile et bornes.
```

**Description longue**

```
Leopardo est une suite metier concue pour les entreprises de terrain — PME de
5 a 250 employes, equipes qui travaillent sur site plutot que devant un bureau.

Elle regroupe ce qui est habituellement disperse : gestion RH et paie, pointage
terrain, absences et conges, CRM, comptabilite et operations. Les equipes
travaillent depuis le web, le mobile ou une borne, sur les memes donnees.

Le produit est multi-tenant, expose une API documentee (OpenAPI) et peut etre
auto-heberge.

[A COMPLETER — ce qui distingue Leopardo pour votre marche, en une phrase]
```

## Capterra / GetApp / G2

**Points forts (3 puces, pas plus)**

```
- Un seul outil pour RH, paie, pointage, absences, CRM et comptabilite
- Fonctionne sur le terrain : mobile et borne, pas seulement au bureau
- API ouverte et auto-hebergement possible
```

**À qui ça s'adresse**

```
PME de 5 a 250 employes avec des equipes sur site : commerce, restauration,
transport, sante, education, agriculture.
```

> ⚠️ Ne pas remplir la section « avis clients » avant d'avoir des clients qui peuvent
> réellement témoigner. Un avis inventé est une faute, pas un raccourci.

## Product Hunt

**Tagline (max 60 caractères)**

```
La suite metier des entreprises de terrain
```

**Description (max 260 caractères)**

```
RH, paie, pointage, absences, CRM et comptabilite dans un seul outil, concu pour
les equipes qui travaillent sur site. Web, mobile et bornes. API ouverte,
auto-hebergement possible.
```

## Hacker News (Show HN)

**Titre**

```
Show HN: Leopardo – business suite for field-based SMEs (open API, self-hostable)
```

**Corps**

```
We build a business suite for small companies whose teams work on site rather
than at a desk: HR and payroll, time tracking, leave, CRM, accounting.

The problem we kept running into: these companies run five or six disconnected
tools, and the field data (attendance, expenses) never reaches the back office
without manual re-entry.

Architecture notes: multi-tenant PostgreSQL, modular monolith (41 bounded
contexts), OpenAPI as the contract, MCP server exposing the tool contract to
external clients.

[A COMPLETER — what you want feedback on specifically]
```

## dev.to / Hashnode

**Titre**

```
Pourquoi une suite metier plutot qu'un SaaS RH de plus
```

**Plan**

```
1. Le probleme reel : la donnee terrain n'arrive pas au back office
2. Ce qu'on a appris en migrant un monolithe plat vers 41 modules DDD
3. Le contrat d'outils : exposer une API a un agent sans lui donner les cles
4. Ce qu'on ne fait pas (et pourquoi)
```

> Article technique : renvoyer vers la vitrine en canonique, pas l'inverse.

## Règles de rédaction

1. **Jamais de superlatif non prouvé** (« le meilleur », « n°1 »).
2. **Jamais de chiffre sans date de mesure** — et la date doit être dans le texte.
3. **Jamais de nom de client** sans autorisation écrite.
4. **Toujours la même catégorie** : « suite métier », jamais « logiciel RH ».
