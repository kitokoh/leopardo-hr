# Plan de distribution

> Ce plan **décide** la séquence. Les canaux eux-mêmes (UTM, cadence, owner) sont dans
> `docs/GOTO_MARKET/REGISTRE_CANAUX.md` — on n'en recopie rien ici, on y renvoie.

## 1. Objectif

Faire connaître la suite métier Leopardo auprès des PME de terrain (5–250 employés) sur les
verticales où le produit est réellement livré, et convertir l'intérêt en essais qualifiés.

**Ce que l'on ne fait pas** : promettre une couverture produit qui n'existe pas. Une vidéo sur
un module ne sort que si le module est livré et testé (voir `docs/ARCHITECTURE_STATUS.md` §3).

## 2. Verticales cibles

| Verticale | Module(s) de référence | Statut produit |
|---|---|---|
| RH & paie | `HR`, `Payroll`, `Absence`, `Attendance` | noyau — priorité 1 |
| Comptabilité & dépenses | `Accounting`, `Expense`, `Billing` | livré |
| Commerce & distribution | `Trade`, `Retail`, `Catalog` | livré |
| Restauration & hôtellerie | `Restaurant`, `RestaurantManager`, `HospitalityManager` | livré (tests partiels) |
| Voyage & transport | `TravelAgency`, `Vtc`, `Fleet`, `FuelStation` | livré |
| Santé & pharmacie | `HealthManager`, `Pharmacy` | livré |
| Éducation | `EduManager` | livré |
| Agriculture | `FarmOs` | livré (sans tests dédiés) |

> Les statuts « tests partiels » et « sans tests dédiés » viennent de la mesure publiée en
> `docs/ARCHITECTURE_STATUS.md` §3. Une verticale sans tests dédiés ne reçoit **pas** de
> campagne payante avant d'être couverte.

## 3. Séquencement des vagues

| Vague | Contenu | Prérequis |
|---|---|---|
| **V1 — Fondations** | vitrine SEO, fiches annuaires open source, GitHub | URL canonique stable |
| **V2 — Preuve** | démonstrations vidéo du noyau (pointage, paie, absences) | briefs `VIDEOS/` validés |
| **V3 — Autorité** | articles techniques (dev.to, Hashnode), republication | V1 publiée |
| **V4 — Lancement** | Product Hunt, Show HN, listings B2B | V2 + V3 publiées |
| **V5 — Communautés** | Facebook / WhatsApp / Telegram Maghreb & Afrique de l'Ouest | V2 publiée, valeur d'abord |

## 4. Règles de production

1. **Un brief par (catégorie × objet × cible)** — généré par
   `scripts/generate-video-brief.py`, jamais improvisé.
2. **Un texte par plateforme** — dans `REFERENCEMENT/TEXTES.md`, jamais copié-collé d'une
   plateforme à l'autre (les limites de caractères et les règles diffèrent).
3. **Une ligne de suivi par action** — dans `SUIVI.md`, avec URL dès publication.
4. **Validation fondateur avant toute publication externe** (garde-fou stratégie §5 de
   `docs/GOTO_MARKET/`).

## 5. Ce qui compte comme succès

Pas le nombre de publications : le nombre d'**essais qualifiés par verticale**. Une vague qui
produit 20 posts et 0 essai est un échec, et le tableau de bord doit le montrer.
