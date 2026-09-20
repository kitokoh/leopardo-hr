# Kit exécutable — Lancement coordonné Product Hunt + Show HN (#7874)

> Deux cartouches one-shot. On ne tire qu'une fois prêts, et **pas le même jour** :
> chaque canal exige 6 h de disponibilité fondateur pour répondre, et un double lancement
> divise l'attention. Cadre : `README.md` de ce dossier. Go/no-go = fondateur.

## 0. Go/no-go commun (tout doit être vert)

- [ ] URL canonique stable (décidée et gelée — plus de changement après le lancement).
- [ ] Démo publique qui tient la charge (test de montée en charge : un front page HN
      peut envoyer plusieurs milliers de visites en quelques heures).
- [ ] Signup e-mail seul fonctionnel de bout en bout (trial 14 j sans CB).
- [ ] README + docs self-host irréprochables (HN lit le repo avant le site) —
      croiser avec l'audit `awesome-selfhosted.md` §2.
- [ ] Plus de mention « domaine principal indisponible » dans le README.
- [ ] Assets PH prêts (§1) ; captures fraîches.
- [ ] Fondateur disponible 6 h en continu après chaque tir + un backup pour la modération.
- [ ] Analytics + UTM en place pour mesurer (§4).

## 1. Product Hunt

### Champs prêts-à-coller

| Champ | Valeur |
|---|---|
| Product name | Leopardo |
| Product URL | `<URL_CANONIQUE>?utm_source=producthunt&utm_medium=listing&utm_campaign=growth-2026` |
| Tagline (≤ 60 car.) | `Open-source business suite for field-based companies` |
| Topics | Open Source · SaaS · Productivity · Payroll (si dispo) · Android |
| Pricing | Free Options (self-host gratuit + trial 14 j sans CB) |
| Status | Available (uniquement si la démo publique est réellement ouverte) |
| Makers | Compte **personnel** du fondateur (PH interdit les comptes entreprise pour poster) |

**Description (≤ 260 car., prête-à-coller)** :
`Leopardo is the open-source, mobile-first business suite for field-based companies — HR & payroll, attendance (QR/GPS/biometric kiosks), leave, CRM, accounting and operations. Multi-tenant, self-hostable, offline-first. Free 14-day trial, no credit card.`

**First comment (à poster immédiatement après le go-live, compte fondateur)** :

> Hi Product Hunt! 👋
>
> I built Leopardo for companies whose teams are on the road, on sites, in restaurants or
> fuel stations — not behind desks. Most of them still run attendance and payroll on
> spreadsheets and WhatsApp, especially in French-speaking Africa and the Maghreb where
> I started.
>
> Leopardo is one suite for HR & payroll, attendance (QR, GPS, biometric kiosks), leave,
> CRM, accounting and field operations — web, mobile and kiosks, with an offline-first
> mode because connectivity there is never a given. Payroll rules cover 21 countries
> (pilot status for now — honest flag).
>
> It's MIT-licensed and multi-tenant: self-host it for free with Docker Compose, or take
> the 14-day trial (no credit card). Vertical packs switch on per tenant: travel agencies,
> restaurants, fuel stations, schools.
>
> I'd love your feedback — especially from anyone managing field teams or running
> payroll in emerging markets. I'll be here all day. 🙏

**Galerie (formats PH : 1270×760, miniature 240×240)** :

| Ordre | Visuel | Message |
|---|---|---|
| 1 | Dashboard web | One view of field operations |
| 2 | Pointage kiosk/mobile | Attendance that works offline |
| 3 | App mobile employé | HR in your team's pocket |
| 4 | Paie + exports | Payroll prep with controls |
| 5 | Archi/API OpenAPI | Open source & extensible |

### Timing optimal & procédure

- **Jour** : mardi ou mercredi (concurrence forte mais audience max ; éviter ven-dim).
- **Heure** : 00:01 PT (07:01 UTC) — la journée PH court sur 24 h PT, lancer à l'ouverture
  maximise la fenêtre de votes. Programmer le brouillon à l'avance (PH le permet).
- **J-14** : compte maker actif — commenter/upvoter authentiquement (un compte neuf qui
  poste est pénalisé) ; préparer brouillon + assets.
- **J-7** : brouillon complet relu ; prévenir le réseau proche (voir do/don't).
- **Jour J** : first comment immédiat ; répondre à chaque commentaire < 30 min pendant 6 h ;
  posts LinkedIn/X « we're live on PH » (sans mendier l'upvote) ; suivi trafic UTM.
- **J+1** : merci public + bilan interne.

### Do / Don't Product Hunt

- ✅ Prévenir sa communauté qu'on lance (lien vers la *page produit*).
- ✅ Répondre à tout, y compris aux critiques, avec substance.
- ❌ Demander explicitement des upvotes, acheter des votes, chaînes WhatsApp d'upvote —
  détection = déclassement (« we detected unusual activity »).
