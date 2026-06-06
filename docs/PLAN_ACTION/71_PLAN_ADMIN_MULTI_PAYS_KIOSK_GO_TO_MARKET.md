# Plan 71 - Admin plateforme, multi-pays, kiosk et go-to-market

## Objectif

Transformer le socle deja pret au lancement en experience commerciale et operationnelle coherente pour les premiers clients reels. Ce plan couvre les retours du 2026-06-06 : creation/activation client depuis l'app platform admin, multi-pays/multi-devise, i18n centralisee, vitrine essai, kiosk biometrie et audit commercial-technique.

## Principe d'execution

- Ne pas refaire les modules deja livres par les Plans 57-69.
- Corriger d'abord les contrats API qui conditionnent toutes les interfaces.
- Garder `front/mobile_apps/` comme mobile canonique.
- Garder `api/openapi.yaml`, `FRONTEND_API_CONTRACT_MATRIX.md` et `mobile-workflow-contracts.json` alignes.
- Tout texte futur doit passer par le socle i18n ou etre inscrit comme dette i18n explicite.

## Lot 71.1 - Platform admin : creer et activer une entreprise

### Actions

- Permettre au super-admin mobile de choisir le pays depuis une liste controlee.
- Deriver devise, fuseau horaire et langue depuis le pays quand le frontend ne les envoie pas.
- Permettre la creation en `trial` ou `active`.
- Afficher la devise dans la liste entreprises.
- Couvrir le cas mobile minimal par test backend.

### Statut

**Livre dans ce lot.** `POST /api/v1/platform/companies` accepte maintenant `status=trial|active` et applique les defaults pays via `CountryDefaults` au lieu de forcer DZD/Africa-Algiers. L'app platform admin affiche pays, devise, timezone, langue et activation immediate.

## Lot 71.2 - Multi-pays et devise produit

### Actions

- Etendre progressivement la table pays aux marches cibles.
- Ajouter un endpoint public interne `GET /api/v1/platform/country-defaults` si plusieurs frontends doivent consommer la meme liste.
- Brancher pricing, paie, factures, exports et dashboards sur la devise entreprise.
- Ajouter tests pour DZ, MA, TN, SN/XOF, CM/XAF, FR/EUR, TR/TRY.

### Criteres

- Aucun nouveau parcours client ne doit afficher DZD si `company.currency` vaut autre chose.
- Les nouveaux tenants doivent recevoir timezone/langue/devise coherents au provisioning.

### Statut

**Lot API/mobile livre.** Le backend expose `GET /api/v1/platform/country-defaults` comme source de verite super-admin pour pays, devise, fuseau horaire et langue. L'app `leopardo_platform_admin` consomme cette route avec fallback local non bloquant pour garder le formulaire utilisable pendant une latence API.

## Lot 71.3 - I18n centralisee toutes surfaces

### Actions

- Garder `front/mobile_apps/leopardo_core/lib/l10n` comme source mobile compilee.
- Garder le catalogue backend `/i18n/catalog/{locale}` pour updates distantes.
- Sortir progressivement les textes hardcodes des trois apps mobiles, de `front/web`, de `admin-dashboard` et du kiosk.
- Ajouter un garde de dette i18n par surface : liste de fichiers avec textes restants, priorisee par ecrans de vente/login/compte.

### Criteres

- Les nouveaux ecrans ne doivent pas ajouter de texte hardcode sans justification.
- FR reste langue de developpement, EN/AR/TR sont les langues de traduction Jules.

### Statut

**Lot gouvernance livre.** Le guide Jules multilingue formalise les fichiers autorises et les prompts EN/AR/TR. Le script `dev-hub/tools/validate-i18n-debt.ps1` produit un rapport par surface afin de prioriser les textes hardcodes sans bloquer encore la CI en mode non strict.

## Lot 71.4 - Vitrine : essai reel par email

### Actions

- Clarifier le CTA "Tester" : essai automatique ou demande d'essai.
- Si essai automatique : creer un workflow de provisioning sandbox a partir de l'email, avec anti-abus, quota et email de verification.
- Si demande d'essai : afficher explicitement le delai et le prochain pas commercial.
- Relier la capture lead a la creation platform admin ou a une demande client exploitable.

### Criteres

- Un visiteur qui clique "Tester" comprend immediatement ce qui se passe apres l'email.
- Le funnel produit ne depend pas d'une intervention manuelle invisible.

### Statut

**Lot vitrine livre.** `/signup` est maintenant un tunnel de demande d'essai guidee, sans collecte de mot de passe tant qu'un workspace n'est pas provisionne. Le formulaire collecte email professionnel, entreprise, role, taille d'equipe et telephone optionnel ; l'API `POST /api/forms/signup` transmet un lead exploitable avec `nextStep=contact_under_24h` et champs CRM/platform admin utiles.

## Lot 71.5 - Kiosk moderne et biometrie terrain

### Actions

- Moderniser l'ecran kiosk autour d'un geste principal : poser doigt/visage, puis arrivee/depart.
- Garder les modes fallback : identifiant, QR, offline bridge.
- Verifier que le kiosk consomme les endpoints `/kiosks/{deviceCode}/punch`, `/qr-punch`, `/sync`, `/roster`.
- Afficher une confirmation lisible : employe reconnu, action, heure locale, sync/offline.

### Criteres

- L'employe deja enrole peut pointer sans formulaire long.
- La borne reste utilisable si le bridge local ou le reseau est lent.

### Statut

**Lot interface livre.** `front/zkteco-kiosk/index.html` a ete restructure sans IDs dupliques ni blocs mal imbriques, avec un premier geste visuel centre sur la biometrie doigt/visage et des fallbacks QR/matricule. `app.js` bloque les doubles clics de pointage, garde l'offline-first via `/local/punch`, et affiche une confirmation plus lisible avec le nom employe quand le roster local le permet.

## Lot 71.6 - UX "Mon compte" et experience apps

### Actions

- Revoir la liste de fonctionnalites "Mon compte" en sections actionnables : identite, securite, documents, parcours, preferences, deconnexion.
- Verifier employee, manager et platform admin sur parcours login, home, compte, notifications, logout.
- Garder les spinners non bloquants et les etats vides utiles.

### Criteres

- Chaque bouton visible mene a un ecran ou une action fonctionnelle.
- Les informations sensibles sont separees des actions commerciales ou documentaires.

## Lot 71.7 - Audit commercial-technique go-to-market

### Actions

- Produire un audit court mais exploitable : etat actuel, risques, quick wins, priorites commerciales et techniques.
- Identifier les points bloquants pour marketing : essai, pricing, demo, support, onboarding client.
- Identifier les points bloquants pour production : secrets, queues, Redis, Firebase, observabilite, backups.

### Sortie attendue

- Rapport dans `docs/validation/`.
- Decisions produit transformees en issues/lots avant lancement public.
