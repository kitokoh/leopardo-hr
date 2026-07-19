# Audit vitrine — acquisition et conversion commerciale — 2026-07-19

Statut: complete pour publication
Auteur: audit interne KiloClaw (agent), a la demande de kitokoh
Perimetre: `front/web/src/modules/vitrine/**`, `front/web/src/app/(landing)/**`, `assets/screenshots/**`, `front/web/public/**`, `front/web/vercel.json`, workflow `.github/workflows/web-marketing-ci.yml`, deploiements GitHub/Vercel, `docs/GUIDES/GUIDE_LIENS_PLATEFORME_ET_COMMUNICATION.md`.

Ce document complete `08_AUDIT_ARCHITECTURE_TECH.md`, `09_AUDIT_MODULES_API_STRUCTURE.md` et `10_AUDIT_I18N_MULTILINGUE.md` avec un audit dedie a la vitrine commerciale (front-office marketing), en reponse directe a l'objectif "vendable immediatement depuis la vitrine" du `00_SOMMAIRE.md`. Les tickets d'action issus de cet audit sont listes en fin de fichier et repris dans `02_BACKLOG_ATOMIQUE.md` sous le prefixe `PA2-MKT-*` (suite de `PA2-MKT-001` a `007` deja existants).

Methode : lecture directe du code (`front/web/src/modules/vitrine`, `front/web/src/app/(landing)`), inspection des assets reels (`assets/screenshots/**`, `front/web/public/avatars/**`), verification des routes/liens vers des pages reellement existantes, requetes HTTP reelles sur le domaine de production annonce (`leopardo.com`) et sur les deploiements Vercel/GitHub Actions du repo, lecture de `docs/GUIDES/GUIDE_LIENS_PLATEFORME_ET_COMMUNICATION.md` pour l'etat reel des domaines/hebergeurs. Aucune capture d'ecran navigateur n'a pu etre prise (sandbox sans Chrome/dbus disponible) ; l'audit visuel s'appuie sur lecture de code (JSX/Tailwind, palette `globals.css`) et sur le rendu texte de `web_fetch`.

---

## 1. Ce qui fonctionne deja bien (a ne pas casser)

- **Architecture vitrine deja mature** : `front/web/src/modules/vitrine/` contient un vrai design system dedie (Navbar, Footer, Hero, sections reutilisables sous `components/sections/`, animations `ParticleField`/scroll-reveal, formulaires `NewsletterForm`/`QuickTrialEmailForm`, donnees separees du JSX sous `data/` pour `features`, `faq`, `testimonials`, `pricing`, `blog`, `changelog-public`). Ce n'est pas une vitrine jetable : la structure supporte une vraie iteration continue.
- **i18n vitrine fonctionnelle** (hors donnees, voir section 3) : `vitrine-locale.ts` fournit un copy typed complet en 4 langues (`fr`, `en`, `tr`, `ar`) pour le hero, les stats, le probleme/solution, avec RTL gere pour l'arabe.
- **Pages metier deja ecrites et credibles** : `pricing` (grille Free/Pilot/Starter/Pro avec mensuel/annuel), `case-studies` (3 etudes de cas chiffrees, adaptees au marche Algerie/Maroc/Tunisie/France/multi-pays), `demo` (formulaire de demande de demo avec champs qualifiants), `download` (apps mobiles), `changelog` public — le contenu commercial de base existe, ce n'est pas une coquille vide.
- **CI dediee et verte** : `web-marketing-ci.yml` ("Web CI - Leopardo Vitrine") est separee de l'admin (`web-ci.yml`), se declenche sur `front/web/**`, et les 5 derniers runs sur `main` sont `success` — la vitrine ne casse pas le build aujourd'hui.
- **SEO technique present** : `layout.tsx` declare des metadata OpenGraph/Twitter, `opengraph-image.tsx` et `twitter-image.tsx` generent des images dynamiques, scripts GA/Mixpanel branches — le squelette SEO/analytics n'est pas a construire depuis zero.
- **Assets produit reels et inexploites** (voir section 2) : de vraies captures d'ecran de l'application (dashboard, mobile employee/manager, admin) existent dans le repo en haute qualite — la vitrine n'a pas besoin de mockups factices, juste de brancher ce qui existe deja.

