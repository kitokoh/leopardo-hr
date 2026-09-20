# Registre des canaux d'acquisition — canaux, fiches publiées & UTM

> Source de vérité de TOUTES les présences externes de Leopardo. Toute fiche, profil ou
> lancement publié doit avoir une ligne ici (règle du kit `platform-submissions/`).
> Convention UTM complète : `docs/GOTO_MARKET/CONVENTION_UTM.md`.
> Rapport mensuel d'acquisition : `rapports-mensuels/` (premier : `2026-09-acquisition.md`).
> Cadre de contenu : `docs/REFERENTIEL_PRODUIT/MESSAGE.md` (pitch canonique, promesses
> interdites) et `METRIQUES_VITRINE.md` (aucun chiffre public sans date de mesure).

## Registre des canaux

Un canal = une ligne. `utm_source` / `utm_medium` sont **canoniques** (vocabulaire fermé,
cf. `CONVENTION_UTM.md`) : tout lien sortant publié sur ce canal les porte tels quels.

| Canal | Statut | Owner | Cadence | `utm_source` | `utm_medium` | KPI suivi |
|---|---|---|---|---|---|---|
| Vitrine — SEO d'interception (`/alternatives`, pages « vs ») | en construction (lot 1, T4 2026) | gardien vitrine | Lot 1 puis lot 2 selon Search Console | *(trafic entrant — pas d'UTM ; mesuré via Search Console)* | `organic` | Clics/impressions Search Console sur requêtes « alternative / vs » |
| Blog vitrine (`/blog`) | à réactiver | gardien vitrine | 2 articles/mois | *(trafic entrant — Search Console)* | `organic` | Sessions organiques blog, CTA essai cliqués |
| LinkedIn (page + profil fondateur) | à confirmer (comptes fondateur) | fondateur | 2-3 posts/sem | `linkedin` | `social` | Sessions par UTM, abonnés page |
| X/Twitter | à confirmer (compte fondateur) | fondateur | 2 threads/sem | `x` | `social` | Sessions par UTM, followers |
| Communautés FB/WhatsApp/Telegram — Maghreb & Afrique de l'Ouest (DZ/MA/TN/SN/CI) | à démarrer (vague V5) | fondateur | Continu (valeur d'abord, pas de spam) | `facebook` / `whatsapp` / `telegram` | `community` | Sessions par UTM, trials par source |
| Annuaires open source (AlternativeTo, OpenAlternative, LibHunt, awesome-selfhosted, selfh.st, StackShare) | à publier (kits prêts, vague V2) | fondateur (publication) + gardien vitrine (kits) | One-shot + refresh trimestriel des fiches | `alternativeto`, `openalternative`, `libhunt`, `awesome-selfhosted`, `selfhst`, `stackshare` | `listing` | Fiches publiées, sessions par UTM |
| Listings B2B (Capterra/GetApp, G2, Crunchbase, F6S, VC4A, Wellfound, BetaList) | à publier (vagues V2/V4) | fondateur | One-shot + campagne d'avis pilotes | `capterra`, `getapp`, `g2`, `crunchbase`, `f6s`, `vc4a`, `wellfound`, `betalist` | `listing` | Fiches publiées, avis collectés, sessions par UTM |
| Product Hunt (launch) | à préparer (après URL canonique stable) | fondateur | One-shot coordonné | `producthunt` | `listing` | Upvotes, sessions J0-J7, trials par source |
| Hacker News (Show HN) | à préparer | fondateur | One-shot préparé | `hn` | `community` | Points/commentaires, sessions J0-J7, étoiles GitHub |
| dev.to / Hashnode (republication technique) | à démarrer (vague V3) | gardien vitrine | 1 article/mois (canonique vers la vitrine) | `devto` / `hashnode` | `referral` | Sessions par UTM, lectures |
| Reddit (r/selfhosted, r/opensource, r/smallbusiness) | à démarrer (au fil des features) | fondateur | Au lancement de features | `reddit` | `community` | Sessions par UTM |
| YouTube (démos FR 3-5 min) | à confirmer (compte) | fondateur | 2 vidéos/mois | `youtube` | `social` | Vues, sessions par UTM |
| GitHub (repo + Pages) | actif | kitokoh | Optimisation continue (topics, social preview) | `github` | `referral` | Étoiles, trafic « Insights → Traffic » |

**Règles** :
- Aucun canal sans ligne ici ; aucun lien publié sans UTM conforme (`CONVENTION_UTM.md`).
- Publication externe = validation fondateur au préalable (garde-fou stratégie §5).
- Statuts possibles : `actif` / `en construction` / `à publier` / `à préparer` / `à démarrer` / `à confirmer` / `en pause`.

## Registre des fiches & profils

| Plateforme | URL de la fiche | Compte utilisé | Publié le | Statut | Notes |
|---|---|---|---|---|---|
| GitHub (repo) | https://github.com/kitokoh/leopardo-hr | kitokoh | — | actif | Optimisation continue (topics, social preview) |
| GitHub Pages | https://kitokoh.github.io/leopardo-hr | kitokoh | — | actif | Landing statique |
| AlternativeTo | — | — | — | à publier | Kit prêt : `platform-submissions/alternativeto.md` |
| awesome-selfhosted | — | fork `kitokoh/awesome-selfhosted-data` | — | à préparer | Vérifier critères d'éligibilité |
| Product Hunt | — | — | — | à préparer | Kit : `product-hunt.md` |
| Capterra/GetApp | — | — | — | à publier | Kit : `capterra-getapp.md` |
| G2 | — | — | — | à publier | Kit : `g2.md` |
| OpenAlternative | — | — | — | à publier | |
| LibHunt | — | — | — | à publier | |
| StackShare | — | — | — | à publier | |
| Crunchbase | — | — | — | à publier | Kit : `startup-platforms.md` |
| F6S | — | — | — | à publier | |
| VC4A | — | — | — | à publier | |
| Wellfound | — | — | — | à publier | |
| BetaList | — | — | — | à publier | |
| LinkedIn (page) | — | — | — | à confirmer | |
| X/Twitter | — | — | — | à confirmer | |
| YouTube | — | — | — | à confirmer | |

## Lancements (one-shot)

| Événement | Date cible | Statut | Résultat |
|---|---|---|---|
| Show HN | — | à préparer | — |
| Product Hunt launch | — | à préparer | — |

## Actions owner restantes (mesure — issue #7877)

Voir le détail pas-à-pas dans `rapports-mensuels/2026-09-acquisition.md` §« Actions owner
restantes ». Résumé :

1. **Google Search Console** : vérifier la propriété du domaine vitrine live
   (`https://gestionemployer-backend.vercel.app` et/ou `https://leopardo-prod.vercel.app`)
   sur https://search.google.com/search-console.
2. **Sitemap** : soumettre `https://<domaine-vitrine>/sitemap.xml` dans Search Console
   (menu « Sitemaps »).
3. **Analytics vitrine** : brancher une solution d'analytics sur `front/web` (décision
   d'outil + variable d'env Vercel) pour lire sessions et trials par `utm_source`.
4. À la bascule vers `leopardo-rh.com` (#3452) : re-vérifier la propriété et re-soumettre
   le sitemap sur le domaine définitif.

*Mettre à jour ce fichier à CHAQUE publication externe. Rapport mensuel : `rapports-mensuels/`.*
