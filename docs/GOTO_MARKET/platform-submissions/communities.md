# Kit — Communautés & distribution sociale

> Respecter `MESSAGE.md` (pitch canonique, promesses interdites). Jamais de publication
> automatisée non relue. Chaque lien sortant porte un UTM et une entrée dans
> `../REGISTRE_CANAUX.md`.

## Hacker News — « Show HN » (une seule cartouche, à soigner)

**Titre** : Show HN: Leopardo – open-source business suite for field-based companies
**Texte (draft)** :

> Hi HN — I've been building Leopardo, an MIT-licensed, multi-tenant business suite for
> companies with field teams (construction, transport, fuel stations, restaurants,
> schools): HR & payroll, attendance via QR/GPS/biometric kiosks (ZKTeco), leave, CRM,
> accounting — web, Flutter mobile apps and kiosks, with an offline edge mode.
>
> The twist: it's built first for French-speaking Africa and the Maghreb (payroll rules
> for 21 countries in pilot status), where most SMEs still run payroll on Excel and
> can't rely on stable connectivity. Self-hostable or SaaS.
>
> Repo: https://github.com/kitokoh/leopardo-hr — feedback very welcome, especially on
> the multi-tenant isolation and offline sync design.

**Timing** : mardi-jeudi, 14h-16h UTC. Répondre à chaque commentaire pendant 6 h.
**Prérequis** : README impeccable, démo qui tient la charge, docs self-host à jour.

## Reddit

| Subreddit | Angle | Règle |
|---|---|---|
| r/selfhosted | Self-host, docker-compose, offline | Post « I built » avec détails techniques, pas de lien commercial |
| r/opensource | Licence MIT, architecture modulaire | Idem |
| r/smallbusiness, r/Entrepreneur | Problème (paie Excel, pointage terrain) → solution | Valeur d'abord, lien en commentaire |

## dev.to / Hashnode (republication + contenus dédiés)

Sujets dédiés dev : architecture modular monolith DDD (27 modules), isolation multi-tenant
PostgreSQL, offline-first sync (edge), 7 apps Flutter + core partagé, OpenAPI 798 endpoints
(mesuré 2026-09-18). Canonical URL → vitrine quand republication.

## LinkedIn (canal B2B n°1)

- **Page entreprise** : 2-3 posts/sem — feature, coulisse, étude de cas, republication blog.
- **Profil fondateur** : build-in-public FR : décisions produit, apprentissages, chiffres datés.
- **Formats** : carrousels comparatifs (repris des pages /alternatives), courtes vidéos démo.

## X/Twitter

Threads techniques + build-in-public, hashtags #opensource #selfhosted #buildinpublic.

## Facebook / WhatsApp / Telegram (Maghreb & Afrique de l'Ouest)

- Groupes cibles : entrepreneurs DZ/MA/SN/CI, RH & paie DZ, comptables DZ/MA/SN,
  gérants transport/restauration locaux.
- Règle : répondre aux questions réelles (paie, CNAS/CNSS, pointage) avec de la valeur,
  mentionner Leopardo quand pertinent. Jamais de spam de lien nu.
- Créer une communauté WhatsApp « utilisateurs Leopardo » quand ≥ 20 tenants actifs.

## YouTube

Démos 3-5 min par module et par vertical (FR, sous-titres EN/AR) — scripts dans
`../ASSETS_PRODUCTION/`. Titres SEO : « Gérer la paie en Algérie avec Leopardo », etc.

## Cadence hebdo type

| Jour | Action |
|---|---|
| Lun | Post LinkedIn page (feature/comparatif) |
| Mar | Thread X technique |
| Mer | Réponses groupes FB/WhatsApp + veille |
| Jeu | Post LinkedIn fondateur (build-in-public) |
| Ven | Contenu long (blog/dev.to/vidéo) 1 sem sur 2 |