## 2. Le vrai probleme n°1 : la preuve produit existe mais n'est jamais montree

`assets/screenshots/` (28 Mo, sous-dossiers `web_showcase/`, `web_dashboard/`, `mobile_employee/`, `mobile_manager/`, `admin/`, `marketing/`) contient de vraies captures d'ecran de l'application en fonctionnement (confirme par lecture directe des PNG, ex. `web_dashboard/dashboard.png`, `mobile_employee/index.png`). Recherche exhaustive dans `front/web/src` : **aucune reference a `assets/screenshots/`, et aucun de ces fichiers n'est copie dans `front/web/public/`.** Le composant `ProductScreenshots.tsx` existe dans `components/sections/` (donc l'intention produit d'afficher des captures ecran est deja dans le design), mais consomme ses images depuis une autre source (a verifier au ticket) — pas depuis ces vrais screenshots produit.

Consequence directe : le ticket `PA2-MKT-001` deja present ("visuel produit reel ou video existante") n'est **pas encore satisfait** malgre du contenu pret a l'emploi. C'est le plus gros ecart entre "l'infra vitrine existe" et "la vitrine vend" — un visiteur B2B RH veut voir l'interface avant de laisser son email, et l'atout existe deja dans le repo sans etre utilise.

## 3. Le vrai probleme n°2 : preuve sociale cassee et trompeuse

- **Avatars temoignages 404** : `data/testimonials.ts` reference `/avatars/avatar-1.webp` a `avatar-4.webp`. Ces fichiers **n'existent pas** dans `front/web/public/avatars/` (seuls des fichiers nommes individuellement type `jean.svg`, `dupont.svg` y existent). Toute section testimonials avec ces entrees affiche une icone brisee a la place du visage du client — l'inverse de l'effet de confiance recherche.
- **Melange de langues dans les donnees pricing** (deja identifie dans `10_AUDIT_I18N_MULTILINGUE.md`, ticket `PA2-I18N-011`, rappele ici car impact direct sur la conversion) : `data/pricing.ts` contient des chaines turques codees en dur au meme niveau que du francais dans le meme objet — un visiteur `fr`/`en`/`ar` peut voir un fragment de texte turc sur la page de prix, la page la plus consultee avant un essai payant.
- **Marques "Ils nous font confiance" a verifier** : `components/sections/TrustedBrands.tsx` liste des marques reelles et connues (Arcelik, Vestel, Cevital, Sonatrach, SAP, Aramco, Dangote, MTN, Turkish Airlines...) presentees comme clients/partenaires sans distinction visuelle claire entre "client reel", "marche cible" ou "reference sectorielle". Si ces entreprises ne sont pas des clientes reelles de Leopardo, l'affichage tel quel constitue une allegation de social proof non etayee (risque reputationnel et legal, pas seulement un probleme de design) — a trancher explicitement avant tout lancement public.

## 4. Le vrai probleme n°3 : le domaine de production n'est pas le vrai produit

Verification reelle (requete HTTP) : `https://leopardo.com` repond aujourd'hui avec le contenu d'un site vitrine **sans rapport**, celui d'une entreprise americaine de construction commerciale ("Commercial Construction Company"), **pas** l'application Leopardo RH. `docs/DEPLOYMENT_PRODUCTION.md` et `docs/DEPLOYMENT_STAGING.md` documentent pourtant `NEXT_PUBLIC_SITE_URL=https://leopardo.com` / `https://staging.leopardo.com` comme cibles, et `docs/GUIDES/GUIDE_LIENS_PLATEFORME_ET_COMMUNICATION.md` confirme explicitement : *"Les URLs finales devront etre remplacees par les domaines officiels apres achat du nom de domaine"* — le domaine `leopardo.com` n'a en realite jamais ete achete pour ce produit, c'est un nom de domaine tiers deja detenu par quelqu'un d'autre.

