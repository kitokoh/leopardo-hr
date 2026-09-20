# Kit exécutable — Avis B2B : Capterra/GetApp, G2 + campagne d'avis pilotes (#7873)

> Objectif : crédibilité B2B — fiches sur les annuaires Gartner Digital Markets
> (Capterra + GetApp, une soumission commune) et G2, puis ≥ 5 avis authentiques.
> Cadre : `README.md` de ce dossier. **Jamais d'avis fabriqués, incités financièrement
> de façon opaque, ou rédigés à la place du client** — c'est illégal (pratiques trompeuses)
> et fatal sur ces plateformes.

## 0. Pré-vol

- [ ] URL canonique stable + page pricing publique (Gartner et G2 la demandent ; sinon
      indiquer « Free (open source) » + « Contact us » — pas de prix ferme inventé).
- [ ] Démo fonctionnelle + captures fraîches EN (mêmes assets que `annuaires-oss.md` §5).
- [ ] Vidéo démo 60-90 s (fortement recommandée sur Capterra ; facultative au lancement).
- [ ] Entité légale + e-mail pro du domaine (les deux plateformes vérifient le vendeur).
- [ ] Comptes créés par le fondateur (Gartner Digital Markets vendor portal ; G2 seller).

## 1. Capterra + GetApp (Gartner Digital Markets — une soumission, deux annuaires)

Portail : https://digitalmarkets.gartner.com (« List your product »). Le listing de base
est gratuit ; le PPC est optionnel (ne pas activer sans validation dépense).

| Champ | Valeur prête-à-coller |
|---|---|
| Product Name | Leopardo |
| Website URL | `<URL_CANONIQUE>?utm_source=capterra&utm_medium=listing&utm_campaign=growth-2026` (adapter `utm_source=getapp` si champ distinct) |
| Short Description (≤ 200 car.) | `Open-source, mobile-first business suite for field-based companies — HR & payroll, attendance (QR/GPS/biometric kiosks), leave, CRM, accounting and operations. Multi-tenant, self-hostable.` |
| Long Description | Voir bloc ci-dessous |
| Primary Category | Human Resources (catégorie de l'annuaire — notre *description* garde « business suite ») |
| Secondary Categories | Payroll, Attendance Tracking, Time Clock, Leave Management, CRM, Accounting, Workforce Management |
| Deployment | Cloud/SaaS · Self-Hosted/On-Premise · Android (**pas iOS** tant que TestFlight non public) |
| Pricing Model | Free version: Yes (open source, self-hosted) · Free trial: Yes (14 days, no credit card) · Subscription: « Contact us » tant que la page pricing publique n'est pas figée |
| Target Market | SMB & Mid-Market (5–250 employees), field-based companies — Africa, Maghreb, Turkey, francophone markets |
| Languages | French, English, Turkish, Arabic |

**Long Description (EN, prête-à-coller)** :

> Leopardo is the business suite for field-based companies — HR & payroll, attendance,
> leave, CRM, accounting and operations, on web, mobile and kiosks.
>
> Teams clock in via QR codes, GPS-fenced mobile check-in or biometric kiosks (ZKTeco
> bridge), with an offline-first mode for unreliable connectivity. Payroll preparation
> covers 21 countries (pilot status, measured 2026-09-09), focused on French-speaking
> Africa, the Maghreb and Turkey. Vertical packs activate per tenant: travel agencies,
> restaurants & delivery, fuel stations, schools.
>
> Leopardo is MIT-licensed and multi-tenant: self-host it for free with Docker Compose,
> or start a 14-day SaaS trial without a credit card. Onboarding takes under 30 minutes
> with the guided demo.

**Features checklist Capterra** (cocher uniquement l'existant — les filtres génèrent le
trafic) : Employee Database ✔ · Attendance Tracking ✔ · Time Clock ✔ · Leave Management ✔ ·
Payroll Preparation/Reporting ✔ · Document Management ✔ · Self-Service Portal ✔ ·
Mobile Access (Android) ✔ · Reporting/Analytics ✔ · Multi-Company/Multi-Tenant ✔ ·
CRM ✔ · Invoicing/Accounting ✔ · API ✔. **Ne pas cocher** ce qui n'est pas livré sur `main`.

**Checklist pas-à-pas** : 1) compte vendor Gartner ; 2) formulaire ci-dessus ; 3) logo +
4 captures + vidéo si prête ; 4) la fiche se propage à GetApp (et Software Advice) —
vérifier les 2 fiches après publication ; 5) 2 lignes au `../REGISTRE_CANAUX.md`.

