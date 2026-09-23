# Stratégie d'acquisition & de visibilité — Leopardo (2026 T4)

> ⚠️ **Contenu interne publié — décision propriétaire en attente (#7982).** La convention
> [`docs/README.md` §3](../README.md) classe ce document « ne DOIT PAS être publié » (tactique
> commerciale / mode opératoire interne) et le désigne candidat au dépôt privé. Il reste publié
> en attendant la décision (transfert vers un dépôt privé vs transparence assumée). Aucune
> donnée nominative (prospect, contact, pilote) ne doit y figurer.


> **Cadre obligatoire** : tout contenu produit dans le cadre de cette stratégie respecte
> `docs/REFERENTIEL_PRODUIT/MESSAGE.md` (pitch canonique, promesses interdites),
> `docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md` (aucun chiffre sans date de mesure) et
> `docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md` (catégorie « suite métier »,
> jamais « logiciel RH » / « SaaS RH » / « HR SaaS » comme catégorie — garde CI #7428).

**Version :** 1.0 · **Date :** 2026-09-20 · **Statut :** stratégie active — déclinée en issues GitHub (voir §9)

---

## 1. Objectif et principe directeur

**Objectif** : faire découvrir Leopardo par les personnes qui cherchent déjà une solution
(intention chaude), les convertir en essai gratuit 14 jours (sans CB), puis en tenant actif.

**Principe directeur** : Leopardo est jeune sur un marché occupé par des acteurs établis
(Odoo, Sage, PayFit, OrangeHRM…). On ne gagne pas en criant plus fort — on gagne en se
positionnant **là où les gens cherchent déjà ces acteurs** :

1. **SEO d'interception** — pages « Alternative à X » et « Leopardo vs X » sur la vitrine.
2. **Annuaires & marketplaces** — être présent partout où l'on compare des solutions.
3. **Communautés** — open source et self-hosted comme levier de crédibilité et de trafic.
4. **Écosystème startup** — visibilité investisseurs, presse tech, concours.
5. **Prescripteurs locaux** — cabinets comptables, intégrateurs, revendeurs (Maghreb/Afrique de l'Ouest).

Atout différenciant à exploiter systématiquement : **open source (MIT) + self-hostable +
paie multi-pays africains + mobile/kiosque terrain**. Aucun concurrent établi ne coche
les quatre cases à la fois.

---

## 2. Cibles et marchés (rappel du référentiel)

- **Cible** : PME terrain 5-250 salariés.
- **Marchés prioritaires** : Maghreb (DZ/MA/TN), Afrique de l'Ouest (SN/CI), Turquie.
- **Marchés secondaires** : France/Europe francophone, Canada (Québec), international EN.
- **Langues** : FR d'abord (marchés prioritaires), EN (annuaires/communautés mondiales), TR, AR.

Personas d'acquisition :

| Persona | Où il cherche | Contenu qui le convertit |
|---|---|---|
| Dirigeant PME / DAF (DZ, MA, SN, CI) | Google FR (« logiciel de paie algérie », « alternative sage »), Facebook pro, bouche-à-oreille comptable | Pages alternatives, guides paie pays, démo guidée |
| DRH / gestionnaire paie | Google, Capterra/GetApp, LinkedIn | Comparatifs, études de cas, checklist paie |
| DSI / dev / intégrateur | GitHub, r/selfhosted, Hacker News, AlternativeTo, awesome-selfhosted | README, docs API, self-host, articles techniques |
| Gérant secteur vertical (transport, resto, station, école) | Recherches métier, groupes Facebook/WhatsApp sectoriels | Pages verticales, démos sectorielles |
| Investisseur / presse tech | Crunchbase, F6S, VC4A, Disrupt Africa, TechCabal | Fiche startup, pitch deck, traction datée |

---

## 3. Pilier 1 — SEO d'interception : « Alternative à X » / « Leopardo vs X »

Le levier au meilleur ratio effort/impact. Les requêtes « alternative à [concurrent] » et
« [concurrent] avis/prix » ont une intention d'achat maximale et une concurrence SEO modérée.

### 3.1 Architecture sur la vitrine (`front/web`)

- **Hub** : `/alternatives` — page pilier listant tous les comparatifs, maillage interne.
- **Pages** : `/alternatives/[slug]` — une page par concurrent, rendue serveur avec
  metadata dédiée, JSON-LD (`Article` + `BreadcrumbList` + FAQ), hreflang, entrée sitemap
  (même pattern que `case-studies`).
- **Structure type d'une page** (honnête, pas de dénigrement — crédibilité = conversion) :
  1. Résumé : qui est le concurrent, pour qui il est bon.
  2. Tableau comparatif (10-12 critères) : périmètre fonctionnel, open source, self-host,
     paie pays africains, mobile/kiosque, offline, langues, modèle de prix.
  3. « Quand choisir [concurrent] » — section honnête, 3 cas.
  4. « Quand choisir Leopardo » — 4-5 cas différenciants.
  5. FAQ (migration, prix, données, self-host) → JSON-LD FAQPage.
  6. CTA : essai 14 j sans CB + démo guidée.

### 3.2 Lot 1 (T4 2026) — concurrents à intercepter en priorité

| Slug | Concurrent | Requête cible principale | Marché |
|---|---|---|---|
| `odoo` | Odoo | alternative à odoo / odoo alternative open source | DZ/MA/TN/SN/CI/FR — très cherché au Maghreb |
| `sage` | Sage (Paie & RH) | alternative à sage paie / sage afrique | Afrique francophone — l'incumbent |
| `payfit` | PayFit | alternative payfit | FR/EU |
| `orangehrm` | OrangeHRM | orangehrm alternative | International open source |
| `connecteam` | Connecteam | connecteam alternative / équipes terrain | International EN |
| `talenteo` | Talenteo | talenteo alternative / sirh algérie | DZ |

Lot 2 (T1 2027, selon Search Console) : Lucca, Factorial, Kelio, Skello/Combo, BambooHR,
Dolibarr, ERPNext, SeamlessHR, Workpay, Kiwi HR.

### 3.3 Règles de contenu (bloquantes)

- Faits vérifiables uniquement sur les concurrents (site officiel, docs publiques) ; en cas
  de doute → « non documenté ». Pas de prix concurrents chiffrés sans source datée.
- Statuts Leopardo honnêtes : règles de paie pays = **pilot** ; jamais « conformité légale
  validée » ; métriques datées conformes à `METRIQUES_VITRINE.md`.
- Mots interdits (garde CI) : « logiciel RH », « SaaS RH », « HR SaaS », « HR software »
  → utiliser « SIRH », « solution de gestion RH », « suite métier ».

### 3.4 Mots-clés d'intention locale (cluster blog, cf. Pilier 2)

`logiciel de paie algérie`, `logiciel paie et rh maroc`, `logiciel de paie sénégal`,
`gestion de la paie côte d'ivoire`, `pointage biométrique entreprise`, `logiciel gestion
agence de voyage`, `logiciel gestion station service`, `logiciel gestion école privée`,
`bordro programı` (TR). Une page guide par requête, avec CTA essai.

---

## 4. Pilier 2 — Blog & contenu propriétaire

État actuel : blog existant (`/blog`) mais contenu majoritairement archivé (2024). Plan :

1. **Réactiver la production** : 2 articles/mois minimum, FR d'abord.
2. **Clusters** (dans l'ordre) :
   - **Paie par pays** : « Guide de la paie en Algérie (IRG, CNAS) », idem MA/SN/CI/TN/TR —
     fort volume, zéro concurrent open source ne le fait. Statut pilot mentionné.
   - **Terrain** : pointage biométrique/QR/GPS, planning multi-sites, mode hors ligne.
   - **Open source** : self-host, souveraineté des données, coût total vs SaaS propriétaire.
   - **Verticaux** : gestion agence de voyage / restaurant / station / école.
3. **Republication** : dev.to + LinkedIn Articles (canonique vers la vitrine), résumés X/LinkedIn.
4. Chaque article : CTA essai + maillage vers `/alternatives` et pages modules.

### Cluster « paie par pays » — plan d'exécution

Lot 1 **livré** (#7870) : Algérie (`/blog/guide-paie-algerie-irg-cnas`) et Sénégal
(`/blog/gerer-la-paie-au-senegal-guide-pme`) — 2 000+ mots FR, parité 4 locales,
statut pilote mentionné, CTA essai + maillage `/alternatives` et modules.
Republication dev.to/LinkedIn (canonical vitrine) à exécuter côté owner.

Lots suivants (une issue GROWTH par article, même gabarit que le lot 1) :

| Lot | Pays | Titre de travail | Requête cible | Spécificités à couvrir |
|---|---|---|---|---|
| 2 | MA | « Guide de la paie au Maroc (IR, CNSS, AMO) » | logiciel paie et rh maroc | IR barème progressif, CNSS/AMO, CIMR, SIMPL-IR |
| 2 | CI | « Gérer la paie en Côte d'Ivoire : guide PME » | gestion de la paie côte d'ivoire | ITS/CN/IGR, CNPS, FDFP, convention interprofessionnelle |
| 3 | TN | « Guide de la paie en Tunisie (IRPP, CNSS) » | logiciel de paie tunisie | IRPP, CNSS, retenue à la source, SMIG/SMAG |
| 3 | TR | « Türkiye'de bordro rehberi (KOBİ) » — TR d'abord | bordro programı | gelir vergisi, SGK, asgari ücret, e-bildirge |

Règles communes : chiffres datés (« relevé AAAA-MM ») ; statut **pilot** explicite,
jamais « conformité validée » (MESSAGE.md) ; contenu dans
`front/web/src/modules/vitrine/data/blog.ts` (posts FR + overrides en/tr/ar) ;
visuel SVG dédié dans `front/web/public/blog/`.

---

## 5. Pilier 3 — Annuaires, marketplaces & listes open source

Les kits existent déjà (`docs/GOTO_MARKET/platform-submissions/`). Il faut **exécuter**,
dans cet ordre (coût 0 d'abord), et tenir un **registre des fiches** (URL, date, compte).

| Priorité | Plateforme | Coût | Kit | Action |
|---|---|---|---|---|
| P0 | GitHub (topics, description, social preview) | 0 | `github.md` | Optimiser le repo — fait en continu |
| P0 | AlternativeTo | 0 | `alternativeto.md` | Créer la fiche, se déclarer alternative à Odoo/Sage/PayFit/OrangeHRM/Connecteam |
| P0 | awesome-selfhosted | 0 | `awesome-selfhosted.md` | PR depuis le fork existant (`kitokoh/awesome-selfhosted-data`) — vérifier les critères (release ≥ 1 an ?) |
| P1 | OpenAlternative, selfh.st, LibHunt, StackShare | 0 | à créer | Fiches gratuites, mêmes textes |
| P1 | Product Hunt | 0 | `product-hunt.md` | Lancement coordonné (voir checklist kit) après stabilisation URL canonique |
| P1 | Capterra + GetApp (Gartner) | 0 (listing) | `capterra-getapp.md` | Fiche + campagne d'avis clients pilotes |
| P2 | G2 | 0 (listing) | `g2.md` | Fiche + 5 premiers avis |
| P2 | SaaSHub | payant | `saashub.md` | Conditionné à validation dépense |
| P3 | SourceForge / FOSSHub | 0 | kits | Optionnel, si miroir maintenable |

**Garde-fou** : aucune fiche publiée sans URL canonique stable + démo fonctionnelle +
captures fraîches (checklist de `platform-submissions/README.md`). Publication externe =
validation fondateur au préalable.

---

## 6. Pilier 4 — Communautés & distribution sociale

| Canal | Format | Cadence | Note |
|---|---|---|---|
| **LinkedIn** (page + profil fondateur) | Posts produit, coulisses build-in-public, articles | 2-3/sem | 1er canal B2B FR/Afrique |
| **X/Twitter** | Threads techniques, build-in-public, #opensource | 2/sem | Communauté dev/OSS |
| **Hacker News** | « Show HN: Leopardo — open-source business suite for field-based companies » | 1 fois, préparé | Fort potentiel (OSS + Afrique = angle original) |
| **Reddit** | r/selfhosted, r/opensource, r/smallbusiness | Au lancement de features | Règles anti-promo : apporter de la valeur d'abord |
| **dev.to / Hashnode** | Articles techniques (architecture DDD, multi-tenant, Flutter, offline-first) | 1/mois | Republication blog + contenus dédiés dev |
| **Facebook / WhatsApp / Telegram** | Groupes entrepreneurs & RH DZ/MA/SN/CI | Continu | Canal n°1 PME Maghreb/Afrique de l'Ouest |
| **YouTube** | Démos 3-5 min par module/vertical, FR | 2/mois | Réutiliser scripts `ASSETS_PRODUCTION` |

Règle : jamais de publication automatisée non relue ; comptes fournis par le fondateur ;
chaque lien porte un UTM (`utm_source`/`utm_medium`/`utm_campaign` — registre commun).

---

## 7. Pilier 5 — Écosystème startup, presse & concours

| Plateforme / action | Objectif | Coût |
|---|---|---|
| **Crunchbase** | Fiche société — crédibilité investisseurs | 0 |
| **F6S** | Profil startup + candidatures programmes | 0 |
| **VC4A** | Écosystème startups Afrique | 0 |
| **Wellfound (ex-AngelList)** | Profil + marque employeur | 0 |
| **BetaList** | Découverte early adopters | 0 |
| **Presse tech Afrique** : Disrupt Africa, TechCabal, WeAreTechAfrica, Afrikanheroes | Pitch e-mail avec angle « suite métier open source pour PME africaines » | 0 |
| **Concours/programmes** : Orange POESAM, Seedstars, MEST Africa Challenge, prix i&p | Dossiers de candidature | 0 |
| **Turquie** : startups.watch, Webrazzi | Fiche + pitch TR | 0 |

Prérequis : one-pager investisseur + deck à jour (existent dans `ASSETS_PRODUCTION` — à
actualiser avec métriques datées 2026-09).

---

## 8. Pilier 6 — Prescripteurs & partenariats locaux

- **Cabinets comptables & experts-comptables** (DZ/MA/SN/CI) : programme partenaire
  (page `/partner` existe) — commission ou co-branding, kit prescripteur PDF.
- **Intégrateurs IT & revendeurs matériel ZKTeco** : la compatibilité biométrique native
  est un cheval de Troie — lister les distributeurs ZKTeco locaux et proposer le bundle.
- **Écoles de gestion / incubateurs locaux** : offre gratuite éducation → notoriété.

---

## 9. Séquencement & déclinaison en issues

| Vague | Semaines | Chantiers |
|---|---|---|
| **V1 — Fondations** | S1-S2 | Pages `/alternatives` (lot 1), registre UTM + registre fiches, optimisation GitHub |
| **V2 — Annuaires gratuits** | S2-S4 | AlternativeTo, OpenAlternative, LibHunt, awesome-selfhosted PR, Crunchbase, F6S, VC4A |
| **V3 — Contenu** | S3-S8 | 2 guides paie pays, refresh blog, republication dev.to/LinkedIn |
| **V4 — Lancements** | S6-S10 | Product Hunt, Show HN, campagne avis Capterra/G2 |
| **V5 — Local** | S8-S12 | Kit prescripteur comptables, groupes FB/WhatsApp, presse tech Afrique |

Chaque chantier = une issue GitHub labellisée `BC-12 GROWTH` + `marketing`/`content`,
avec critères d'acceptation. La clôture suit la règle #4816 (preuve mergée/URL publiée).

## 10. Mesure

- **KPIs** : sessions organiques vitrine, clics Search Console sur requêtes « alternative/vs »,
  inscriptions trial (par UTM), tenants actifs à J+30, étoiles GitHub, fiches publiées.
- **Outillage** : Google Search Console (à vérifier), analytics vitrine, registre UTM
  (`docs/GOTO_MARKET/REGISTRE_CANAUX.md` — à créer), rapport mensuel dans
  `GOTO_MARKET/rapports-mensuels/`.
- **Boucle** : chaque mois, Search Console décide du lot suivant de pages alternatives.

---

*Document lié : issues de déclinaison (voir epic GROWTH), kits `platform-submissions/`,
`COMPARATIF_CONCURRENTS.md` (source interne des tableaux — à ne jamais publier tel quel).*