Consequence concrete : la vraie vitrine de production tourne uniquement sur des URLs Vercel de deploiement (ex. `leopardo-*-africanovatech-8316s-projects.vercel.app`), dont plusieurs sont protegees par l'authentification SSO Vercel (redirection `vercel.com/sso-api`, `noindex` explicite) — **inaccessibles a un prospect externe**, et d'autres runs de deploiement recents affichent un statut `failure`. `staging.leopardo.com` ne resout meme pas (DNS non configure, `curl` renvoie code `000`). Aujourd'hui, il n'existe aucune URL publique, stable et indexable ou un prospect peut atterrir depuis une recherche Google, un lien LinkedIn ou une carte de visite. C'est un blocage total de l'acquisition, plus fondamental que n'importe quel probleme de contenu ou de design de la vitrine elle-meme.

## 5. Points ponctuels supplementaires

- **Composants "legacy" dupliques encore exportes** : `components/index.ts` exporte a la fois les nouvelles sections (`sections/HeroSection`, etc., "Phase 3") et les anciennes sous alias `LegacyHeroSection`, `LegacyFeaturesSection`, `LegacyTestimonialsSection`, `LegacyPricingSection`, `LegacyFaqSection`, `LegacyCTASection`. Rien ne prouve dans le code lu que ces exports legacy sont encore utilises par une page ; a auditer et supprimer si morts (dette qui complique toute future refonte visuelle et gonfle le bundle si les deux versions sont importees par erreur sur une meme page).
- **Footer avec ancres de section fixes non verifiees** : `getFooterHref` renvoie des ancres comme `#fonctionnalites` cote page d'accueil — a verifier que cette ancre existe bien sur toutes les pages ou le Footer est rendu (le Footer est partage entre plusieurs pages `(landing)/*`, or `#fonctionnalites` n'existe probablement que sur `page.tsx` de l'accueil).
- **Absence de preuve video** malgre un ticket dedie (`PA2-MKT-001` mentionne "video existante") : aucun fichier video ni integration (YouTube/Vimeo/self-hosted) trouve dans `modules/vitrine`. A trancher : produire une demo video courte (meme un screen-recording du dashboard reel) est un des leviers de conversion B2B les plus efficaces et n'existe pas aujourd'hui.
- **`ProductScreenshots.tsx` a verifier finement** : le composant existe et est cense afficher des captures produit dans le design ; son contenu source exact (images statiques bidon vs vrai produit) doit etre confirme au moment du ticket avant de decider s'il faut le brancher sur `assets/screenshots/` ou le remplacer.

## 6. Definition de "vendable depuis la vitrine" retenue pour ce plan

Un critere verifiable, pas une impression :

1. Un prospect externe peut atteindre la vitrine depuis une URL publique stable, indexable par les moteurs de recherche, sans authentification ni redirection SSO.
2. Le visiteur voit une preuve produit reelle (capture d'ecran ou video de l'application reelle) avant de devoir laisser son email.
3. Aucune preuve sociale cassee (avatar 404) ni trompeuse (marque listee comme "confiance" sans etre une reference reelle ou explicitement qualifiee) n'apparait sur la vitrine.
4. Le pricing et les CTA sont dans une seule langue coherente par visiteur (pas de melange de langues code en dur dans les donnees).
5. Chaque lien/ancre du footer et de la navigation pointe vers un contenu reel sur la page ou il est rendu, sur toutes les pages `(landing)` ou le composant est partage.

Tant qu'un seul point est faux, la vitrine reste "techniquement construite" mais pas "vendable en l'etat".