- ❌ Lancer avec une démo instable ou un signup cassé — pas de deuxième chance le jour J.
- ❌ Sur-promettre en commentaire (conformité paie « validée », iOS…) — MESSAGE.md s'applique.

## 2. Show HN (Hacker News)

### Post prêt-à-coller

**Titre** (≤ 80 car., pas de superlatif — les règles Show HN interdisent le marketing) :
`Show HN: Leopardo – open-source business suite for field-based companies`

**URL** : lien direct vers le repo GitHub (HN valorise le code ; la vitrine peut décevoir).
**Pas d'UTM dans l'URL soumise** (HN les strippe parfois et la communauté n'aime pas) —
mesurer via referrer + pic de trafic.

**Texte du post** :

> Hi HN — I've been building Leopardo, an MIT-licensed, multi-tenant business suite for
> companies with field teams (construction, transport, fuel stations, restaurants,
> schools): HR & payroll, attendance via QR/GPS/biometric kiosks (ZKTeco), leave, CRM,
> accounting — web, Flutter mobile apps and kiosks, with an offline-first edge mode.
>
> The twist: it's built first for French-speaking Africa and the Maghreb (payroll rules
> for 21 countries, in pilot status), where most SMEs still run payroll on Excel and
> can't rely on stable connectivity. Self-hostable via Docker Compose, or SaaS.
>
> Architecture notes: modular monolith (27 DDD modules), PostgreSQL multi-tenant
> isolation, OpenAPI layer (798 endpoints), 7 Flutter apps sharing a common core.
>
> Repo: https://github.com/kitokoh/leopardo-hr — feedback very welcome, especially on
> the multi-tenant isolation and offline sync design.

### Timing & procédure

- **Jour/heure** : mardi-jeudi, **14:00-16:00 UTC** (matin US Est + après-midi EU = audience max).
- Compte HN du fondateur avec un minimum de karma/historique (un compte créé le jour
  même est un red flag) — commencer à participer authentiquement dès maintenant.
- Répondre à chaque commentaire pendant 6 h, techniquement et sans défensive : les
  questions dures (multi-tenancy, sécurité, « why PHP », comparaison Odoo/ERPNext) sont
  une opportunité — préparer des réponses courtes sourcées dans le code.
- **Une seule soumission.** Si le post ne décolle pas, ne pas reposter le lendemain
  (attendre ≥ 1-2 mois et changer d'angle ; HN tolère une résoumission espacée).

### Do / Don't Show HN

- ✅ Ton ingénieur, faits, chiffres datés, limites assumées (« pilot status » est un atout de crédibilité ici).
- ❌ Demander des votes ou partager le lien direct du post pour voter (ring detection HN).
- ❌ Langage marketing (« revolutionary », « game-changing ») dans le titre ou les réponses.
- ❌ Réponses défensives ou évasives sur la sécurité — si on ne sait pas, le dire et créer une issue publique.

## 3. Calendrier coordonné (à caler sur le go fondateur)

| Jalon | Quand | Quoi |
|---|---|---|
| J-14 | — | Go/no-go §0 ; comptes PH/HN actifs ; brouillon PH créé |
| J-7 | — | Assets finaux ; test de charge démo ; réseau prévenu (PH) |
| **PH day** | mardi, 00:01 PT | Lancement PH + first comment + 6 h de réponses |
| PH day + 2-3 j | jeudi, 14-16 h UTC | **Show HN** (le trafic PH a validé la stabilité ; HN vierge de tout buzz artificiel) |
| J+7 | — | Bilan chiffré §4 |

Ordre PH → HN recommandé : PH sert de répétition générale de charge ; l'inverse expose
la démo au pic HN sans rodage. Ne jamais mentionner le score PH dans le post HN.

## 4. Bilan chiffré (critère d'acceptation — `rapports-mensuels/`)

À consigner dans `../rapports-mensuels/<AAAA-MM>.md` sous 7 jours :

| Métrique | PH | HN |
|---|---|---|
| Visites (UTM `producthunt` / referrer `news.ycombinator.com`) | — | — |
| Signups trial (attribution UTM) | — | — |
| Étoiles GitHub gagnées (avant/après, datées) | — | — |
| Upvotes/points & rang | — | — |
| Commentaires traités / issues créées | — | — |

## 5. Définition de fait (critères d'acceptation #7874)

- [ ] Checklist §0 verte + go fondateur écrit.
- [ ] Lancement PH exécuté (6 h de présence) puis Show HN à J+2/3.
- [ ] Bilan chiffré déposé dans `rapports-mensuels/` + lignes « Lancements » du
      `../REGISTRE_CANAUX.md` complétées.