## 2. G2 — fiche

Portail : https://sell.g2.com (« Create a profile », gratuit).

| Champ | Valeur prête-à-coller |
|---|---|
| Product Name | Leopardo |
| Seller Name | entité légale (fondateur) |
| Product Tagline | `The open-source business suite for field-based companies` |
| Description | Long Description du §1, identique |
| Categories | Core HR · Payroll · Time & Attendance · Workforce Management · Employee Self-Service · CRM (catégories G2) |
| Deployment | Cloud + Self-Hosted/Open Source (couvrir les deux segments de recherche) |
| Target Market | Small Business & Mid-Market (5–250 employees) |
| Pricing | Free (self-hosted) · Free trial 14 days, no credit card |

**Checklist** : 1) revendiquer/créer le profil ; 2) vérification vendeur (e-mail du
domaine) ; 3) champs + assets ; 4) registre. Objectif badge : 5 avis = présence dans les
grilles de catégorie.

## 3. Campagne d'avis pilotes (≥ 5 avis authentiques)

**Règles dures** : avis rédigé par le client lui-même, avec son compte, sur son expérience
réelle. Autorisé : demander, faciliter (lien direct), relancer une fois. Interdit :
rédiger à sa place, conditionner une remise à un avis *positif*, faire noter des collègues.
G2 propose des campagnes à incentive neutre gérées *par G2* — passer par ce canal
officiel uniquement, jamais d'incentive en direct.

**Ciblage** : 5-10 utilisateurs pilotes actifs (tenants réels, ≥ 3 semaines d'usage),
mix DZ/MA/SN/CI, rôles variés (gérant, RH/paie, ops). Prioriser une seule plateforme
(reco : **Capterra**, plus simple sans compte LinkedIn, formulaire FR disponible) pour
concentrer les 5 premiers avis, puis G2.

**E-mail type 1 — sollicitation (FR, prêt-à-coller, personnaliser [crochets])** :

> Objet : Votre retour sur Leopardo (5 minutes, ça compte beaucoup)
>
> Bonjour [Prénom],
>
> Vous utilisez Leopardo depuis [durée] pour [pointage/paie/…] chez [Société], et vos
> retours nous ont déjà aidés à améliorer [exemple concret].
>
> Nous ouvrons notre fiche sur Capterra, et les premiers avis d'utilisateurs réels
> comptent énormément pour une jeune solution. Accepteriez-vous d'y partager votre
> expérience — en toute franchise, points faibles compris ?
>
> → Lien direct : [URL de la fiche Capterra]
> Cela prend environ 5 minutes. Aucune obligation, et votre avis reste 100 % le vôtre.
>
> Merci beaucoup,
> [Prénom], fondateur de Leopardo

**E-mail type 2 — relance unique (J+7)** :

> Objet : Re: Votre retour sur Leopardo
>
> Bonjour [Prénom],
>
> Petit rappel au cas où mon message serait passé sous une pile : votre avis sur
> Capterra nous aiderait vraiment. Le lien : [URL]. Si vous préférez ne pas le faire,
> aucun souci — dites-le-moi et je ne vous relancerai plus.
>
> Merci !

**Suivi de campagne** (tenir dans ce fichier ou un sheet privé) :

| Pilote | Société | Pays | Sollicité le | Relancé le | Avis publié | Plateforme |
|---|---|---|---|---|---|---|
| — | — | — | — | — | — | — |

## 4. Définition de fait (critères d'acceptation #7873)

- [ ] Fiche Gartner (Capterra + GetApp) publiée + registre.
- [ ] Fiche G2 publiée + registre.
- [ ] 5-10 pilotes identifiés, e-mails envoyés (modèles §3), tableau de suivi tenu.
- [ ] ≥ 5 avis authentiques sur au moins une plateforme.
- [ ] Validation fondateur à chaque étape externe.