## 7. Tickets d'action (prefixe `PA2-MKT-*`, suite de 001-007 existants)

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-MKT-008 | P0 | Domaine de production public et accessible | `front/web/vercel.json`, DNS, `docs/DEPLOYMENT_PRODUCTION.md`, `docs/GUIDES/GUIDE_LIENS_PLATEFORME_ET_COMMUNICATION.md` | un domaine reellement possede (ou sous-domaine `vercel.app` de production, pas un lien de preview SSO) sert la vitrine sans authentification ni `noindex`; `leopardo.com` retire de la documentation tant qu'il n'est pas achete, ou plan d'achat explicite documente; `staging.leopardo.com` soit resolu soit retire des docs |
| PA2-MKT-009 | P0 | Brancher les vraies captures produit sur la vitrine | `front/web/src/modules/vitrine/components/sections/ProductScreenshots.tsx`, `front/web/public`, `assets/screenshots` | `ProductScreenshots` (et toute section hero pertinente) affiche des images optimisees (Next `Image`, formats webp/avif redimensionnes) provenant reellement de `assets/screenshots/{web_dashboard,web_showcase,mobile_employee,mobile_manager,admin}`, pas de placeholder; alt text descriptif par langue |
| PA2-MKT-010 | P0 | Corriger les avatars temoignages casses | `front/web/src/modules/vitrine/data/testimonials.ts`, `front/web/public/avatars` | plus aucune reference a un fichier avatar inexistant; soit les 4 fichiers `avatar-1..4.webp` sont ajoutes (photos ou avatars generes coherents), soit les entrees pointent vers les avatars existants, soit un fallback initiales/silhouette est utilise sans jamais afficher une icone brisee |
| PA2-MKT-011 | P0 | Trancher et documenter le statut des marques "Ils nous font confiance" | `front/web/src/modules/vitrine/components/sections/TrustedBrands.tsx` | decision ecrite: soit la liste est remplacee par des clients/pilotes reels avec autorisation explicite, soit elle est requalifiee visuellement en "marches vises"/"secteurs adresses" sans laisser croire a une relation client existante; aucune marque presentee comme cliente sans preuve |
| PA2-MKT-012 | P1 | Nettoyer les exports vitrine legacy dupliques | `front/web/src/modules/vitrine/components/index.ts`, pages `(landing)` | audit d'usage reel des exports `Legacy*`; suppression des composants et fichiers legacy non references par aucune page, ou justification ecrite si conserves (ex. usage par une page precise listee) |
| PA2-MKT-013 | P1 | Verifier la portee des ancres du Footer sur toutes les pages ou il est rendu | `front/web/src/modules/vitrine/components/Footer.tsx`, pages `(landing)/*` | chaque ancre (`#fonctionnalites`, etc.) resolvable sur chaque page qui rend le Footer, ou remplacee par un lien de page dedie; aucune ancre morte detectee en revue manuelle des pages listees |
| PA2-MKT-014 | P1 | Demo produit video courte | `front/web/src/modules/vitrine` | une video (screen-recording reel ou produite) de 60-120s integree sur la page d'accueil et/ou `/demo`, hebergee de facon performante (self-hosted optimise ou plateforme externe), sous-titree au moins en fr/en |

Ordre d'execution recommande : `PA2-MKT-008` d'abord (sans domaine public accessible, aucun autre ticket vitrine n'a d'impact commercial mesurable) → `PA2-MKT-010`/`PA2-MKT-011` (corrections de confiance rapides et a fort risque reputationnel) → `PA2-MKT-009` (preuve produit, plus gros levier de conversion) → `PA2-MKT-012`/`PA2-MKT-013` (nettoyage technique) → `PA2-MKT-014` (investissement contenu, apres que le reste soit stable).

---

## 8. Recapitulatif executif

| Domaine | Etat | Severite |
|---|---|---|
| Design system et structure vitrine (`modules/vitrine`) | Mature, reutilisable, bien separe donnees/JSX | OK |
| CI dediee vitrine (`web-marketing-ci.yml`) | Verte sur `main`, declenchement cible correct | OK |
| Contenu commercial de base (pricing, case studies, demo, download) | Deja redige et credible | OK |
| SEO technique (OG/Twitter images, metadata, analytics) | Squelette present | OK |
| Preuve produit visible (captures/video reelles) | Assets reels existants, jamais branches sur la vitrine | Eleve |
| Preuve sociale (testimonials, marques) | Avatars casses, marques a statut non clarifie | Eleve |
| Domaine de production public | `leopardo.com` non possede, deploiements reels caches derriere SSO/preview, staging non resolu | Critique |
| Coherence linguistique des donnees vitrine (pricing) | Melange fr/tr code en dur (recoupe `PA2-I18N-011`) | Moyen |
| Dette technique legacy (`Legacy*` exports) | Doublon potentiel non confirme mort ou vivant | Faible-moyen |
| Integrite des liens/ancres partages (Footer) | Ancres de section non verifiees cross-page | Faible-moyen |
