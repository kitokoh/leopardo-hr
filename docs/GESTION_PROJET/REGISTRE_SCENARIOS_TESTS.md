> **MAJ 2026-10-27 — HospitalityManager, HOSP-005 (#7947, BC-32) : gestion locative.**
> Surface **API** (`api/routes/modules/hospitality_manager.php`, module HospitalityManager) : **8 routes** ajoutees et
> documentees dans `api/openapi.yaml` — `GET|POST /hospitality/properties/{property}/leases`,
> `GET|PATCH /hospitality/leases/{lease}`, `POST /hospitality/leases/{lease}/terminate`,
> `POST /hospitality/leases/{lease}/generate-periods`, `GET /hospitality/rent-payments`,
> `POST /hospitality/rent-payments/{payment}/record`. Tenant re-verifie sur chaque route (404 fail-closed).
> Comportements reels documentes plutot que lisses : **`billing_day` ramene au dernier jour du mois** (un bail « au 31 »
> echu en fevrier tombe le 28, jamais reporte en mars) ; **`late` est DERIVE et recalcule a la lecture** — aucune
> commande planifiee ne conditionne la justesse du statut ; **un encaissement qui depasse le du est REFUSE (422)**, jamais
> tronque ; **un bail se resilie, il ne se supprime pas** (aucune route DELETE) et devient immuable une fois terminal ;
> **la generation d'echeances est idempotente** par `UNIQUE(lease_id, period)` et s'arrete a `ends_on`.
> Codes d'erreur verifiables : `INVALID_LEASE_TRANSITION` (409), `RENT_AMOUNT_EXCEEDS_OUTSTANDING` (422),
> `RENT_ALREADY_SETTLED` (422), `RENT_AMOUNT_INVALID` (422).
> Scenarios automatises : `php artisan test tests/Feature/Hospitality` (**106 tests / 408 assertions**),
> `dev-hub/tools/check-openapi-route-coverage.py --strict-staleness` (exit 0) et
> `dev-hub/tools/check-i18n-catalog-parity.sh` (4 langues). Gouvernance : `pint --test` PASS, PHPStan niveau 8 `No errors`.
> **Limite assumee** : la flotte Flutter n'est pas concernee (module backend) ; aucune verification manuelle de l'UI
> metier n'a ete faite — l'ecran locatif n'existe pas encore.

> **MAJ 2026-10-26 — Growth & Search Intelligence (GSI) : contrat OpenAPI des 26 routes du module (issues #8512, #8513, #8515, #8516, #8517, #8518, #8522).**
> Surface **API (`api/routes/modules/search_intelligence.php`, module SearchIntelligence, BC GROWTH)** : les **26 routes**
> du module sont desormais **documentees** dans `api/openapi.yaml` (elles etaient mergees sans contrat, ce qui faisait
> echouer le check requis `Route -> OpenAPI coverage guard`). Prefixe `/api/v1/platform/search-intel`, garde
> `auth:super_admin_api` portee par le groupe `platform`, permission route par route : **lecture** `search_intel.view`,
> **ecriture** `search_intel.manage`. Aucune route n'est exposee a un tenant (agregats cross-tenant par nature : le parc
> de sites public est unique ; un token Sanctum employee n'a pas le guard `super_admin_api` -> 401 systematique).
> Comportements reels documentes plutot que lisses : **AEO** — revision humaine obligatoire, la validation **fige** la
> reponse, workflow `draft -> submitted -> approved -> published`, `to-update` renvoie une reponse publiee en revision ;
> **GEO** — l'historique des checks est **immuable** (aucun PATCH/DELETE expose), methode de preuve et date obligatoires ;
> **contenu** — `/content/export` declare avant `/content/{content}` pour ne pas etre capture par le parametre de route,
> export en `text/csv` ; **concurrents** — une observation sans source datee est refusee (422) ; **SEO** — transition non
> autorisee par l'etat courant -> 409 ; **scan** — refuse de tourner sans cible declaree (`SEARCH_INTEL_SCAN_BASE_URL`),
> 409 plutot qu'une URL devinee.
> Scenarios automatises : `dev-hub/tools/check-openapi-route-coverage.py --strict-staleness` (garde de couverture, exit 0,
> **0 nouveau drift**, **0 nouveau drift de nom de parametre**) et `dev-hub/tools/test_openapi_route_coverage_orphans.py`
> (**13/13 verifications**). Gouvernance : `check-bounded-context-registry.sh` exit 0 (38 BCs, SearchIntelligence rattache
> a GROWTH), `check-route-owner-guard.sh` exit 0, `check-env-example-parity.sh` exit 0 (451 cles).
> **Limite assumee** : aucun PHP ni `vendor` dans l'environnement d'execution — `phpunit`/`pint` non lances localement ;
> l'execution reelle est laissee aux checks CI requis.

> **MAJ 2026-10-24 — Restaurant OS, REST-OS-5 : UI impression de l'écran cuisine (issue #8607).**
> Surface **front/web** (`src/app/(dashboard)/restaurant/kitchen/page.tsx`) : l'écran cuisine affiche désormais un **bandeau d'état
> d'imprimante** (prête / hors ligne / non configurée) et permet la **réimpression** d'un ticket.
> L'état est **dérivé des travaux d'impression** de la branche (`GET /restaurant/print-jobs?branch_id=X`) — le bridge
> `GET /restaurant/printing/bridge/health` est authentifié par jeton d'appareil et n'est pas exposé au navigateur. Dérivation
> dans `front/web/src/lib/restaurant/printer-status.ts` : `ready` (au moins un `printer_ref`, aucun ticket bloqué), `offline`
> (ticket `pending`/`claimed` > 5 min sans accusé), `not_configured` (aucun `printer_ref`) — **fail-closed**, jamais `ready`
> sans preuve. Réimpression = `POST /restaurant/print-jobs/{id}/requeue` → **nouvelle révision** côté serveur (jamais
> automatique). i18n : 22 clés `restaurant.printing.*` dans les 4 catalogues partagés (fr/en/ar/tr), propagées aux cibles
> web, admin-dashboard, mobile (ARB) et backend.
> Scénarios automatisés : `front/web/src/lib/restaurant/__tests__/printer-status.test.ts` (10 cas) et
> `front/web/src/app/(dashboard)/restaurant/kitchen/__tests__/restaurant-kitchen-page.test.tsx` (9 cas, dont les 3 états et
> les deux actions de réimpression). Preuve : `jest` 19/19, `tsc --noEmit` exit 0, `eslint` exit 0,
> `shared/i18n/validators/validate.js` → `I18N_VALIDATION_OK (4 locales)`.

> **MAJ 2026-10-24 — Business OS, écart O-03 : le provisioning superadmin crée l'établissement principal (issue #8623).**
> Surface **API (`api/app/Http/Controllers/Web/PlatformCompanyController.php`, module Platform)** : `POST /api/v1/platform/companies`
> accepte désormais `site_name` (`nullable|string|max:150`) et sa réponse expose `primary_site_id`.
> `CompanyProvisioningService::provisionSharedCompany()` crée l'établissement principal **dans la même transaction** que
> l'entreprise (`code = 'PRINCIPAL'`, `site_type = 'office'`, `status = 'active'`) — un tenant ne peut plus naître sans lieu
> d'exploitation (aucun `site_id` à référencer pour le pointage géolocalisé, le stock ou une vente). Idempotence par
> `company_id` : un rejeu de provisioning retourne l'établissement existant au lieu d'en créer un second. Le nom vient de
> `site_name` s'il est fourni, sinon du nom de l'entreprise (le contrat « payload minimal » de l'app mobile reste valide).
> Scénarios automatisés : `api/tests/Feature/PlatformCompanyProvisioningTest.php` (+3 cas : création nominale — exactement
> 1 site, nom/code/statut, `primary_site_id` cohérent ; payload sans `site_name` → nom = entreprise ; idempotence du rejeu —
> même id retourné, 1 seul site en base).
> **Limite assumée** : aucun PHP ni `vendor` dans l'environnement d'exécution — `phpunit`/`pint`/`phpstan` non lancés
> localement ; l'exécution réelle est laissée aux checks CI requis.

> **MAJ 2026-10-17 — Business OS, Lot 6 : console de provisionnement superadmin — PR #8668.**
> Surface **API (`api/routes/api.php`, module Platform)** : nouvelle route
> `GET /api/v1/platform/provisioning` (`PlatformProvisioningController`, middleware
> `platform.permission:companies.view`) — étapes de provisionnement franchies par tenant
> (`blocked_no_site` / `site_ready` / `onboarding_started` / `operational`), erreurs d'onboarding
> avec **email masqué** (minimisation RGPD) et message borné à 300 caractères, tenants sans
> établissement, catalogue des verticales avec leur maturité réelle. Faits de provisionnement
> uniquement, **aucun agrégat métier client**. Documentée dans `api/openapi.yaml`
> (`/platform/provisioning`).
> Surface **API (module EdgeSync)** : `GET /api/v1/edge-node/{nodeId}/sync-diagnostic` — file de
> synchronisation par statut, âge du plus ancien item en attente, échecs et conflits **avec leur
> cause** ; le heartbeat ne masque plus les `failed`/`conflict`. Colonnes `idempotency_key`,
> `last_error`, `last_attempt_at`, `conflict_at` sur `sync_queue` (migrations miroir tenant + edge).
> Documentée dans `api/openapi.yaml`.
> Scénarios automatisés : `api/tests/Feature/Platform/PlatformProvisioningApiTest.php`
> (**6 tests / 65 assertions**), `api/tests/Feature/EdgeSync/EdgeSyncDiagnosticsTest.php` +
> `EdgeSchemaParityTest.php` (**6 tests / 47 assertions**), `tests/Feature/BusinessOs`
> (**27 tests / 76 assertions**, non-régression). Garde de couverture OpenAPI
> (`check-openapi-route-coverage.py --strict-staleness`) → **exit 0**.

> **MAJ 2026-10-20 (#3) — passerelle publique : capacités métier déclarées par le backend `meta.actions` (PR #8763, issue #8761).**
> Le frontend portait un `BOOKING_TYPES` codé en dur : il dérivait dès qu'une verticale changeait de capacité et pouvait
> afficher un bouton inopérant. La source de vérité est désormais **le backend** : `PublicDiscoverableProvider::supportedActions()`
> (nouvelle méthode du contrat) déclarée par chaque verticale, agrégée par `PublicDiscoveryService::actionsByType()` et exposée
> dans `meta.actions`. Valeurs actuelles — `restaurant` : `contact`,`order`,`booking` ; `showcase` : `contact` ;
> `hospitality_property` : `contact`,`booking`. Aucun moteur universel, aucun service dupliqué : le CTA pointe vers le flux
> métier réel déjà en place (`POST /api/v1/restaurant/public/orders`, fiche publique d'hébergement). Fail-closed : sans
> `meta.actions`, le frontend n'affiche **aucune** action métier ; un type sans action déclarée affiche l'état explicite
> « indisponible » jamais un bouton mort. Ajout d'un CTA `order` distinct du `booking`.
> Scénarios automatisés : `api/tests/Feature/Platform/PublicDiscoveryTest.php` (+2 cas : `meta.actions` réel par type ;
> couverture de tous les `meta.types`) ; `front/web/src/app/decouvrir/__tests__/discovery-explorer.test.tsx`
> (+4 cas : CTA depuis les actions serveur, aucun CTA si actions vides, **fail-closed sans `meta.actions`**, réservation
> limitée aux verticales qui la déclarent).
> **Résultats réels** : `php artisan test --filter=PublicDiscovery` **9/9 (47 assertions)** ; `jest src/app/decouvrir`
> **20/20 (3 suites)** ; `tsc --noEmit` **0 erreur** ; `eslint --max-warnings 0` **0 warning** ; `check:mojibake` / `check:orphans` /
> `check:french-copy` **OK** ; `validate.js` → `I18N_VALIDATION_OK (4 locales)`.

> **MAJ 2026-10-20 (#2) — passerelle publique : contact client → professionnel `POST /api/v1/public/contact-requests` (PR #8763, issue #8760).**
> Surface **backend (`api`) + web grand public (`front/web`)** : le CTA « Contacter » de `/decouvrir` ouvre un formulaire
> public qui route vers le **tenant propriétaire** du profil. Réutilisation stricte de l'existant — aucun moteur nouveau :
> contrat `PublicProfileResolver` + `PublicProfileLocator` (même inversion de dépendance que `PublicDiscoverableProvider`),
> gardes fail-closed **identiques** à la découverte (`status=published`, `is_public`, société non `suspended`/`expired`,
> feature verticale active), flux Événement → listener calqué sur `CatalogInquiryReceived` (lead CRM BC-11 + notification
> BC-13 via `CommunicationService`), idempotence par `idempotency_key` unique. Table **plateforme** `public_contact_requests`
> (schéma public, **sans `company_id`** : le visiteur n'appartient à aucun tenant, comme `travel_customer_accounts`) ;
> le tenant est résolu **côté serveur**, jamais accepté en entrée. Séparation stricte avec Léa : aucune table `ai_*`,
> aucun service IA. Anti-spam : honeypot + `throttle:shop-public`. RGPD : consentement horodaté, IP **hashée**, rétention bornée.
> Scénarios automatisés : `api/tests/Feature/Platform/PublicContactRequestTest.php` (**10 cas** : acceptation + lead CRM
> tenant-scopé, notification manager, **404 uniforme** pour profil `draft`/feature absente/slug inexistant, société
> suspendue, **isolation cross-tenant** (2 tenants, aucun lead ni notification chez le non-visé), **aucune écriture `ai_*`**,
> rejeu idempotent sans doublon, `target_type` inconnu → 422, consentement manquant → 422, honeypot → succès factice sans
> persistance) ; `front/web/src/app/decouvrir/__tests__/contact-dialog.test.tsx` (**4 cas** : envoi avec **seuls**
> `target_type`/`target_slug` (aucun `company_id`), 404 → état indisponible **sans faux succès**, panne réseau → erreur
> réessayable, **même clé d'idempotence** conservée entre deux tentatives).
> **Résultats réels** : `php artisan test --filter=PublicContactRequestTest` **10/10 (37 assertions)** ;
> `jest src/app/decouvrir` **17/17 (3 suites)** ; `tsc --noEmit` **0 erreur** ; `eslint src --max-warnings 0` **0 warning** ;
> `check:mojibake` / `check:assets` / `check:orphans` / `check:french-copy` **OK** ; `validate.js` → `I18N_VALIDATION_OK (4 locales)` ;
> `check-i18n-catalog-parity.sh` **OK** ; `check-i18n-diff.js main HEAD` **aucune chaîne hardcodée nouvelle**.

> **MAJ 2026-10-20 — passerelle publique « Leopardo Public » : page `/decouvrir` sur `GET /api/v1/public/discover` — PR #8763.**
> Surface **web grand public (`front/web`)** : nouvelle page SSR indexable `/decouvrir` (recherche métier/service/nom,
> ville, catégorie, pagination) consommant l'agrégateur cross-verticale **déjà existant** (`PublicDiscoveryService`,
> SUPERAPP-P0 #8611) — **aucun BC créé, aucun fichier backend modifié**. Catégories dérivées de `meta.types` ;
> géolocalisation **sur action explicite** avec repli saisie manuelle ville/zone (aucune collecte en arrière-plan,
> position jamais dans l'URL SSR) ; chaque résultat pointe vers la fiche publique verticale fournie par l'API
> (`item.url`) ; CTA réservation uniquement pour les verticales qui la prennent en charge, sinon état explicite.
> Surface **i18n** : 31 clés `discovery.*` dans `shared/i18n/locales/{fr,en,ar,tr}.json`, propagées par
> `sync-web`/`sync-mobile`/`sync-backend` — **aucun catalogue généré modifié à la main** ; les ARB mobile reçoivent
> les mêmes clés par propagation automatique (d'où la mise à jour de ce registre, exigée par la garde de gouvernance
> dès qu'une surface `front/mobile_apps/` ou `front/admin-dashboard/` bouge).
> Scénarios automatisés : `front/web/src/app/decouvrir/__tests__/public-discovery-api.test.ts` (**7 cas** : query vide,
> encodage `q`/`city`, `type` inconnu **jamais transmis** (l'API répond 422), type canonique transmis, rayon borné et
> envoyé seulement avec une position, `per_page` plafonné à 50, `page=1` omis) ;
> `front/web/src/app/decouvrir/__tests__/discovery-explorer.test.tsx` (**6 cas** : rendu multi-verticales, lien de sortie
> propre à chaque verticale, état vide, état erreur + réessai, **aucun identifiant interne** dans le HTML rendu,
> action de réservation présente uniquement pour les verticales qui la supportent). **Résultats réels** :
> `jest src/app/decouvrir` **13/13** ; `jest src/modules/vitrine src/app/restaurants src/app/__tests__`
> **713/713 (66 suites)** ; `tsc --noEmit` **0 erreur** ; `eslint src --max-warnings 0` **0 warning** ;
> `check:mojibake` / `check:assets` / `check:orphans` / `check:french-copy` **OK** ;
> `shared/i18n/validators/validate.js` → `I18N_VALIDATION_OK (4 locales)` ; `check-i18n-catalog-parity.sh` → parité
> fr/en/ar/tr ; `check-i18n-diff.js main HEAD` → aucune chaîne hardcodée nouvelle ; `next build` **compilé**
> (`/decouvrir` dans `app-path-routes-manifest.json`).
> **Non couvert par tests** : la phase « Collecting page data » du build n'a pas pu s'exécuter dans le sandbox
> (cgroup 2 Gio, 47 workers → OOM) — la compilation et le typecheck passent, la génération statique complète reste
> à valider par la CI du dépôt ; les parcours E2E navigateur (géolocalisation réelle, refus d'autorisation) ne sont
> pas exécutés localement.

> **MAJ 2026-10-19 — #8741, BC-38 Paiements : permissions dédiées payments.view et payments.manage.**
> Surface **API (`api/app/Core/Tenant/Domain/Enums`)** : création de `PlatformPermission::PaymentsView` et
> `PlatformPermission::PaymentsManage`, intégration à `PlatformRole::permissions()` pour `Finance` (avec `Admin`
> et `SuperAdmin`), restriction des endpoints Superadmin `/api/v1/platform/payments/*` dans `api/routes/api.php`.
> Scénarios automatisés : `api/tests/Feature/Platform/PlatformPermissionMatrixDeploymentTest.php` (+1 test dédié
> `test_payments_endpoints_require_payments_view_and_manage`, 9 routes vérifiées dans le DataProvider, assertions
> de matrice pour tous les rôles plateforme).
>
> **MAJ 2026-10-17 — #8652, #8653, #8654, #8655, Travel OS : sièges physiquement absents, cohérence disposition ↔ inventaire, rafraîchissement du plan — PR #8656.**
> Surface **API (`api/app/Modules/TravelAgency`)** : `SeatLayout` accepte `absent_seats` (entiers positifs, uniques,
> bornés par la capacité) et expose `sellableCapacity()` ; `GenerateTripSeatsAction` ne crée **aucune** ligne
> `travel_trip_seats` pour un siège physiquement absent — l'inventaire correspond au bus réel, pas au nombre de
> places déclaré. Nouvelle `ValidateSeatLayoutCoherenceAction` (lecture seule) : refus **422** motivé d'une
> disposition incohérente, jamais de correction silencieuse ; refuse aussi de rendre absent un siège **déjà
> réservé**, en listant les sièges en conflit. `StoreTravelVehicleRequest` / `UpdateTravelVehicleRequest` valident
> `seat_layout.absent_seats` (entiers, distincts). Migration **idempotente** `2026_10_17_140000_8652` :
> `absent_seats` vit dans le JSONB `seat_layout` existant, aucune colonne nouvelle, aucune ligne réécrite.
> Surface **web grand public (`front/travel-web`)** : `buildSeatMapLayout` reconstruit la séquence **physique**
> complète quand le véhicule déclare des sièges absents — chaque siège absent occupe sa place réelle en case non
> cliquable, au lieu d'être omis (ce qui **décalerait** tous les sièges suivants) ; état `absent` avec symbole
> dédié, libellé texte et style barré (jamais la couleur seule) ; `resolveSeatLayout` normalise `absent_seats`.
> `booking-flow.tsx` rafraîchit périodiquement le plan (30 s), annule au démontage, et **désélectionne + signale**
> un siège devenu indisponible — aucun temps réel simulé, la disponibilité définitive reste tranchée par le
> serveur à la confirmation.
> Surface **web admin (`front/admin-dashboard`)** : `TravelSeatMap.vue` rend les sièges absents (case non
> cliquable, légende conditionnelle) ; `TravelBookingsView.vue` affiche un bandeau `role="note"` signalant qu'un
> changement de disposition **ne déplace pas** les passagers déjà réservés.
> Surface **i18n** : sources `shared/i18n/locales/{fr,en,tr,ar}.json` éditées puis propagées par
> `sync-web`/`sync-backend`/`sync-mobile` — **aucun catalogue généré modifié à la main**. Les ARB mobile reçoivent
> `travelSeatsStateAbsent` / `travelSeatsLayoutnotice` (propagation automatique du catalogue partagé).
> Scénarios automatisés : `api/tests/Feature/Travel/TravelAbsentSeatsTest.php` (**12 tests / 24 assertions** :
> `sellableCapacity`, siège absent hors capacité → disposition inexploitable, inventaire sans siège absent,
> véhicule non configuré inchangé, refus 422 hors capacité / doublon, refus de rendre absent un siège réservé avec
> liste des conflits, acceptation d'un siège absent libre, véhicule non configuré accepté sans géométrie) ;
> `front/travel-web/src/lib/seat-layout.test.ts` (**+4 cas** : siège absent à sa place réelle sans décalage,
> aucune case absente sans déclaration, normalisation, forme inexploitable ignorée). Suites Travel : **40/40
> verts** ; `travel-web` vitest **37/37** ; PHPStan niveau 8 → **No errors** ; idempotence de la migration vérifiée
> sur la base réelle. **Non-régression mesurée** : `tests/Feature/Travel` complet comparé à un worktree propre de
> `origin/main` → **16 erreurs / 83 échecs des deux côtés**, +12 tests (les nouveaux) : **0 régression**.

> **MAJ 2026-10-17 — vague 6 (correctif a11y), vitrine : constellation atteignable au clavier, focus restitué à l'étoile.**
> Surface **web (`front/web/src/modules/vitrine/components/hero`)** : les étiquettes d'étoiles de `HeroBusinessOS3D`
> passent de `<div>` décoratifs (`pointer-events-none`, aucun `tabindex`) à de **vrais `<button>` focusables**
> (`aria-label`, `tabindex 0`, anneau `focus-visible`) — la fiche s'ouvre au clavier (Entrée/Espace) comme à la
> souris ; le survol souris **ou** le focus clavier allume l'étoile ; une étiquette hors cadre passe en
> `visibility:hidden` + `tabindex -1` (plus de boutons invisibles dans l'ordre de tabulation) ; `aria-hidden` est
> retiré en mode interactif (il aurait masqué de vrais contrôles) ; `HeroScene3DPanel` donne `tabindex -1` à la
> scène, cible de restitution du focus quand la fiche a été ouverte à la souris (l'étoile est un pixel de canvas).
> **Défaut mesuré en navigateur sur `main` (7859b93c4)** : aucun chemin clavier vers la fiche, focus retombant sur
> `<body>` à la fermeture. Scénarios automatisés : nouveau
> `front/web/src/modules/vitrine/components/hero/__tests__/HeroScene3DPanel-a11y.test.tsx` (**4 cas** : étiquette
> focusable par solution, ouverture clavier + fermeture Échap, scène `tabindex -1`, `role=dialog` + `aria-modal`).
> Suite vitrine complète : **57 suites / 662 tests** verts. Aucune clé i18n ajoutée, aucun catalogue généré modifié.

> **MAJ 2026-10-17 — vague 6, vitrine : direction artistique premium du héros `stage`.**
> Surface **web (`front/web/src/modules/vitrine` + `front/web/src/app/globals.css`)** : couche de profondeur
> (`.hero-stage-base` bleu nuit/graphite, `.hero-stage-halo` derrière la scène, `.hero-stage-vignette`, voile
> `.hero-stage-title-scrim`) ; pile inchangée et explicite — cartes `z-[1]` < titre `z-[5]` < constellation `z-10`
> < vignettage `z-[11]` < CTA `z-20` ; arc géographique recalé (`--geo-r: min(30vw, 21vh)`, `--geo-apex: 54%`)
> pour que son sommet tombe sur le cœur de la scène et non sur la phrase ; `HeroScene3DPanel` porte la scène à
> 86 % de la hauteur pour rapprocher le CTA unique ; indicateur de défilement retiré du layout `stage` (il occupait
> exactement la place du CTA). Scénarios couverts par le nouveau `HeroStageDepth.test.tsx` (9 cas) : présence et
> `aria-hidden` des trois plans, ordre des couches titre/scène/vignettage, absence de l'indicateur, règles CSS du
> fond (dégradés + bleu nuit `rgb(8 14 26)`), du halo et du vignettage, neutralisation de `hero-halo-breathe` hors
> `no-preference`, calage de l'arc (`--geo-apex`, `--geo-r`) et courbe d'échelle `0.68 → 1 → 0.68`. Suite vitrine
> complète : **56 suites / 658 tests** verts. Aucune clé i18n ajoutée, aucun catalogue généré modifié.

> **MAJ 2026-10-17 — #8638, vitrine : retours vague 5 (fiche accessible, CTA unique, cartes derrière la constellation) — PR #8637.**
> Surface **web (`front/web/src/modules/vitrine`)** : `HeroSolutionsCTA` ne rend plus qu'**un seul** lien
> (« Découvrir nos solutions » → `/packs`, `?lang=` préservé), rendu par `HeroScene3DPanel` donc **sous** la
> scène ; `SolutionCard` passe en `createPortal(document.body)` avec overlay `fixed inset-0 z-[100]`
> (fermeture X / fond / Échap, focus piégé puis restitué à l'étoile) ; `GeoBackdrop` passe en `z-[1]`
> (11 cartes en demi-cercle, continents puis pays, intérieur vide, échelle 0,68→1→0,68) ; pile explicite
> cartes `z-[1]` < titre `z-[5]` < constellation `z-10` ; constellation agrandie (échelle 3D 1,12→1,3) ;
> libellés d'étoiles bornés à 15 % du cadre. Copie allégée : `hero3d.hint` (« Modules to explore »),
> titre/sous-titre CTA et réassurance commerciale retirés ; `nav.packs` → « Pack ».
> Surface **i18n** : sources `shared/i18n/locales/{fr,en,tr,ar}.json` éditées puis propagées aux cibles
> générées (`sync-web`, `sync-mobile`, `sync-backend`) — **aucun catalogue généré modifié à la main**.
> Scénarios automatisés : `front/web/src/modules/vitrine/components/hero/__tests__/mission-leopardo.test.tsx`
> (fiche : ouverture, fermeture X/fond/Échap, focus piégé et restitué ; CTA unique ; cartes derrière la
> constellation) — **55 suites / 649 tests verts** sur `src/modules/vitrine`.
> Vérifié localement : `npx tsc --noEmit` exit 0, `npx eslint src/modules/vitrine --max-warnings 0` exit 0,
> gardes `check:french-copy` / `check:mojibake` / `check:orphans` / `check:assets` vertes.
> `next build` **non concluant en local** (exit 137 — OOM, cgroup 2 Gio du sandbox) : à valider en CI.
> Surface **API** : aucun changement de route, de contrôleur ni de contrat.
> **MAJ 2026-10-17 — #8584, #8585, #8586, #8578, Fuel Station OS : cle d'idempotence du lot de releves, test outbox sans consommateur, endpoints dedies resolve/close.**
> Surface **API (api/app/Modules/FuelStation/Interfaces/Api/V1/Controllers/FuelIncidentController.php)** :
> les endpoints dedies `POST /fuel-station/incidents/{incident}/resolve` et `.../close` n'exigent plus
> les notes (`RESOLUTION_NOTES_REQUIRED` / `CLOSURE_NOTES_REQUIRED` supprimes) — l'exigence reste portee
> par le workflow strict `POST /fuel-station/incidents/{incident}/transition` (cible `resolved` sans notes
> → 422). Les deux chemins sont desormais **satisfaisables simultanement** ; aucune route ajoutee, aucun
> contrat de reponse modifie.
> Surface **API (api/app/Modules/FuelStation/Interfaces/Api/V1/Requests/SyncFuelReadingsRequest.php)** :
> la cle d'idempotence du **lot** de releves recoit la meme contrainte que le releve unitaire
> (`StoreMeterReadingRequest`) — `regex:/^[A-Za-z0-9\-_.]{8,191}$/`. Une cle trop courte provoquait des
> **collisions de deduplication** (deux releves distincts ecrases l'un par l'autre, donc des releves de
> compteur perdus silencieusement) ; hors format → 422.
> Surface **API (api/app/Modules/FuelStation/Infrastructure/Services/FuelIncidentService.php)** :
> `assign()` conserve `in_progress` — **contradiction de test documentee, non contournee** :
> `FuelIncidentApiTest:149` attend `in_progress` et `:257` attend `assigned` pour la **meme** operation,
> aucune implementation ne peut satisfaire les deux (arbitrage produit rattache a #8578).
> Scenarios automatises : `tests/Feature/Fuel/FuelOutboxApiTest.php`
> (`test_dispatch_without_consumer_dead_letters_and_can_retry` substitue desormais un registre de
> consommateurs **vide** pour ce test uniquement — la premisse « aucun consommateur enregistre » etait
> fausse, `FuelStationServiceProvider::boot` enregistre `FuelAccountingOutboxConsumer` ; le chemin
> dead-letter est donc reellement exerce **sans desenregistrer un consommateur de production**),
> `tests/Feature/Fuel/FuelReadingSyncApiTest.php` (cle d'idempotence trop courte → 422) et
> `tests/Feature/Fuel/FuelIncidentApiTest.php` (resolve/close sans notes → 200).
> Verifie localement (PHP 8.4.26 + PostgreSQL 15.19 + PostGIS 3, `CI=true`) : suite Fuel complete
> **266 tests / 1063 assertions / 11 echecs** contre un baseline de **266 / 1064 / 13** — diff des listes
> numerotees : seuls les **2 tests corriges**, **aucun ajout**, **0 regression**, aucun test desactive.
> PHPStan strict niveau 8 sur `app/Modules/FuelStation` → `[OK] No errors` ; `pint --test` sur les
> 4 fichiers du lot → PASS ; garde Route → OpenAPI → exit 0 ; garde de parite MVP → OK (350 tables).
> Surface **docs** : `CHANGELOG.md` (entree dans `[Unreleased]` existant, aucun nouveau header).
>
> **MAJ 2026-10-17 — #8633, mission Business OS : statut hors-ligne honnête dans le centre « Mes logiciels » (gap F-01).**
> Surface **web (`front/web/src/lib/software-catalog.ts`)** : le booléen `offline` purement déclaratif
> est remplacé par `OfflineMode = 'verified' | 'implemented' | 'absent'`. Vérification du câblage réel :
> `/restaurant/kitchen` et `/commerce/pos` passent par `dispatch()` de `useOfflineSync` (file réelle),
> le kiosque ZKTeco possède un pont local `/local/punch` ; en revanche `/restaurant/pos`, `/kiosk` et
> `/fuel` émettent leurs POST **directement** — ils ne revendiquent plus de continuité hors-ligne.
> `verified` exige une référence de scénario CI, sinon retombe fail-closed sur `implemented` ;
> `absent` n'affiche **aucun** badge (`isOfflineClaimed()` centralise la règle, consommée par
> `/mon-logiciel`). Résultat : 3 apps `implemented`, 9 `absent` — **recul de promesse assumé**.
> Scénarios automatisés : `front/web/src/lib/__tests__/software-offline-honesty.test.ts`
> (6 cas : aucun badge pour `absent`, `verified` sans preuve refusé, liste fermée des apps revendiquant
> le hors-ligne = `commerce-pos`, `desktop-attendance`, `restaurant-kds`, et les trois surfaces à POST
> directs ne revendiquent rien) plus `software-catalog.test.ts` (10 cas) et
> `front/web/src/app/(dashboard)/mon-logiciel/__tests__/page.test.tsx` (6 cas) — **16/16 + 6/6 verts**.
> Vérifié localement : `npx tsc --noEmit` exit 0, `npx eslint` exit 0 sur les 3 fichiers touchés,
> `check-web-design-tokens.sh` vert, `check-i18n-catalog-parity.sh` vert (4 langues).
> Surface **i18n** : clés `software.offlineBadgeVerified` et `software.offlineImplementedHint` ajoutées
> dans `shared/i18n/locales/*.json` puis propagées aux cibles générées (`sync-web`, `sync-backend`,
> `sync-mobile`) — voir la note du même jour dans `SCENARIOS_TEST_MOBILE_FLUTTER.md`.
> Surface **API** : aucun changement de route, de contrôleur ni de contrat.
>
> **MAJ 2026-10-17 — #8603-#8610, Restaurant OS : impression thermique ESC/POS + RBAC d'impression.**
> Surface **API (api/routes/modules/restaurantmanager.php)** : 11 routes ajoutees, toutes
> documentees dans `api/openapi.yaml` (garde Route -> OpenAPI, drift 0).
> **4 routes tenant** (`auth:sanctum` + tenant + `api.manager`) : `POST /restaurant/orders/{order}/print-jobs`,
> `GET /restaurant/print-jobs`, `GET /restaurant/print-jobs/{job}`,
> `POST /restaurant/print-jobs/{job}/requeue` — permissions `restaurant.print_jobs.operate`
> (cuisine/service) et `restaurant.print_jobs.manage` (direction/gerance).
> **7 routes bridge** (`/restaurant/printing/bridge/*` : health, jobs, claim, ack, fail,
> payload, preview) — **auth dediee** par jeton partage **hache SHA-256** en variable
> d'environnement (`X-Restaurant-Print-Bridge-Token`) + `X-Company-Id`, **fail-closed 404**
> si `RESTAURANT_PRINT_BRIDGE_ENABLED` n'est pas true ou si le hash est absent ; **aucun
> acteur Sanctum** sur ces routes (allowlist explicite du garde
> `check-vertical-controller-policies`, avec justification dans le script).
> **Invariants couverts** : idempotence d'enfilement (UNIQUE `company_id, order_id,
> ticket_kind, revision` — double POST -> un seul travail), reclamation **atomique**
> (second claim -> 409), `printed` **uniquement** sur accuse du bridge, `attempts`
> incremente a chaque tentative, 404 cross-tenant sur les 11 routes.
> **Scenarios de test** : `tests/Unit/Restaurant/EscPosOrderTicketRendererTest.php`,
> `tests/Unit/Restaurant/RestaurantPermissionsTest.php`,
> `tests/Feature/Restaurant/RestaurantPrintJobTest.php`,
> `tests/Feature/Restaurant/RestaurantPrintBridgeTest.php`,
> `tests/Feature/Restaurant/RestaurantPrintJobPersistenceTest.php`.
> **Total : 27 tests / 95 assertions**, executes en local sur PostgreSQL (`CI=true`).
> **Configuration** : `RESTAURANT_PRINT_BRIDGE_ENABLED` (defaut `false`),
> `RESTAURANT_PRINT_BRIDGE_TOKEN_HASH`, `RESTAURANT_PRINT_PAPER_WIDTH` (58|80),
> `RESTAURANT_PRINT_CLAIM_LEASE_MINUTES` — documentes dans `api/.env.example`.
> **Limite connue** : l'impression PHYSIQUE (octets reellement remis a l'imprimante) exige
> du materiel ESC/POS 58/80 mm et le daemon bridge local ; non verifiable en CI.

> **MAJ 2026-10-17 — #8581 + #8583, Fuel Station OS : convention monétaire additive, logo de station et vue comparative inter-stations.**
> Surface **API (api/routes/modules/fuel_station.php)** : trois nouvelles routes,
> documentées dans `api/openapi.yaml` (garde Route → OpenAPI, drift 0) —
> `GET /fuel-station/reports/station-comparison` (manager, `FuelReportPolicy::viewAny`),
> `POST /fuel-station/stations/{station}/logo` et `DELETE /fuel-station/stations/{station}/logo`
> (manager, `FuelStationPolicy::update`, `throttle:fuel-sensitive`).
> **Piège de routage** : `station-comparison` est enregistré **avant** `/reports/{type}` —
> Laravel résout par ordre d'enregistrement, sinon le segment générique capture le chemin
> (404 `SNAPSHOT_TYPE_UNKNOWN`).
> **P1-c — convention monétaire (#8581)** : la bascule destructive des colonnes
> `decimal(14,2)` vers des entiers en unités mineures casserait l'app mobile gelée ; la
> convention cible est donc exposée de façon **additive** — `FuelSale::amountMinor()`,
> `unitPriceMinor()`, `quantityMinor()` (arrondi au centime / au milli-litre) et colonne
> `fuel_sales.currency` (devise ISO 4217 explicite par vente). Les champs historiques
> (`amount`, `unit_price`, `quantity`) ne sont **jamais retirés**.
> **P1-e — logo de station (#8583)** : `fuel_stations.logo_path|logo_disk|logo_url` +
> `FuelStationLogoService`, qui **réutilise le stockage du branding société** (disque
> `public`, chemin par tenant) — aucun stockage parallèle. Chemin toujours préfixé par
> `company_id` ; le logo d'un autre tenant est indiscernable d'un inexistant (404).
> **P1-f — vue comparative inter-stations (#8583)** : `FuelReportingService::compareStations()`
> — une ligne par station du tenant (ventes, quantité, montant, écarts de rapprochement,
> état des cuves, cuves basses) + totaux ; les stations **sans activité sont incluses à zéro**.
> Scénarios PHPUnit : `tests/Feature/FuelStation/FuelStationLot6Test.php`
> (10 cas / 50 assertions — unités mineures exposées sans casser les champs historiques,
> arrondi au centime, upload logo stocké sous le préfixe tenant et retourné, PDF refusé 422,
> isolation tenant du logo (404), suppression du logo (fichier + colonnes), agrégation par
> station avec tri par nom, station muette incluse à zéro, isolation tenant de la comparaison,
> opérateur refusé 403). Non-régression : suite Fuel complète **266 tests / 1064 assertions /
> 13 échecs** contre un baseline de **256 / 1014 / 13** — liste d'échecs strictement identique
> (diff des listes numérotées) : **0 régression**. Colonnes vérifiées en base
> (`information_schema`).
>
> **MAJ 2026-10-17 — BC-24 TRAVEL OS, clôture #8546 / #8547 / #8548 (contrôle billets, impression QR, historique télémétrie).**
> Surface **API** : trois surfaces TravelAgency complétées, **sans reconstruire
> l'existant** (`VerifyTicketAction` #8538, `CheckInTicketAction` #8539,
> `TravelTicketQrCode`, `TravelTicketPdfGenerator`, `RecordVehiclePositionAction`
> #8540, `VehiclePositionFreshness`, `BuildTravelFleetTrackingAction`, PostGIS +
> module core `Geo` réutilisés tels quels).
> **(1) #8546** — `POST /api/v1/travel/tickets/lookup` : recherche **manuelle par
> référence**, secours quand la caméra est indisponible. **LECTURE SEULE** — ne
> consomme jamais le billet, ne divulgue aucun code de contrôle, identité du
> passager réservée à `travel.checkin`, cross-tenant → 404.
> **(2) #8547** — preuve que le QR du billet est **réellement scannable depuis le
> PDF livré** : `TravelTicketQrDecoder` (décodeur QR en PHP pur, vérifie les
> syndromes de Reed-Solomon et **refuse** une image dégradée plutôt que de
> deviner) + `PdfEmbeddedImageExtractor` (lit l'image telle qu'embarquée par
> dompdf : FlateDecode + inversion du prédicteur PNG).
> **(3) #8548** — `GET /api/v1/travel/vehicles/{id}/positions` : **historique des
> positions réellement transmises** (table `travel_vehicle_position_logs`,
> append-only, isolation `company_id`), instantané **et** historique dans la
> **même transaction**, `recorded_at` borné (`before_or_equal:now`,
> `after:-30 days`), **liste VIDE** si aucun dispositif — jamais de points
> fabriqués.
> Scénarios PHPUnit : `api/tests/Feature/Travel/TravelTicketLookupTest.php`
> (9 cas : lecture seule, aucun code divulgué, 403 non habilité, 404
> cross-tenant, statut `void` remonté), `TravelTicketPrintingTest.php` (10 cas :
> QR décodé **depuis le PDF**, charge utile sans PII, payloads distincts,
> refus d'une image illisible/blanche, PDF valide, une seule image couleur),
> `TravelVehiclePositionHistoryTest.php` (17 cas : instantané + historique
> atomiques, horodatage de mesure conservé, date future/trop ancienne refusée,
> source inconnue refusée, coordonnées hors bornes refusées, historique vide
> sans dispositif, ordre antéchronologique, fenêtre temporelle, `limit` plafonné,
> estimation jamais `is_measured`, 404 cross-tenant, 403 non habilité, périmètre
> par véhicule). **36 tests / 88 assertions, 0 échec** (PHP 8.4.26 + PostgreSQL +
> PostGIS + Redis) ; PHPStan strict niveau 8 → 0 erreur ; non-régression
> `tests/Feature/Travel` : `main` 561 succès / 100 échecs → branche **611 succès /
> 100 échecs**, noms des échecs **byte-identiques** (aucune régression).
> **Limites assumées** : scan caméra et impression thermique ESC/POS nécessitent
> un **appareil réel** (aucune compatibilité affirmée) ; le **suivi GPS temps
> réel n'est pas déclaré opérationnel** (aucun fournisseur de télémétrie
> n'alimente le système, aucune position simulée) ; détection de retard non
> livrée. Documentation : `docs/ops/TRAVEL_IMPRESSION_BILLETS.md`,
> `docs/ops/TRAVEL_SUIVI_FLOTTE.md`.
> **MAJ 2026-10-17 — #8579 + #8580, Fuel Station OS : moyens de paiement et annulation compensatoire des ventes.**
> Surface **API (api/routes/modules/fuel_station.php)** : nouvelle route
> `POST /fuel-station/sales/{sale}/cancel` (bloc manager, `throttle:fuel-sensitive`),
> documentée dans `api/openapi.yaml` (garde Route → OpenAPI, drift 0).
> **P1-a — moyens de paiement** : `fuel_sales` ne portait aucune colonne de règlement
> (`source` décrit la provenance de la saisie, pas la nature du règlement) ; migration
> additive/idempotente `2026_10_17_100000_9003` ajoutant `payment_method` (défaut `cash`,
> CHECK `cash|card|account|voucher|other`), `payment_reference` (référence externe opaque,
> jamais un PAN) et l'index de ventilation `(company_id, payment_method)` ; validation
> `StoreFuelSaleRequest` (valeur hors liste → 422) ; colonnes miroir dans
> `CreatesMvpSchema` (garde #5443).
> **P1-b — annulation compensatoire** : `FuelSaleService::cancel()` est une **écriture
> compensatoire** — la ligne d'origine est **conservée** et marquée `cancelled` (aucun
> DELETE), montant/quantité/opérateur restent lisibles ; refusée (422) si la session de
> caisse est déjà `closed`/`approved` ou si la vente est déjà annulée ; ré-incrémente la
> cuve du produit annulé et publie `fuel.sale.cancelled.v1` ; trace `audit_logs` ;
> `FuelSalePolicy::cancel()` réserve l'acte à la supervision (manager), croisé avec
> l'accès ressource-scopé par station (#7600).
> Scénarios PHPUnit : `tests/Feature/Fuel/FuelSalePaymentAndCancellationTest.php`
> (10 cas / 40 assertions — mode de paiement enregistré et exposé, défaut `cash`, valeur
> inconnue rejetée 422, annulation manager sans DELETE avec montant/opérateur conservés,
> événement outbox `fuel.sale.cancelled.v1` publié, opérateur refusé 403, motif obligatoire
> 422, session de caisse clôturée refusée 422, double annulation refusée 422, cross-tenant
> 404). Non-régression : suite Fuel complète **256 tests / 1014 assertions / 13 échecs**
> contre un baseline de **246 / 974 / 13** — liste d'échecs strictement identique
> (0 régression) ; PHPStan strict niveau 8 → `[OK] No errors`.
> **MAJ 2026-10-17 — #8597 (BOS-045), fiches applications du centre « Mon Logiciel ».**
> Surface **web (front/web)** : le hub `/mon-logiciel` expose désormais, pour
> chaque application, une fiche complète (rôle, profils concernés,
> fonctionnalités principales, prérequis, méthode de connexion, permissions
> nécessaires, étapes de mise en service) et un **statut de disponibilité
> dérivé des cibles réelles** (`softwareAvailability()` : `published` /
> `pilot` / `not_distributed`) — jamais déclaré à la main, donc jamais menteur.
> Fiche dépliable accessible (`aria-expanded` / `aria-controls`), texte 100 %
> i18n. Scénarios Jest : `lib/__tests__/software-catalog.test.ts` (10 cas —
> dont statut dérivé, complétude des fiches, absence de cible cliquable pour
> une app non distribuée) et `mon-logiciel/__tests__/page.test.tsx` (6 cas —
> dont rendu de la fiche et badge de disponibilité). Libellés fr/en/ar/tr
> ajoutés dans `shared/i18n/locales/*.json` puis propagés par
> `shared/i18n/sync/*` (web, admin, `api/lang`, ARB mobile) — parité #7089
> vérifiée.
> **MAJ 2026-10-16 — Mission vitrine : constellation, fiche solution, CTA, cartes géo (LEO-VIT-01→05).**
> Surface **web vitrine (front/web)** : constellation = solutions métier réelles
> (`modules/vitrine/data/solution-catalog.ts`, source unique réutilisant `PACK_PAGES`),
> fiche interactive au clic (`hero/SolutionCard.tsx`, `role="dialog"` + focus piégé),
> CTA sous la constellation (`hero/HeroSolutionsCTA.tsx`), cartes géographiques animées
> (`hero/GeoBackdrop.tsx` + `data/geo/map-paths.ts`, `prefers-reduced-motion` respecté),
> entrée « Trade » retirée de la navbar et repositionnée dans `/packs` (route `/trade`
> inchangée). Scénarios Jest : `hero/__tests__/mission-leopardo.test.tsx` (fiche, CTA,
> cartes, non-régression navbar) ; suite vitrine complète 55 suites / 638 tests.
> Surface **admin-dashboard** : correction d'une sur-promesse i18n — la clé
> `packsTravelDesc` (×4 locales) annonçait des « commissions » pour l'agence de voyage
> alors que le module `TravelAgency` n'en porte pas (le modèle `Commission` appartient au
> programme partenaire) ; remplacée par « paiements / règlements des ventes suivis ».
> Source corrigée dans `shared/i18n/locales/*.json` puis propagée par `sync-web.js`
> (catalogues web + admin + `versions.json`), conformément à #4805.
> **MAJ 2026-10-15 — #8476→#8486 (PR #8487), verticale « International Trade OS » (BC-36 TRADE).**
> Surface **API HTTP** (`api/routes/modules/trade.php`, gate `module.trade` fail-closed + `trade.role:*`) :
> noyau commercial **partagé** entre les deux espaces — `GET/POST /v1/trade/rfqs`,
> `POST /v1/trade/rfqs/{rfq}/send` (liens de réponse retournés en clair **une seule fois**),
> `GET /v1/trade/quotes`, `POST /v1/trade/quotes/{quote}/accept` (l'acceptation
> crée la commande), `GET/POST /v1/trade/cost-estimates`, `GET /v1/trade/customs/checklist`,
> et `GET /v1/trade/meta/integrations` (état RÉEL des 4 adaptateurs externes).
> **?direction=incoming|outgoing** sélectionne la vue acheteur/vendeur sur les
> MÊMES objets : pas deux implémentations, et une même entreprise peut importer
> ET exporter sans rôle irréversible. Isolation tenant par `BelongsToCompany` :
> une ressource d'une autre entreprise est **introuvable (404), jamais 403**.
> Honnêteté du calcul : chaque poste porte sa `source`
> (`confirmed|estimated|user_input|unavailable`) et `duty_rate_available` reste
> `false` tant qu'aucune source de tarifs n'est configurée — la capacité est
> INDISPONIBLE, jamais « estimée à 0 ».
> Surface **API superadmin** (`api/routes/api.php`, groupe `/platform`) :
> `GET /platform/trade/overview` (compteurs agrégés par tenant — entreprises
> portant le flag `trade`, répartition par statut des deals/RFQ/offres/commandes)
> et `GET /platform/trade/integrations` (état des adaptateurs + identité du
> manifest). **Compteurs uniquement** : aucun contenu commercial privé (prix,
> offres, messages, documents) n'est exposé à l'administration plateforme.
> Surface **web tenant** (`front/web`) : route protégée `/trade` — onglets
> « Vue d'ensemble », « Sourcing & demandes de prix » (acheteur),
> « Ventes & offres » (vendeur), « Coûts & douane » (avec disclaimer de
> non-responsabilité douanière) ; 403 `MODULE_DISABLED` → état dédié.
> Surface **web admin** (`front/admin-dashboard`) : `views/trade/TradeOverviewView.vue`
> (route `/trade`, nav groupe « Clients & croissance », perm `companies.view`),
> tableau des intégrations avec badge « Opérationnelle » / « Non configurée ».
> Couverture : `api/tests/Unit/Trade/TradeSurveyTest.php` (11 tests — les 3
> critères d'acceptation du périmètre, parité i18n, garde anti-sur-promesse
> `mobile_field`), `TradeVerticalConsistencyTest.php` (conformité manifest,
> données sensibles déclarées, les 3 points d'enregistrement obligatoires dont
> `Company::KNOWN_MODULES`, bijection rôles ↔ permissions),
> `api/tests/Feature/Security/TradeTenantIsolationTest.php` (6 tests d'isolation
> inter-entreprise). **Aucun service payant souscrit, aucune clé inventée** :
> les 4 adaptateurs sont en mode dégradé explicite (`manual`/`null`) et déclarés
> non configurés — voir `docs/architecture/TradeIntegrationAdapters.md`.
> **MAJ 2026-10-13 — #8447 + #8451, « Mon Logiciel » et checklists par profil métier.**
> Surface **web (front/web)** : nouvelle route protégée `/mon-logiciel` (hub
> d'installation des apps du tenant, filtrée par solutions activées via
> `lib/software-catalog.ts` + `getClientModuleAccess`), scène 3D
> `SoftwareConstellation3D` (dynamic import ssr:false), QR d'installation
> (`qrcode`), module de navigation `mon_logiciel` (groupe `general`) et carte
> d'accès sur `/dashboard`. Scénarios Jest : `software-catalog.test.ts`
> (7 cas : filtrage strict par verticale, honnêteté des cibles link/pilot/soon),
> `mon-logiciel/__tests__/page.test.tsx` (4 cas : restaurateur, sans verticale,
> « Bientôt disponible » sans lien mort, modale QR) ; `NextStepsCard.test.tsx`
> étendu (2 cas : liens d'action par étape → écran réel, aucun lien mort pour
> une étape inconnue). Surface **API** : `SeedDefaultSteps` généralise les
> checklists par profil — station-service (station → équipe → rotation → vente,
> étapes équipe réservées aux profils `team`), retail (catalogue → caisse →
> vente), scolarité (campus/année → classes → élèves) + étape optionnelle
> `install_apps` pour les verticales à écrans métier ; clés i18n ×4
> (`api/lang/*/onboarding.php`). Scénario PHPUnit :
> `SetupInterviewVerticalChecklistsTest` (7 cas : ordres d'étapes, détection
> par flag déclaré ou opérationnel, solo sans étapes d'équipe, absence chez un
> tenant sans verticale). Parité i18n ×4 vérifiée par `sync-web.js`
> (I18N_SYNC_WEB_OK).
> **MAJ 2026-10-13 — #8440/#8441, espace « Prendre des parts » + contexte entreprise du paramétrage fundraising.**
> Surface **API HTTP** : type `marketing_leads` `equity_interest` (CHECK étendue,
> migration publique `2026_10_13_000100_8440`) capturé par `POST /marketing/leads`
> (canal serveur-à-serveur vitrine, inchangé) ; `GET /platform/marketing/leads`
> (+ alias `/admin`) expose `meta.type_status_counts` quand `type` est filtré ;
> nouveau `PATCH /platform/marketing/leads/{id}` (+ alias `/admin`) — qualification
> (statut `new/contacted/qualified/converted/rejected` + note interne), garde
> `crm.view`. Scénarios automatisés : `EquityInterestLeadApiTest` (8 tests —
> capture, rejet de type inconnu, compteurs scopés vs globaux, transitions,
> 422/404, RBAC 401/403).
> Surface **API HTTP (#8441)** : `GET/PUT /platform/companies/{company}/fundraising/settings`
> (groupe `auth:super_admin_api`, `platform.permission:companies.manage`) —
> lecture/écriture de la ligne du tenant ciblé via `TenantManager::withinTenant()`,
> fail-closed 403 verticale inactive / 404 entreprise inconnue. Scénarios :
> `PlatformFundraisingSettingsApiTest` (6 tests — défauts sans persistance,
> écriture dans le schema cible, isolation réelle entre deux entreprises,
> 403/404, RBAC).
> Surface **web vitrine** (`front/web`) : page `(landing)/investir` (×4 locales
> inline + SEO `getPageMetadata('investir')`, cadrage non-offre-publique,
> formulaire zod `equityFormSchema`, honeypot + `/api/forms/equity`) ; entrée
> footer discrète « Devenir actionnaire » (`getFooterHref(3,3)`, libellés ×4
> `vitrine-locale.ts`). Scénarios Jest : `validation.test.ts` (equityFormSchema
> ×4 locales : tranche liste fermée, consentement, messages localisés) et
> `footer-links.test.ts` (href 3-3).
> Surface **web admin** (`front/admin-dashboard`) : écran
> `views/equity/EquityInterestsView.vue` (route `/equity-interests`, nav groupe
> « Entreprises », perm `crm.view`) — onglets statut à compteurs scopés type,
> recherche email, lignes extensibles (message, note interne, transitions) ;
> `FundraisingSettingsView.vue` consomme `?company=` (endpoints platform avec
> contexte, routes tenant sans contexte). i18n ×4 (`navigation.equityInterests`
> + bloc `equityInterests.*`). Parité i18n vérifiée par sync-web/sync-mobile +
> `versions.json` recalculé.
> **MAJ 2026-10-13 — #8435, espace station-service (BC-15 FUEL) : hub web + CRUD responsable.**
> Surface **web (front/web)** : nouvelle route protégée `/fuel` (hub) et sous-routes
> `/fuel/stations`, `/fuel/team`, `/fuel/rotations`, `/fuel/stock`, `/fuel/sales`
> (`front/web/src/app/(dashboard)/fuel/**`), branchées sur l'API existante
> `/fuel-station/*` (stations, shifts + affectations, stock, caisse/ventes) ;
> `/fuel/pump` (poste pompiste) conservé. Scénarios Jest : `fuel-home-page.test.tsx`
> (tuiles + état vide ; KPIs stations/shifts/incidents), `fuel-pump-page.test.tsx`
> (conservé). Parité i18n ×4 (`fuel.*` fr/en/ar/tr) vérifiée par `shared/i18n/validators/validate.js`.
> Aucun changement d'API : le backend FuelStation était déjà complet (gate `module.fuel`).
> **MAJ 2026-10-11 — #8394, vague vitrine « retours propriétaire » (#8384-#8392).**
> **MAJ 2026-10-11 — #8423 (PR #8425), marketplace publique VTC/taxi + corrections BC-34/35 (lot 2 geo/VTC).**
> Surface **API HTTP** (`api/routes/modules/vtc.php`) : groupe public `throttle:shop-public`
> `/v1/public/vtc/marketplace/*` — `GET /operators` (annuaire opt-in, DTO strict),
> `POST /estimate` (devis borné par slug, 404 uniforme), `POST /rides` (réservation invitée
> idempotente, `tracking_code` present-once, throttle renforcé), `GET /rides/{reference}?code=`
> (suivi invité, 404 uniforme anti-énumération) ; annulation multi-acteurs M-21 —
> `POST /v1/vtc/driver/rides/{id}/cancel` (chauffeur, SA course après acceptation) et
> `POST /v1/vtc/dispatch/rides/{id}/cancel` (dispatcher/admin, acteur tracé) ; listeners
> notifications M-20 sur `VtcRideAccepted/Expired/Completed` (push plateforme, dégradation
> gracieuse). Scénarios automatisés : `VtcMarketplacePublicApiTest` (5 tests — annuaire
> borné, devis, idempotence, suivi, coupure au retrait de l'annuaire), `VtcRideCancellationApiTest`
> (10 tests — acteurs, libération chauffeur, gardes 403/401/409/422), `VtcRideNotificationTest`
> (5 tests — locale destinataire, invité ignoré, double notification clôture).
> Surface **web travel** (`front/travel-web`) : parcours public « VTC & Taxi » — pages
> `/vtc` (opérateurs → géoloc/coordonnées → devis → réservation invitée idempotente) et
> `/vtc/suivi` (référence + code) ; proxy same-origin ouvert de façon bornée à
> `public/vtc/marketplace/*` (fail-closed, jamais de Bearer) ; preuve locale typecheck /
> eslint / vitest 15/15 / `next build`. Surface **mobile** (`leopardo_core`) : clés ARB
> propagées par `sync-mobile.js` uniquement (`vtcDrivernotlinked`,
> `vtcMarketplacetrackingcoderequired` ×4 locales — catalogue partagé), aucun contrat ni
> parcours mobile modifié — **aucun scénario mobile nouveau requis**.
> Surface **web admin** (`front/admin-dashboard/src/i18n/locales/*.json`) : catalogues
> resynchronisés après ajout de la clé navbar `vitrine.nav.comparer` et passage de la
> baseline à « Business OS » seule (×4 locales) — aucun parcours admin modifié, aucun
> nouveau scénario requis (clés de traduction additives, parité ×4 vérifiée par la
> garde i18n). Surface **mobile** (`leopardo_core`) : tokens `emerald50…emerald950`
> ajoutés dans `app_colors.dart` (parité L.07 avec COULEURS.md) — constantes additives,
> détail dans le journal de `SCENARIOS_TEST_MOBILE_FLUTTER.md`. Surface **vitrine
> (front/web)** : hors périmètre de cette garde — vérifications dédiées dans la PR
> (typecheck, 621 tests vitrine, build 163 routes, E2E funnel, captures navigateur).
> **MAJ 2026-10-10 — #8349 (épic, PR #8377), core géospatial `geo` (BC-34) + verticale VTC/taxi `vtc` (BC-35).**
> Surface **API HTTP** : deux modules nouveaux, chacun derrière son middleware de feature
> flag fail-closed (`module.geo` / `module.vtc`, alias `api/bootstrap/app.php`) —
> `api/routes/modules/geo.php` : `GET /v1/geo/ping`, `POST /v1/geo/distance`,
> `GET /v1/geo/nearest` (registry opt-in `geo.searchables`), `GET /v1/geo/capabilities`
> (admin tenant) ; `api/routes/modules/vtc.php` : passager (`POST /v1/vtc/rides/estimate`,
> `POST /v1/vtc/rides` idempotent par `idempotency_key`, `GET /v1/vtc/rides/{id}`,
> `POST /v1/vtc/rides/{id}/cancel`), chauffeur rôle `vtc.driver` (`GET /v1/vtc/driver/offers`,
> `POST /v1/vtc/driver/rides/{id}/accept|decline|arrive|start|complete`,
> `POST /v1/vtc/driver/position` throttle 30/min + idempotent sur (driver, recorded_at),
> `POST /v1/vtc/driver/availability`), dispatcher rôle `vtc.dispatcher`
> (`GET /v1/vtc/dispatch/rides|drivers`, polling v1), admin rôle `vtc.admin` (CRUD
> `vtc/fare-profiles`, `vtc/vehicles`, `vtc/drivers`). Règle d'architecture : `vtc` ne
> calcule jamais une distance — tout passe par le core `geo` (contrats
> `App\Shared\Contracts\Geo`), garde CI `check-geo-single-usage.sh` en warning (GEO-07
> #8356) puis bloquante (GEO-07b #8380). Pilote GEO-06 (#8355) : annuaire public Restaurant
> servi par `geo` derrière le flag `GEO_PILOT_RESTAURANT_DIRECTORY`. RBAC vtc deny-by-default
> (matrice `docs/architecture/VTC_RBAC.md`) ; `openapi.yaml` aligné (4 endpoints geo + 27
> endpoints vtc documentés). Scénarios automatisés : `api/tests/Unit/Geo` (GeoPoint,
> calculateurs PostGIS/Haversine, registry), `api/tests/Unit/Vtc` (state machine, tarif,
> value objects), `api/tests/Feature/Geo` (distance, nearest, capabilities, flags, pilote
> Restaurant `RestaurantPublicDirectoryGeoPilotTest`), `api/tests/Feature/Vtc`
> (activation, schéma tenant, manifest, API passager `VtcPassengerApiTest`, dispatch
> `VtcDispatchTest` + admin `VtcDispatchAdminApiTest`, API chauffeur `VtcDriverApiTest`,
> flags) — isolation tenant croisée et 403 `MODULE_DISABLED` flag off couverts dans
> chaque suite.

> **MAJ 2026-09-29 — #8278, routes manager du cycle de vie kiosk restaurées (revoke / rotate-token, Closes #8241).**
> Surface **API HTTP** (`api/routes/modules/rh.php`) : deux routes nouvelles —
> `POST /api/v1/kiosks/{kiosk}/revoke` et `POST /api/v1/kiosks/{kiosk}/rotate-token`
> (manager uniquement : middleware `api.manager` + `whereNumber('kiosk')`, même pile
> `auth:sanctum`/`tenant` que `POST /kiosks` register) — exposant les méthodes BIO-005
> (#6766) de `KioskController` qui existaient sans routage (404 sur tout appel manager).
> Comportement : la révocation passe le kiosk en `status=revoked` (punch/sync de
> l'appareil ensuite refusés en 403 `DEVICE_REVOKED`, audit biométrique `device.revoked`) ;
> la rotation invalide l'ancien secret (401) et retourne le nouveau en clair une seule
> fois (`data.sync_token`, audit `device.token_rotated`) ; résolution scopée tenant
> (`currentCompany()` + `whereKey`). `openapi.yaml` aligné (2 endpoints documentés).
> Scénarios automatisés : `api/tests/Feature/Attendance/KioskDeviceLifecycleTest.php`
> (`revoked kiosk cannot punch nor sync`, `rotate token invalidates the old secret`) et
> `api/tests/Feature/Attendance/KioskSyncStatusEndpointTest.php` (`revoked device gets
> 403 device revoked`) — 3 tests chroniquement ROUGES sur `main` (404 route absente),
> désormais verts (8/8 sur les deux fichiers, 59 assertions).
> **MAJ 2026-09-26 — #8188, contrat RBAC du référentiel Fuel rendu explicite (option 2 de l'issue).**
> Surface **API HTTP** : aucune route nouvelle ni contrat d'URL modifié — le changement
> touche `api/routes/modules/fuel_station.php` en commentaire uniquement (docblock ; la
> garde gouvernance surveille `api/routes/**`, d'où cette note). Le contrat effectif est
> désormais documenté sans zone grise : lecture du référentiel (stations/sites/équipements/
> produits) ouverte à tout employé authentifié du tenant (#7439 — l'écran pompiste web/mobile
> appelle ces endpoints en employé simple), écriture réservée au groupe `api.manager`,
> signalement d'incident (`POST /fuel-station/incidents`) ouvert à tout employé, liste et
> gestion des incidents réservées au manager via la policy effective. Scénarios automatisés :
> `api/tests/Feature/FuelStation/FuelStationReferentialTest.php` — le test obsolète
> `referential_requires_manager_role` (ROUGE sur `main` : 200 vs 403) est remplacé par
> `test_referential_read_is_open_to_any_tenant_employee` (lecture employé 200 + contenu
> asserté, flag actif) et `test_referential_write_requires_manager_role` (POST/PUT/DELETE →
> 403 `MANAGER_REQUIRED`, échoue si la garde `api.manager` disparaît des écritures).

> **MAJ 2026-09-26 — #8181, matrice RBAC fine `delivery.role` câblée sur les routes Delivery (BC-26-D05).**
> Surface **API HTTP** : aucune route nouvelle ni contrat d'URL modifié — les gardes changent
> (`api/routes/modules/delivery.php` : `api.manager` générique remplacé par la matrice fine
> `delivery.role:dispatcher|manager|rider` selon `docs/architecture/DELIVERY_RBAC.md`) ;
> alias `delivery.role` enregistré dans `api/bootstrap/app.php`. Comportement : deny-by-default
> — un manager hors rôle (ex. marketing) reçoit 403 `DELIVERY_ROLE_REQUIRED` ;
> `DeliveryEventController::store` invoque `DeliveryPolicy::store` (rider borné à SES tournées).
> Scénarios automatisés : `api/tests/Feature/Delivery/DeliveryRbacTest.php` et
> `DeliveryRbacMatrixTest.php` (7 tests précédemment ROUGES dans le sens sécurité, désormais verts),
> suite `tests/Feature/Delivery` complète 110/110 ; deux assertions d'ancien contrat mises à jour
> (`DeliveryApiTest` code d'erreur canonique, `DeliveryRbacMatrixTest` rider sur livraison planifiée).

> **MAJ 2026-09-26 — #8096, session acheteur marketplace en cookie HttpOnly.**
> Surface **API** (module Retail, `routes/modules/market_public.php`) : la session acheteur
> migre du jeton porté en `localStorage` vers un cookie `market_buyer_session`
> (`HttpOnly; Secure; SameSite=None`, chemin borné `/api/v1/public/market`, valeur opaque
> hashée côté serveur — tranche cross-stack de #8022). `config/cors.php` autorise les
> credentials sur la surface publique marché ; `openapi.yaml` aligné. Scénarios automatisés :
> `api/tests/Feature/Retail/RetailMarketBuyerAccountTest.php` (attributs du cookie, refus
> sans session, isolation des commandes par acheteur). Surfaces web admin et mobile : aucune.

> **MAJ 2026-09-26 — #8164, 0 email en clair dans les logs d'`AuthService` + rétention de `ai_tool_executions` (suite #8144).**
> Surface **API HTTP** : aucune route nouvelle ni contrat modifié — le changement touche
> `api/routes/console.php` en commentaire uniquement (la garde gouvernance surveille
> `api/routes/**`, d'où cette note) : la planification existante `ai:purge-audit-logs`
> (quotidienne 04:45) est inchangée, sa portée documentée couvre désormais deux tables.
> Côté comportement : les 5 points de log d'`AuthService` qui journalisaient l'email en clair
> journalisent `email_hash` (sha256 tronqué, convention #8144) ; la commande
> `ai:purge-audit-logs` purge désormais AUSSI `ai_tool_executions` (même rétention 90 j,
> mêmes options `--older-than`/`--company`/`--dry-run`, idempotence — chaque table purgée
> indépendamment). Scénarios automatisés : `api/tests/Unit/AuthServiceLogsPiiTest.php`
> (aucune PII en clair dans les logs de résolution auth, hash exact journalisé) et
> `api/tests/Feature/AI/PurgeAiAuditLogsTest.php` étendu (seuil commun sur
> `ai_tool_executions`, scope `--company`, `--dry-run`, idempotence sur les deux tables).
> Registre RGPD : `docs/RGPD_REGISTRE_TRAITEMENTS.md` mis à jour.

> **MAJ 2026-09-26 — #8144 BOS-002, rétention des logs d'audit IA + logs trial sans PII.**
> Surface **API HTTP** : aucune route nouvelle ni contrat modifié — le changement touche
> `api/routes/console.php` (la garde gouvernance surveille `api/routes/**`, d'où cette note) :
> nouvelle entrée planifiée `ai:purge-audit-logs` (quotidienne 04:45, `withoutOverlapping`,
> idempotente) purgeant les logs d'audit IA au-delà de `ai.audit_log_retention_days`
> (défaut 90 j). Le parcours trial (`SelfServiceTrialController`) ne journalise plus les
> e-mails en clair (hachage). Scénarios automatisés : `api/tests/Feature/AI/PurgeAiAuditLogsTest.php`
> (purge bornée, idempotence) et `api/tests/Feature/Billing/TrialSignupLogsPiiTest.php`
> (aucune PII en clair dans les logs du parcours trial). Registre RGPD :
> `docs/RGPD_REGISTRE_TRAITEMENTS.md` mis à jour.

> **MAJ 2026-09-26 — #8139 BOS-006A, scheduler à source unique.** Surface **API HTTP** :
> aucune (aucune route, middleware ou contrat modifié) — le changement touche `api/routes/console.php`
> et `api/bootstrap/app.php` (la garde gouvernance surveille `api/routes/**`, d'où cette note).
> Le bloc `withSchedule()` de `bootstrap/app.php` est **supprimé** : les 8 commandes planifiées en
> double (horaires/paramètres contradictoires — `leave:accrue` daily+monthly,
> `billing:generate-invoices` 02:00+03:00, deux expireurs Travel publiant des événements distincts)
> vivent désormais **uniquement** dans `routes/console.php`, avec `withoutOverlapping` sur les
> commandes sensibles. `TravelExpireBookingsCommand` (legacy) est retiré : seul
> `travel:expire-pending-bookings` publie les expirations (`travel.booking.cancelled.v1`).
> Scénarios automatisés : `api/tests/Feature/Console/SchedulerUniquenessTest.php` (aucune commande
> en double dans le scheduler, expireur legacy absent, verrous anti-chevauchement présents).
> Audit rétrospectif des données historiques (doubles exécutions passées) : tracé séparément
> dans **#8140 (BOS-006B)**.

> ⚠️ **MAJ 2026-08-17** : l'arborescence mobile historique `front/mobile/` a été supprimée (PR #754).
> Les apps vivent sous `front/mobile_apps/*` ; les jobs mobile de CI sont gérés par `mobile-apps-ci.yml`.
> Les mentions `front/mobile_apps/**` ci-dessous (ex-`front/mobile/**`) sont historiques et ne peuvent plus se déclencher.

> **MAJ 2026-09-23 — #7986 tranche 4 (convention `Controllers/`).** Surface **API** : 5
> controllers déplacés sous `Interfaces/Api/V1/Controllers/` (`SolutionSurveyController`,
> `KioskEnrollmentController`, `CabinetFolderController`, `CabinetDocumentController`,
> `CabinetShareController`) — namespaces et imports de `routes/modules/{solutions,rh,cabinet}.php`
> mis à jour ; **aucune route, aucun middleware ni contrat modifié** (déplacement mécanique,
> vérifié par scan exhaustif des références FQCN). Doublon mort
> `Core/Solutions/.../PlatformSolutionSurveyStatsController` supprimé (0 référence — le vivant
> est `Modules/Platform/.../Controllers/PlatformSolutionSurveyStatsController`). Scénarios :
> couverts par les suites existantes (`CabinetDocumentControllerTest`, tests kiosk/solutions) —
> aucun comportement nouveau à scénariser.

> **MAJ 2026-09-21 — #8020 (suivi #8005/#7973), dernière tranche de la matrice `platform.permission`.**
> Surface **API** : les 13 routes `/admin` et `/platform` restées sans garde sont armées sans
> ajouter de permission — `GET /platform/country-defaults` → `companies.view` ;
> `GET /admin/ai/conversations`, `GET /admin/ai/conversations/{conversation}/messages`,
> `POST /admin/ai/chat`, `GET /admin/hr-reports`, `GET /admin/fleet/alerts`,
> `GET /admin/training/{courses,sessions,enrollments}` → `companies.manage` (contenus tenant
> cross-tenant, admin seul) ; `GET /admin/solutions/survey-stats` → `crm.view` ;
> `GET /admin/platform/ai/{monitoring,health}` et `POST /admin/payroll/simulate` → `settings.manage`.
> Scénarios automatisés : `api/tests/Feature/Platform/PlatformPermissionMatrixDeploymentTest.php`
> étendu (table route → permission vérifiée sur la définition réelle via `gatherMiddleware()`,
> 403 des rôles délégués, passage des rôles habilités ; sur schéma MVP `CreatesMvpSchema`).
> Surfaces web admin (les sondes `navigation.js` de `/fleet`, `/training` et
> `/solutions/survey-stats` restent `permission: null` — alignement à suivre, sans impact
> sécurité) et mobile : aucune nouvelle.

> **MAJ 2026-09-21 — lot BC-01 PLATFORM #7973/#7974/#7975/#7977/#7978 (PR #8005), critiques
> d'audit plateforme.** Surface **API** : (1) #7973 — la matrice `platform.permission` (#7553)
> est déployée sur les blocs qui l'avaient perdue : écriture `/platform/plans` → `plans.manage`
> (nouvelle permission, finance + admin), purge tenant (`deletion-inventory` + DELETE
> `/platform/companies/{company}`) → `companies.manage`, TOUT le bloc `/admin/webhooks*` (lecture
> comprise) → `webhooks.manage` (nouvelle, admin + ops), bloc réglages `/admin/*` (templates
> e-mail, IA, oauth-config marketing, fériés FR/islamiques, tax-slabs, social-contributions,
> rate-validation) → `settings.manage` (nouvelle, admin seul), `/admin/payroll/audit*` →
> `payroll.view` (nouvelle, finance + ops — support exclu), `/admin/users` GET → `users.view` /
> PATCH → `users.manage`, alias `/admin/edge-nodes` réalignés sur `edge.manage` ; (2) #7974 —
> `leopardo:migrate --fresh` refusé sec en production (exit 1) et soumis à confirmation
> interactive hors production sauf `--force` (nouvelle option) ; (3) #7975 — migration
> `create_onboarding_progresses_table` déplacée de la racine (orpheline du runner) vers
> `tenant/` avec garde `schemaTableExists`. Scénarios automatisés :
> `api/tests/Feature/Platform/PlatformPermissionMatrixDeploymentTest.php` (matrice rôle →
> nouvelles permissions + pour chaque bloc touché : 403 pour un rôle délégué sans permission,
> passage du middleware pour un rôle habilité — sur schéma MVP `CreatesMvpSchema`),
> `api/tests/Feature/Database/LeopardoMigrateFreshGuardTest.php` (refus production + abandon
> sans confirmation interactive, base intacte), `api/tests/Feature/Database/LeopardoMigrateRunnerTest.php`
> réaligné (rejouabilité du runner). Surfaces web admin (sondes de menu `navigation.js`
> alignées), mobile : aucune nouvelle — couverture existante inchangée.

> **MAJ 2026-09-20 — #7866 (API #7865), pop-up d'import du jeu de données de démonstration à la
> première entrée dans l'espace.** Surface **web client** : `DemoDataPrompt.tsx` monté dans le
> layout dashboard APRÈS l'écran de bienvenue (#7604) et l'entretien de préparation (#7493) —
> pré-garde `shouldShowDemoDataPrompt` (RBAC `principal`/`rh` + verticale active + metadata
> `demo_data`), serveur source de vérité (`GET /demo-data`), import séquentiel des kits
> proposables, « Plus tard » sans persistance, « Non merci » persisté (`POST dismiss`).
> Surface **API** : aucune dans cette PR (contrat #7865 livré séparément). Surfaces **web
> admin** et **mobile** : aucun écran ni parcours modifié — seules les **valeurs traduites**
> des catalogues (`front/admin-dashboard/src/i18n/locales/*.json`, ARB `leopardo_core`) sont
> propagées depuis le catalogue partagé (`shared/i18n`, clés `demoDataPrompt.*`).
> Non-régression :
> `front/web/src/modules/onboarding/components/__tests__/DemoDataPrompt.test.tsx` (10 cas —
> table de vérité de la pré-garde, rien sans kit serveur, import + succès, import multiple
> séquentiel, dismiss persisté, « Plus tard » sans appel serveur, erreur d'import non
> bloquante).

> **MAJ 2026-09-19 — lot BC-21 paiements #7726/#7727 (PR #7732).**
> Surface **API** : (1) endpoints admin plateforme `GET/PUT /platform/billing/gateways` et
> `POST /platform/billing/gateways/{gateway}/test` (permission `platform.permission:billing.manage`,
> secrets write-only masqués) — configuration des passerelles PSP (stripe|chargily) stockée
> chiffrée en BDD avec précédence BDD → fallback env ; (2) endpoints tenant
> `GET/POST/PUT/DELETE /billing/payment-profiles` + `/{id}/activate` (réservés au `principal`)
> — profils de paiement du tenant (stripe_keys|bank_account|mobile_money) et routage des
> encaissements Accounting vers les clés Stripe DU tenant quand un profil `stripe_keys` est actif.
> Scénarios automatisés : `api/tests/Feature/Platform/PlatformPaymentGatewayAdminApiTest`
> (7 cas — masquage, chiffrement au repos, précédence BDD/env, write-only, 403, webhook secret,
> ping sans fuite) et `api/tests/Feature/Billing/TenantPaymentProfileApiTest` (6 cas — CRUD/activation,
> 403 non-principal, isolation cross-tenant, routage checkout tenant + fallback plateforme).
> Surfaces web : écran admin Vue « Passerelles de paiement » et page client « Encaissements »
> (couverts par ESLint/tsc/Jest du lot). Surface mobile : aucune.

> **MAJ 2026-09-19 — #7739 (épic #7736), comptes clients grand public du site marketplace (PR empilée sur #7738/#7781).**
> Surface **API** : `/api/v1/public/travel/marketplace/account/*` — inscription/connexion
> (`POST /register`, `POST /login`, throttle `auth-sensitive` + verrouillage 5 échecs/15 min),
> surface connectée sur guard Sanctum DÉDIÉ `travel_customer` (`POST /logout`, `GET /me`,
> `GET /bookings` — « mes réservations » cross-agences STRICTEMENT bornées par
> `customer_account_id`). Rattachement des réservations marketplace au compte : à l'inscription
> (par e-mail de contact, insensible à la casse, réservations orphelines uniquement) et à la
> création (client connecté sur `POST /bookings`) ; checkout invité préservé. Contrat documenté
> dans `api/openapi.yaml` (+5 paths). Scénarios automatisés :
> `api/tests/Feature/Travel/TravelCustomerAccountApiTest.php` (6 cas Feature multi-tenant :
> hash du mot de passe + revendication par e-mail sur 2 agences, verrouillage login,
> me/logout sur guard dédié, isolation stricte entre deux clients, rattachement à la création
> vs invité non rattaché, mots de passe faibles rejetés).
> Surface **web** : `front/travel-web` — pages `/account/login`, `/account/register`,
> `/account` (profil + réservations + déconnexion), pré-remplissage checkout, proxy
> same-origin relayant `Authorization` uniquement sur `account/*` + `bookings` (vérifié par
> `tsc`/`eslint` ; pas de suite e2e travel-web à ce stade de l'épic).

> **MAJ 2026-09-19 — lot BC-17 RETAIL #7672–#7675 (PR #7718), le module vendeur devient actif.**
> Surface **API** : nouveau préfixe `/v1/retail` (flag tenant `retail`, middleware `module.retail`,
> fail-closed) — produits/catégories (CRUD + publish/unpublish, SKU/slug uniques par tenant),
> stock (emplacements, niveaux, mouvements tracés, alertes ; toute quantité ne bouge que par
> `RetailStockService::applyMovement`), POS (sessions de caisse à index unique partiel
> `WHERE status='open'`, ventes, paiements idempotents cash|card|mobile — `online` réservé
> e-commerce —, décrément stock à la complétion, contre-mouvements à l'annulation, reçu).
> Scénarios automatisés : `api/tests/Feature/Retail/{RetailApiTest,RetailStockApiTest,
> RetailPosApiTest}.php` — 27 tests / 321 assertions (flag off 403, RBAC écriture, isolation
> tenant 404, SKU dupliqué 422, survente tracée, idempotence paiement, variance de clôture,
> deux sessions fermées coexistent). Surface **web** : espace vendeur `/commerce{,/products,
> /stock,/pos}` gaté par le flag `retail` (Jest + tsc + eslint verts ; helpers monétaires testés
> `commerce-format.test.ts`). Surface **mobile** : aucune — propagation i18n des catalogues
> uniquement (clés `commerce.*` ×4, `sync-mobile.js`).

> **MAJ 2026-09-19 — #7737 (épic #7736), API publique MARKETPLACE inter-agences (PR #7750).**
> Surface **API** : nouvelle surface publique `/api/v1/public/travel/marketplace/*` SANS jeton
> d'agence (throttle `shop-public`) — villes desservies dédupliquées par identité géographique
> (`GET /cities`), recherche agrégée CROSS-TENANT bornée aux agences opt-in (jeton boutique
> actif + feature `travelagency` ; `GET /trips`), détail + plan de sièges résolu PAR TRAJET
> (`GET /trips/{trip}`, 404 fail-closed hors opt-in), réservation déléguée au flux TRAVEL-1001
> dans le tenant du trajet (`POST /bookings`, `booking_source=marketplace`), paiement résolu par
> RÉFÉRENCE (`POST /payments/initiate`, ambiguïté cross-tenant → 404) et alias marketplace de la
> surface passager #7395 (suivi/annulation/e-billet par référence + code de validation). Contrat
> documenté dans `api/openapi.yaml` (+8 paths, miroir/SDK régénérés). Scénarios automatisés :
> `api/tests/Feature/Travel/TravelMarketplaceApiTest.php` (6 cas Feature multi-tenant :
> agrégation ≥ 2 agences sans jeton, exclusion jeton inactif/feature absente, déduplication des
> villes, 404 fail-closed sur trajet non opt-in, réservation créée chez la BONNE agence et
> invisible ailleurs + idempotence, paiement résolu par référence). La boutique mono-agence
> TRAVEL-1001/1002 est inchangée (non-régression : suites shop/portail passager vertes).
> Surfaces web/mobile : aucune (le front `front/travel-web` est un lot ultérieur de l'épic).
> **MAJ 2026-09-19 — #7713, image de marque du tenant dans l'espace client web (PR #7719), surface
> mobile touchée par propagation i18n uniquement.** La PR ajoute les clés `brandingPage.*` ×4
> locales au catalogue partagé (`shared/i18n/locales/*.json`) ; elles sont propagées par
> `sync-mobile.js` aux catalogues `front/mobile_apps/leopardo_core/lib/l10n/app_*.arb` et par
> `sync-web.js` à `front/admin-dashboard/src/i18n/locales/*.json` — **aucun écran, aucune route
> ni aucun parcours mobile ou admin n'est modifié** (détection par chemin `front/mobile_apps/`
> et `front/admin-dashboard/src/`). L'écran mobile `company_branding` existant reste inchangé
> et ses scénarios (`SCENARIOS_TEST_MOBILE_FLUTTER.md`) restent valides. La surface
> fonctionnelle réellement livrée est **web client** (`/settings/branding`, thème tenant du
> shell) et **PDF API** (helper `App\Support\PdfBranding`, vues invoice/receipt/payslip).
> Non-régression : Jest front/web complet (127 suites) vert, nouveau
> `api/tests/Feature/PdfBrandingTest.php` (5 cas : logo + couleur rendus, rendu inchangé sans
> branding, repli silencieux fichier manquant/couleur invalide, génération payslip binaire).

> **MAJ 2026-09-19 — #7685, R0 du module Communication (BC-29, spec `MODULE_COMMUNICATION_EMAIL_IA.md`).**
> Surface **API** : nouvel endpoint `GET /api/v1/communication/status` (état/santé du squelette
> du module, gardé par la chaîne `throttle:api → auth:sanctum → token.refresh → tenant →
> throttle:api-plan → module.communication`) — le feature flag tenant `communication`
> (nouveau dans `Company::KNOWN_MODULES`, reconstruit par
> `PATCH /platform/companies/{id}/features`) est fail-closed : module inactif → **403
> `FEATURE_NOT_ENABLED`**. Contrat documenté dans `api/openapi.yaml` (tag `Communication`),
> miroir + SDK régénérés. Scénario automatisé :
> `api/tests/Feature/Communication/CommunicationModuleGateTest.php` (6 cas — présence dans
> KNOWN_MODULES, 401 sans auth, 403 flag absent, état 200 complet, isolation du flag par
> tenant, désactivation → 403 immédiat). Surfaces web/mobile : aucune (UI au lot R6).

> **MAJ 2026-09-19 — #7680, dédoublonnage des routes platform (PR de fix RouteCollisionGuard).**
> Surface **API** : suppression de 5 déclarations dupliquées SANS `platform.permission`
> (country/subscription/features de `platform/companies/{company}`) qui masquaient les versions
> protégées — aucune route effective ne change de contrôleur, la granularité RBAC est rétablie.
> Scénario automatisé : `tests/Feature/Security/RouteCollisionGuardTest` (garde existante,
> repasse au vert). Surfaces web/mobile : aucune.

> **MAJ 2026-09-19 — #7666/#7670, alias de supervision `GET /api/health` (PR #7668).**
> Surface **API** : nouvel alias **non versionné** `GET /api/health` → même `HealthController`
> (sonde canonique `GET /api/v1/health`, même throttle `60,1`). Motif : les sondes externes
> (UptimeRobot, Better Uptime, intégrateurs) essaient `/api/health` par convention — vérifié
> 404 en prod le 2026-09-19, conclusion erronée « API morte ». Aucun contrat versionné modifié :
> `/api/v1/health` reste la route canonique (Render `healthCheckPath`,
> `docs/ops/HEALTH_ENDPOINTS.md`). Scénario automatisé :
> `api/tests/Feature/HealthEndpointTest.php::test_unversioned_health_alias_serves_the_same_probe`
> (contrat identique à la sonde canonique). Surfaces web/mobile : aucune.

> **MAJ 2026-09-19 — lot audit vendeur du funnel #7662–#7665/#7669 (PR #7667), surface mobile
> touchée par propagation i18n uniquement.** Le lot corrige la mojibake du catalogue partagé
> (`shared/i18n/locales/*.json`, accents FR / caractères TR) et ajoute les clés
> `vitrine.notFound.*` ×4 ; ces valeurs sont propagées par `sync-mobile.js` aux catalogues
> `front/mobile_apps/leopardo_core/lib/l10n/app_*.arb` — **aucun écran, aucune route ni aucun
> parcours mobile n'est modifié** (détection par chemin `front/mobile_apps/`). Les scénarios
> mobile Flutter existants (`SCENARIOS_TEST_MOBILE_FLUTTER.md`) restent inchangés et valides ;
> la surface fonctionnelle réellement livrée est **web vitrine** : redirects 404 du funnel,
> zones app protégées (`protected-prefixes.ts` + tests), 404 localisée (`not-found.tsx`,
> catalogue `vitrine.notFound.*`) et Navbar accentuée (catalogue inline ×4). Non-régression :
> Jest web (`protected-prefixes.test.ts`), e2e funnel (`funnel-e2e-gate.yml`) verts sur la PR.

> **MAJ 2026-09-18 — #7490, première connexion sans mot de passe en clair (epic #7486).**
> L'e-mail de bienvenue self-service ne contient plus aucun secret : lien magique de
> définition de mot de passe (`/auth/set-password?token=…`, `provisioning_token` à usage
> unique, **TTL 72 h** — `POST /trial/set-password` répond `410 TRIAL_PASSWORD_LINK_EXPIRED`
> au-delà) et **connexion par code** pour les comptes sans mot de passe : nouveaux endpoints
> publics `POST /auth/login-code/request|verify` (réponse générique anti-énumération, code
> 6 chiffres haché en cache 10 min, consommé au premier usage, verrou après 5 échecs,
> canal refermé dès qu'un mot de passe existe, 2FA refusée — `403
> LOGIN_CODE_PASSWORD_REQUIRED`). Contrat documenté dans `api/openapi.yaml` (+2 paths,
> +410 sur set-password, miroir/SDK régénérés). Scénarios API couverts par
> `api/tests/Feature/FirstLoginPasswordlessTest.php` (8 cas Feature : lien magique sans
> secret rendu, 410 après 72 h / 200 avant, envoi du code éligible, réponse générique pour
> un e-mail inconnu, session ouverte + usage unique, verrou 5 échecs, canal refermé après
> définition du mot de passe). Surfaces mobile touchées par **propagation i18n uniquement**
> (`sync-mobile.js`, clés `setPassword.*`/`loginCode.*` ×4) : aucun écran ni parcours
> mobile modifié. Surface web : page publique `/auth/set-password` + mode « code » sur
> `/auth/login` (Jest 125 suites / 1030 tests verts).
> **MAJ 2026-09-18 — lot tunnel d'acquisition #7495/#7496 (epic #7486) : pass copy/a11y ×4
> locales et tracking first-party par étape.** Surface **API** : `POST /api/v1/funnel/events`
> (ingestion server-to-server des jalons du funnel, secret partagé `MARKETING_LEAD_WEBHOOK_TOKEN`,
> liste fermée d'événements, contexte en liste blanche, **aucune PII**) et
> `GET /api/v1/admin/funnel/stats` (`platform.permission:metrics.view` — taux de passage par
> étape en parcours distincts, conversion visite → espace prêt par jour/source, alerte livraison
> OTP) — détail dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`, addendum 2026-09-18. Surface **web
> vitrine** (#7495) : reformulation des écrans du tunnel ×4 locales (`shared/i18n`, propagée par
> les 3 synchronisations), renvoi de code OTP en 1 clic (anti-spam 30 s, annonces `aria-live`),
> focus géré à chaque transition, `prefers-reduced-motion` respecté ; e2e bloquants
> `front/web/e2e/funnel-tracking.spec.ts` exécutés par `.github/workflows/funnel-e2e-gate.yml`.
> Surface **web admin** : nouvelle vue `/crm/acquisition-funnel` (admin-dashboard, nav + palette,
> clés `funnelStats.*`). Surface **mobile** : aucune — seules les valeurs traduites des catalogues
> ARB sont propagées depuis le catalogue partagé (détection par chemin). Non-régression :
> `api/tests/Feature/Marketing/AcquisitionFunnelEventControllerTest.php`,
> `api/tests/Feature/Platform/PlatformAcquisitionFunnelStatsTest.php`, Jest web (renvoi OTP,
> jalons funnel), Playwright `funnel-tracking.spec.ts` (ordre des jalons, corrélation stable,
> attribution conservée, zéro PII, zéro beacon sans consentement).

> **MAJ 2026-09-18 — #7594, vitrine : validation zod localisée ×4 et a11y des formulaires
> publics — surface mobile touchée par propagation i18n uniquement.** Le lot branche les
> schémas zod (`contactFormSchema`/`demoFormSchema`/`newsletterFormSchema`) côté client avec
> messages localisés (clés `forms.validation.*` et `payment.errors.*` du catalogue partagé
> `shared/i18n/locales/*.json`). Ces clés sont propagées par `sync-mobile.js` aux catalogues
> `front/mobile_apps/leopardo_core/lib/l10n/app_*.arb` — **aucun écran, aucune route ni aucun
> parcours mobile n'est modifié**. Les scénarios mobile Flutter existants
> (`SCENARIOS_TEST_MOBILE_FLUTTER.md`) restent inchangés et valides ; la surface fonctionnelle
> réellement ajoutée est **web vitrine** (`site/`, formulaires publics + checkout). Non-régression :
> suites Jest des schémas et helpers carte (123 suites / 1042 tests verts).

> **MAJ 2026-09-13 — BC-27 SHOWCASE (#6862), surface mobile `front/mobile_apps/` touchée
> par propagation i18n uniquement.** Le lot « Site vitrine — module horizontal de l'espace
> client » ajoute 51 clés `showcase.*` au catalogue **partagé**
> (`shared/i18n/locales/*.json`). Ces clés sont propagées par `sync-mobile.js` aux catalogues
> `front/mobile_apps/leopardo_core/lib/l10n/app_*.arb` — **aucun écran, aucune route ni aucun
> parcours mobile n'est modifié** : la garde de gouvernance exige néanmoins la mise à jour de ce
> registre (détection par chemin `front/mobile_apps/`). Les scénarios mobile Flutter existants
> (`SCENARIOS_TEST_MOBILE_FLUTTER.md`) restent inchangés et valides ; la surface fonctionnelle
> réellement ajoutée est **web** (`front/web`, page `/showcase` + rendu public `/vitrine/{slug}`).


> **MAJ 2026-09-14 — #7339, pagination du portefeuille clients.** Le lot « paginer /
> cacher / tuer le N+1 » de `GET /platform/companies/health` arrive **après** #7302
> (PR #7340 mergée `c3cc25c`), qui avait déjà supprimé le N+1 (674 requêtes → 12 pour
> 45 sociétés) et posé le cache 60 s. Ce lot ne refait donc **pas** le N+1 : il ajoute
> la **pagination** (`?page=&per_page=`, plafond 100, `limit` conservé comme alias),
> expose `meta` (`current_page`, `per_page`, `total`, `last_page`, `from`, `to`) et
> **explicite le défaut 20** de `GET /platform/companies` (`meta.per_page`). Aucune
> surface web n'est modifiée : la réponse est **additive**, `CompaniesView` /
> `DashboardView` / `SubscriptionsView` continuent de lire `data.items` / `data.summary`
> sans changement. Spécification :
> `docs/specifications/ISSUE_7339_PLATFORM_COMPANIES_HEALTH_PAGINATION.md`. Scénarios
> API : `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`, section 13. Non-régression :
> `api/tests/Feature/PlatformCompanyHealthApiTest.php`
> (`test_portfolio_exposes_page_metadata_and_disjoint_pages`,
> `test_portfolio_defaults_and_legacy_limit_param_stay_compatible`,
> `test_portfolio_query_count_does_not_grow_with_company_count` — dont le comptage de
> requêtes, faussé par un `DB::listen()` jamais retiré, est réparé).

> **MAJ 2026-09-18 — tranche #7490 (PR #7629), connexion de première fois sans mot de passe
> (code à usage unique) + définition du mot de passe.** Surface **API** (module Auth) :
> `POST /auth/login-code/request` (réponse générique anti-énumération, e-mail
> `LoginCodeMail` ×4 locales, code OTP 6 chiffres, TTL 10 min) et
> `POST /auth/login-code/verify` (verrou applicatif à 5 échecs, code consommé au premier
> usage, même bucket auth-sensitive email+IP que `/auth/login`). Surface **web client** :
> écran de connexion « code reçu par e-mail » (`/auth/login`), page `/auth/set-password`
> (`SetPasswordForm.tsx`) et CTA « Définir mon mot de passe maintenant » de l'écran de
> bienvenue (`WelcomeScreen.tsx`, action `set_password`). Surfaces **web admin** et
> **mobile** : aucun parcours modifié — propagation des seules valeurs traduites depuis
> `shared/i18n` (clés `setPassword.*`, `loginCode.*`). Non-régression :
> `api/tests/Feature/FirstLoginPasswordlessTest.php`,
> `front/web/src/app/api/v1/auth/__tests__/login-code-verify.route.test.ts`,
> `front/web/src/app/auth/set-password/__tests__/SetPasswordForm.test.tsx`.

> **MAJ 2026-09-17 — tranche #7490 (lot #7604), écran de bienvenue de première connexion,
> affiché une seule fois (persisté serveur).** Surface **API** (module Onboarding) :
> `POST /onboarding/welcome-ack` — acquittement idempotent, la date d'origine
> (`metadata.welcome_seen_at`, `public.companies`) n'est **jamais** réécrite au second appel
> (`already_acknowledged: true`). Surface **web client** : `WelcomeScreen.tsx` monté dans le
> layout dashboard, affiché uniquement tant que l'acquittement n'est pas persisté côté serveur
> (un rechargement ou un autre poste ne le re-montre pas). Surfaces **web admin** et **mobile** :
> aucun écran ni parcours modifié — seules les **valeurs traduites** des catalogues
> (`front/admin-dashboard/src/i18n/locales/*.json`, ARB `leopardo_core`) sont propagées depuis le
> catalogue partagé (`shared/i18n`, clés `onboarding.welcome.*`). Non-régression :
> `api/tests/Feature/Onboarding/WelcomeScreenAckTest.php` et
> `front/web/src/modules/onboarding/components/__tests__/WelcomeScreen.test.tsx`.

> **MAJ 2026-09-17 — tranche #7595, les leads vitrine deviennent lisibles par l'admin.**
> Nouvelle lecture **API** (module Platform) : `GET /platform/marketing/leads` et son miroir
> super-admin `GET /admin/marketing/leads` — la table globale `marketing_leads` recevait les
> 5 formulaires publics de la vitrine sans qu'aucune route ne la relise. Paginée (25/p., max
> 100), filtres `type`/`status`/`source`/`search`/`from`/`to` (le `to` sans heure inclut la
> journée entière), tri du plus récent au plus ancien, `meta.status_counts` volontairement
> **global** (compteurs d'onglets). Réservée à la permission plateforme `crm.view` ; la réponse
> expose `payload` et `ip` (arbitrage documenté dans le contrôleur — seule relecture de données
> personnelles prospect). Lecture par `DB::table` sans import du module Marketing (garde #5584).
> Spec OpenAPI + SDK miroir régénérés. Aucune surface web/mobile dans cette tranche (écran admin
> à venir). Non-régression : `api/tests/Feature/PlatformMarketingLeadsReadApiTest.php`.

> **MAJ 2026-09-17 — #7598 (R1 de l'épique #7597), socle « accès aux ressources »
> ressource-scopé.** Nouvelle table tenant `employee_resource_assignments`
> (`company_id` uuid indexé sans FK cross-tenant, `resource_type` clé du registre
> `api/config/resource_types.php`, `access_level` `view` < `operate` < `manage`, unicité
> `employee_id`+`resource_type`+`resource_id`). Helpers sur `Employee` :
> `hasResourceAccess()` / `accessibleResourceIds()` — règle de progressivité : tant qu'un type
> n'est pas assigné dans l'entreprise, comportement historique (même `company_id` + rôle) ;
> dès la première assignation, scoping actif et **fail-closed** pour les non-assignés du type
> (`principal` : tout ; `rh` : lecture seule). Surface **API** (module RH) :
> `GET|PUT /employees/{employee}/resource-assignments` (le `PUT` remplace le jeu complet —
> révocation en un geste ; réservé au principal via `EmployeePolicy`) et
> `GET /resources/{type}` (catalogue assignable, fail-closed sur type inconnu). Messages
> d'erreur ×4 langues (`api/lang/{fr,en,ar,tr}/errors.php`). Aucune surface web admin ni
> mobile modifiée dans cette tranche (écrans R2+). Non-régression :
> `api/tests/Feature/Security/ResourceScopedRbacTest.php`.

> **MAJ 2026-09-18 — #7599/#7600/#7601 (R2-R4 de l'épique #7597), RBAC ressource-scopé
> généralisé.** Surface **API** : les 31 policies RestaurantManager passent sur
> `hasResourceAccess('restaurant_branch', …)` (conditions mortes `'manager'`/`'server'`
> supprimées, listings bornés par `accessibleResourceIds`, COGS/cuisine/mobile scopés) —
> scénarios `api/tests/Feature/Restaurant/RestaurantResourceScopedRbacTest.php` (gérant
> refusé sur l'autre branche, serveur borné à sa branche, non-assigné fail-closed,
> comportement inchangé avant la première assignation) et suites Restaurant réalignées
> (personas = assignations via `tests/Support/AssignsResourceAccess`). Généralisation aux
> verticales (trait Core `ChecksResourceScopedAccess` — Vehicle/FuelStation/EduCampus/
> TravelOffice/TravelStation/Camera) : `api/tests/Feature/Security/
> ResourceScopedVerticalPoliciesTest.php`. Cycle de vie (invitation pré-assignée créée à
> l'activation, vue inverse `GET /resources/{type}/{id}/access`, révocation en cascade au
> départ avec audit conservé, rapport `GET /resource-access/audit` + export CSV) :
> `api/tests/Feature/Security/ResourceAccessLifecycleTest.php`. Surface **web client** :
> panneau « Accès & ressources » par collaborateur dans `employees` (matrice ressources ×
> niveaux, i18n ×4) — couvert par les checks front (tsc/eslint/jest) ; sélecteur de branche
> du dashboard restaurant borné côté serveur. Gardes CI :
> `dev-hub/tools/check-vertical-controller-policies.sh` et
> `dev-hub/tools/check-manager-role-enum.sh` (workflow `resource-rbac-guards.yml`).

> **MAJ 2026-09-17 — #7593, vitrine : consentement cookies, Consent Mode et mentions
> d'information.** Surface **web client (vitrine)** uniquement : bandeau de consentement
> (`ConsentBanner`/`ConsentProvider`/`ConsentScripts`), bouton de réglage persistant, notice
> `FormDataNotice` sous les formulaires publics, pages légales et sitemap — scénarios
> `front/web/e2e/consent.spec.ts` et `front/web/e2e/legal-pages.spec.ts`. Surfaces **web
> admin** et **mobile** : aucun écran ni parcours modifié — seules les **valeurs traduites**
> des catalogues (`front/admin-dashboard/src/i18n/locales/*.json`, ARB `leopardo_core`) sont
> propagées depuis le catalogue partagé (`shared/i18n`, clés `consent.*`/`legal.*`), la garde
> de gouvernance exigeant néanmoins cette entrée (détection par chemin).

> **MAJ 2026-09-17 — #7609, seau de throttle dédié pour `/trial/verify`.**
> `api/routes/api.php` sort `/trial/verify` **et** `/trial/set-password` du seau partagé
> `throttle:5,15` et les place derrière un limiteur nommé **`throttle:trial-verify`**
> (10 requêtes / 15 min, clé `email|IP`, déclaré dans
> `api/app/Providers/AppServiceProvider.php`). Motif mesuré : avec le seau partagé, la
> 5e vérification recevait le `429 TOO_MANY_REQUESTS` du **throttle** *avant* d'atteindre le
> **verrou applicatif** (5 échecs → `otp_locked_until`, réponse `OTP_TOO_MANY_ATTEMPTS`) —
> le verrou était donc inatteignable côté utilisateur, et un compte verrouillé ne pouvait
> plus définir son mot de passe. `POST /trial/signup` **conserve** sa garde anti-spam
> `throttle:5,15` (verrouillée par un test). La clé par e-mail évite qu'un poste derrière un
> NAT étrangle tous les inscrits. Surface **API** uniquement : aucun parcours web admin ni
> mobile modifié. Non-régression : `api/tests/Feature/SelfServiceTrialTest.php`.

> **MAJ 2026-09-13 — #7302, cause racine de la lenteur du portefeuille clients.** Le lot
> « le portefeuille ne recalcule plus la santé société par société » supprime le N+1 de
> `GET /platform/companies/health` (674 requêtes → 12 pour 45 sociétés) et met le résultat en
> cache 60 s. Le découplage de **l'affichage** (annuaire d'abord, scoring en tâche de fond) a
> déjà été livré par ailleurs — ce lot n'y revient pas, il ne fait que :
> `front/admin-dashboard/src/views/companies/CompaniesView.vue` transmet `?refresh=1` au clic sur
> « Actualiser » (un rafraîchissement explicite ne doit pas resservir une valeur mise en cache) et
> rafraîchit un commentaire devenu faux (« ~25 s à chaud »). Scénario détaillé :
> `SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md`, section 12. Non-régression API :
> `api/tests/Feature/PlatformCompanyHealthApiTest.php`
> (`test_portfolio_query_count_does_not_grow_with_company_count`,
> `test_portfolio_and_company_detail_agree_on_shared_metrics`).

> **MAJ 2026-09-13 — déblocage de l'onboarding (#7320), surface web admin touchée
> par propagation i18n uniquement.** Le lot « création de département et de
> collaborateur depuis la page Équipe + lien d'action depuis l'assistant »
> ajoute 4 clés `employees.*` au catalogue **partagé**
> (`shared/i18n/locales/*.json`). Ces clés sont propagées par `sync-web.js` aux
> dictionnaires `front/admin-dashboard/src/i18n/locales/` — **aucun écran,
> aucune route ni aucun parcours admin n'est modifié** : la garde de gouvernance
> exige néanmoins la mise à jour de ce registre (détection par chemin
> `front/admin-dashboard/src/`). Les scénarios web admin existants
> (`SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md`, section « Extension i18n
> enterprise ») restent inchangés et valides. La surface fonctionnelle
> réellement modifiée est **web client** (`front/web/src/app/(dashboard)/employees`
> et `front/web/src/modules/onboarding`).


> **MAJ 2026-09-13 — #7300, alignement des progressions d'onboarding.** Le
> lot « une seule source de vérité pour la progression d'onboarding » modifie la
> **fiche Entreprise** du back-office (**vraie** évolution d'UI, pas une simple
> propagation i18n) : la carte Onboarding affiche désormais la progression
> canonique (`onboarding_steps`) — la même que le client —, « — » quand la
> checklist n'est pas amorcée, et l'« Adoption terrain » observée en second
> libellé. Scénario détaillé : `SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md`,
> section 11. Non-régression API :
> `api/tests/Feature/Onboarding/OnboardingProgressAlignmentTest.php`.

# REGISTRE DES SCENARIOS DE TESTS

> ⚠️ **MAJ 2026-08-17** : les références à `front/mobile/` ci-dessous sont obsolètes
> (chemin supprimé par la PR #754 du 2026-06-13). Le chemin actuel est `front/mobile_apps/`
> (`leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_platform_admin`, `leopardo_marketing`).

## Objectif 
  
Fournir une source de verite unique  pour savoir:

- quelles surfaces fonctionnelles doivent etre testees
- dans quel document de scenarios elles sont decrites
- quel workflow CI les execute
- quels artefacts doivent etre produits avant un deploiement

## Regle de gouvernance

Toute nouvelle fonctionnalite, extension de parcours critique ou changement de comportement dans:

- `api/`
- `front/mobile_apps/`
- `front/admin-dashboard/`

doit mettre a jour:

1. le document de scenarios du domaine
2. ou ce registre si le domaine, le workflow, les artefacts ou la criticite changent

Le workflow `Governance Gates` bloque la PR si la surface fonctionnelle change sans mise a jour du registre ou du document de scenarios associe.

## Matrice canonique

| Domaine | Base de scenarios | Workflow source de verite | Artefacts minimums | Gate de deploiement |
|---|---|---|---|---|
| API backend | `docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md` | `Tests - Leopardo RH` | JUnit unit/feature, logs, quality summary, coverage clover + HTML | Obligatoire |
| Mobile Flutter | `docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md` | `Tests - Leopardo RH` | `test-results.json`, `lcov.info`, quality summary, smoke APK | Obligatoire si `front/mobile_apps/**` change |
| Web admin | `docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md` | `Web CI - Leopardo Admin` | rapport Playwright HTML, JUnit Playwright, traces, screenshots, videos en echec | Obligatoire si `front/admin-dashboard/**` change |
| Web vitrine / manager | `front/web/src/modules/vitrine/` + `CHANGELOG.md` | `Web Marketing CI - Leopardo Public` | lint Next.js, build Next.js, locale rail valide, metadata stables | Obligatoire si `front/web/**` change |
| Gouvernance repo | `tools/check-governance.ps1` + ce registre | `Tests - Leopardo RH` | journal CI, verifications changelog/scenarios | Obligatoire |
| Deploiement main | `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md` | `Deploy - Leopardo RH` | healthcheck post-deploy, rollback hook si echec | Strictement bloque tant que les workflows requis ne sont pas verts |
| EduManager BC-16 | `docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md` (Note 2026-08-31) | `Tests - Leopardo RH` | `api/tests/Feature/EduManager/*` (JUnit) | Obligatoire |
| Release readiness | `docs/validation/RELEASE_READINESS_GATE.md` | GitHub Actions + `dev-hub/tools/release-readiness.ps1` | rapport readiness, inventaire tests, statut go/no-go | Obligatoire avant declaration production-ready |

## Definition "tests concluants"

Un SHA est deployable seulement si:

1. `Tests - Leopardo RH` est `success`
2. `Web CI - Leopardo Admin` est `success` si le SHA touche `front/admin-dashboard/**`
3. `Web Marketing CI - Leopardo Public` est `success` si le SHA touche `front/web/**`
4. les artefacts minimums du domaine existent
5. aucun job critique n'est `failure`, `cancelled` ou `timed_out`

## Politique artefacts

### Backend

- `backend-test-reports`
- `backend-quality-summary`
- `backend-quality-reports`
- `backend-coverage-summary`
- `backend-coverage-reports`

### Mobile

- `mobile-quality-summary`
- `mobile-test-reports`
- APK smoke si build mobile actif

### Web admin

- `playwright-report`
- `test-results/junit.xml`
- traces Playwright sur premier retry
- videos Playwright retenues en echec

### Web vitrine / manager

- logs de lint Next.js
- logs de build Next.js
- validation du locale rail public et des metadata au travers du build

## Evolution attendue

Quand un domaine gagne une feature significative, ajouter:

- le scenario nominal
- les refus RBAC / tenant
- les erreurs de validation
- les cas de resilience
- l'artefact CI attendu

## Extension 2026-05-07 - I18N enterprise partage

| Domaine transverse | Base de scenarios | Workflow source de verite | Artefacts minimums | Gate de deploiement |
|---|---|---|---|---|
| I18N partage backend/web/mobile | `SCENARIOS_TEST_API_GITHUB_ACTIONS.md` + `SCENARIOS_TEST_MOBILE_FLUTTER.md` + `SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md` | `I18N Enterprise` + workflows de surface | catalogues generes, checksums `versions.json`, validation locale, endpoint distant syntaxiquement valide | Obligatoire si `shared/i18n/**` ou une surface synchronisee change |

## Notes 2026-05-12

- v4.16.50 : Le seuil coverage mobile par defaut est un ratchet a `21%`, base sur la mesure GitHub Actions `21.85%`. La prochaine cible mobile est `25%`; ne pas augmenter sans nouvelle mesure verte.
- v4.16.78 : Les pages GTM vitrine de la PR #495 doivent rester compatibles avec les composants `CTASection` et le Web Marketing CI doit valider lint + build avant merge.
- v4.16.77 : Les surfaces API integrations/kiosk/ZKTeco de la PR #488 doivent conserver les scenarios device tokens, sync calendrier, heartbeat/sync ZKTeco et extension kiosk (employee info, annonces, leave balance, QR punch), y compris le formatage Pint avant merge.
- v4.16.126 : Plan 21 ajoute les scenarios backend `DemoUserControllerTest` et `ProfileFunctionalReadinessTest` pour verrouiller les personas demo et la matrice d'acces principal/RH/dept/comptable/superviseur/employe. Toute evolution de `/api/v1/demo-users`, des seeders demo ou des sous-roles manager doit garder ces tests comme contrat commercial et QA.
- v4.16.127 : Plan 22 ajoute les scenarios `OpenApiDocsTest` pour `/tester-guide` et `/api-explorer`, plus `DemoUserControllerTest::test_demo_login_recovers_missing_lookup_from_shared_tenant_schema` pour le login demo sans lookup public. Toute evolution de la racine Render, des comptes demo ou de l'explorer API doit garder ces parcours testeur/developpeur accessibles et pre-remplis.
- v4.16.128 : `DemoUserControllerTest::test_demo_users_remain_available_for_public_tester_guides_in_production` verrouille `/api/v1/demo-users` comme contrat public QA, meme si une ancienne config production desactive le mode demo. Les seeders demo doivent verifier les slugs attendus et ne jamais assimiler toute entreprise `shared_tenants` a une demo deja presente.
- v4.16.128 : `DemoUserControllerTest` couvre aussi le contrat complet demo `POST /auth/login` puis `GET /auth/me`. Les tokens Sanctum tenant doivent transporter le contexte schema/email/company/employee pour eviter qu'un `tokenable_id` entier soit resolu dans le mauvais schema shared.
- v4.16.234 : `DemoUserControllerTest::test_demo_once_seeder_keeps_public_super_admin_credentials_usable` verrouille le contrat super-admin demo expose par `/api/v1/demo-users`. Le seeder demo doit resynchroniser le mot de passe public `password123` et retirer le 2FA demo afin que `leopardo_platform_admin` puisse etre teste sur Render.
- v4.16.238 : `GET /api/v1/payroll/mobile-summary` reste dans le perimetre API paie critique. Les scenarios doivent couvrir la tolerance aux colonnes employees optionnelles absentes sur tenants historiques afin d'eviter un 500 mobile manager pendant le lancement.
- v4.16.239 : les soldes paie mobiles doivent rester compatibles avec le contexte `current_company` pose par `TenantMiddleware`. Toute evolution de `PayrollCycleService` doit eviter un rechargement `Company` vulnerable au `search_path` shared PostgreSQL.
- v4.16.240 : `GET /api/v1/employees/{employee}/balance` et `GET /api/v1/payroll/mobile-summary` doivent rester des contrats mobiles resilients : parametre route aligne, isolation tenant et fallback partiel par employe si un calcul individuel echoue.
- v4.16.243 : `GET /api/v1/launch-readiness` fait partie des gates lancement. Il doit utiliser `currentCompany()` en contexte tenant et rester schema-aware sur les champs paie employees pour eviter des faux no-go Render.
- v4.16.245 : le check lancement `communication_governance` depend d'une preference notification par employe actif. `notifications:backfill-preferences` doit creer les lignes manquantes et reparer le `company_id` sans ecraser les choix utilisateur existants ; scenarios couverts par `BackfillNotificationPreferencesCommandTest`, `NotificationPreferenceControllerTest` et `LaunchReadinessControllerTest`.
- v4.16.246 : `DemoCompanyOnceSeeder` doit backfiller les signaux demo necessaires au score lancement (`payroll_base`, `attendance_entry`, `client_experience_tracking`) sans reseed destructif. Scenario couvert par `DemoUserControllerTest::test_demo_once_seeder_backfills_launch_readiness_signals_for_existing_demos`.
- v4.16.128 : `AuthServiceTest::test_login_resolves_public_company_when_tenant_schema_shadows_companies_table` et `DemoUserControllerTest::test_demo_login_recovers_missing_lookup_from_shared_tenant_schema` couvrent le login shared PostgreSQL et `/auth/me` quand le schema tenant masque `public.companies`. Tout smoke demo doit verifier `POST /auth/login` puis `GET /auth/me`, pas seulement `/demo-users`.
- v4.16.49 : Les tests mobiles Plan 14 couvrent desormais navigation GoRouter, surfaces principales, contrats repositories via `ApiClient` mocke et baselines structurelles paie/conges. Le poste Windows local ne fournit pas `flutter`/`dart`; GitHub Actions reste la source de verite pour compiler et executer ces tests.
- v4.16.47 : Les benchmarks performance Plan 14 ajoutent des scripts k6 pour 100 employes simultanes, paie 500 employes et dashboard 10k employes. Les scenarios API doivent garder l'organigramme scope par tenant et le rapport mensuel attendance groupe par employe pour eviter les regressions de scans repetes.
- v4.16.61 : Le sitemap Next `/api/sitemap` liste aussi `/changelog`, `/privacy` et `/terms` pour suivre les routes publiques FR/EN/TR/AR.
- v4.16.60 : La vitrine expose `/changelog` (extrait public du changelog produit) ; le footer pointe vers `/pricing`, `/changelog`, `/blog`, `/privacy`, `/terms`. Le Web Marketing CI doit continuer a valider lint + build ; ajouter un smoke manuel ou E2E du lien « Changelog » si une suite Playwright vitrine est introduite.
- v4.16.29 : La vitrine `front/web` expose maintenant les pages legales `/privacy` et `/terms` en FR/EN/TR/AR avec RTL arabe. Les scenarios Web Marketing doivent verifier les liens footer, le rendu des routes et le changement de langue sur ces pages.
- v4.16.8 : Le cockpit `front/admin-dashboard` consomme maintenant `/platform/metrics/overview` pour les chiffres financiers globaux. Les scenarios web admin doivent verifier MRR, ARR, encaissements 30 jours, impayes et subscriptions sans recalculer ces agregats depuis des listes partielles.
- v4.16.7 : Le contrat `GET /api/v1/platform/metrics/overview` devient une surface plateforme critique pour le cockpit super-admin. Il doit rester protege par `super_admin_api`, exposer uniquement des agregats non nominatifs et rester tolerant aux tables billing absentes pendant les migrations progressives.
- v4.16.0 : Les annotations PHPDoc `@property`, `@return` et `@var Employee` ajoutees dans les modeles, services et controllers ne modifient aucun comportement runtime. Le helper `currentCompany()` est un remplacement fonctionnellement identique de `app('current_company')`. Le binding `LLMClient` dans AppServiceProvider preserve le meme comportement de selection provider. Aucun nouveau endpoint ni modification de contrat API.
- v4.16.1 : Extraction des appels inline `$request->user()->` dans 8 controllers supplementaires. Aucun changement de comportement ni de contrat API.
- v4.16.2 : Extraction des chaines `->fresh()->` nullables et ajout de null checks sur les relations. Aucun changement de comportement ni de contrat API.
- v4.16.27 : Annotations PHPStan Partie 5 — `@mixin` sur 16 Resources, `@property` sur 4 modeles Camera, `@property-read` sur 14 modeles, `@param/@return Builder<static>` sur 48 scopes. Aucun changement de comportement runtime.
- v4.16.3 : Guards `Schema::hasTable()` sur 8 migrations tenant + `$withinTransaction = false` sur migration public. Aucune modification de schema — uniquement idempotence des migrations existantes.

## Notes 2026-05-08

- Le workflow `Tests - Leopardo RH` ne doit pas lancer le job mobile uniquement parce que `.github/workflows/tests.yml` change. La dette mobile historique doit rester visible, mais elle ne doit bloquer une PR backend/admin/web que si `front/mobile_apps/**` bouge vraiment.
- La gate `Backend Quality` doit rester veridique sur le code PHP touche par la PR. Tant que tout l'historique PHPStan n'est pas resorbe, privilegier un scope diff-aware plutot qu'un faux vert global ou un blocage hors perimetre.
- Le contrat d'auth plateforme (`/api/v1/platform/auth/*`, `role=super_admin`, `two_fa_enabled`, `202 TWO_FA_REQUIRED`) fait maintenant partie du perimetre admin critique et doit rester documente et teste.
- Les extensions attendance qui rendent la valeur terrain visible (impact business des anomalies, actions manager recommandees, rapport mensuel avec estimation paie, checklist go-live) font partie des scenarios API critiques et doivent rester couvertes par `Tests - Leopardo RH`.
- Le contrat health plateforme (`/api/v1/platform/companies/{company}/health`) est une surface v5.0 critique : il soutient adoption, retention et upsell, et doit rester teste avec isolation tenant et auth super-admin.
- La vue portefeuille health (`/api/v1/platform/companies/health`) est critique pour le pilotage commercial : elle doit garder MRR, repartition des risques et next action par client dans la CI backend.
- Le contrat abonnement plateforme (`/api/v1/platform/companies/{company}/subscription`) est critique pour la commercialisation : il doit rester fournisseur-agnostique et valider plan, statut et dates avant toute integration paiement.
- Le catalogue plans plateforme (`/api/v1/platform/plans`) doit rester teste afin que l'admin-dashboard ne hardcode jamais les `plan_id` ou les limites de packaging.
- Le cockpit admin v5.0 doit afficher les donnees reelles du portefeuille, du detail health, des abonnements et des plans. Toute regression `front/admin-dashboard/**` sur ces vues doit rester couverte par build/Playwright.
- L'intake demandes clients de l'admin-dashboard doit rester branche sur `/api/v1/platform/company-requests` : filtres statut, compteurs et actions approuver/rejeter font partie du parcours commercial critique.
- L'accueil admin v5.0 ne doit plus dependre d'endpoints mockes `/admin/dashboard/*`; il synthetise les contrats plateforme existants pour garder un premier ecran exploitable.
- L'approbation d'une demande client doit verifier le provisioning complet : company publique, manager principal tenant, invitation et `approved_company_id`.
- v4.16.28 : CI/CD Hardening Partie 6 — seuil coverage 40%, PHPStan diff-gate elargi a tout `app/`, baseline auto-regen sur main avec delta, 3 suites E2E Playwright (navigation, accessibilite, error-handling). Aucun changement de comportement runtime.
- v4.16.67 : Plan 15 Batch 1 — declarations sociales CNAS DZ / CNSS MA, import employes CSV, compression gzip API. Nouveaux endpoints : `POST /social-declarations/cnas-dz`, `POST /social-declarations/cnss-ma`, `POST /employees/import`, `GET /employees/import-template`. Scenarios ajoutes dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- v4.16.69 : Iteration 7 — IA Workflows metier + simulation cotisations. Nouveaux endpoints : `POST /ai/workflows/prepare-payroll`, `GET /ai/workflows/weekly-report`, `POST /cotisation-simulation`. Tests : `AIWorkflowTest.php`, `CotisationSimulationTest.php`. Scenarios ajoutes dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- v4.16.70 : Iteration 8 — Admin enrichments (E3 contrats detail+alertes, E5 formation detail, E8 rapports RH SPA, D6 indexes etendus, F7 newsletter footer). Ecran `/reports` dans admin-dashboard consomme les 10 endpoints rapports backend. Panneau detail contrat et formation enrichis avec alertes automatiques. Indexes PostgreSQL etendus pour contrats, formation, recrutement, audit, webhooks. Newsletter integree dans le footer vitrine.
- v4.16.73 : Iteration 10 — IA Predictions (turnover, absenteisme, notifications proactives). Nouveaux endpoints : `GET /predictions/turnover`, `GET /predictions/absenteeism`, `GET /predictions/notifications`. Tests Feature RBAC PredictionControllerTest (6 tests). Dashboard predictif admin `PredictionsView.vue`. Mobile absences enrichi (leaveBalancesProvider). Plan 15 : C11, C12, C13, C15, E6, E7, G2-G7, G9 -> DONE.
- v4.16.71 : Iteration 9 — Audit logs UI (E9) avec filtres action/type/recherche, export CSV, panneau detail avec diff old/new values. E4 (recrutement Kanban) confirme DONE. Good first issues (I2) et release notes v0.1.0 (I5) documentes.
- v4.16.74 : Iteration 11 — SSO SAML/OIDC stub (K2) + audit WCAG 2.1 AA (K4). Nouveaux endpoints : `GET /sso/providers`, `GET /sso/status`, `POST /sso/configure`, `DELETE /sso/disable`, `POST /sso/saml/{id}/callback`, `GET /sso/oidc/{id}/callback`. Tests Feature SSOControllerTest (8 tests RBAC). Migration company_sso_configs. Skip-to-content WCAG 2.4.1 admin + vitrine.
- v4.16.72 : Iteration 12 — PayrollView enrichi (onglet structures salariales), MetricCard composant, ReportsView rapports RH, PlanningOptimizer IA (C14), sidebar admin avec liens rapports/audit, corrections WCAG (role alert, aria-sort, search input). E1, E2, E10, E11, C14, F1-F6 DONE.
- v4.16.80 : Iteration 13 — Architecture & Performance. Nouveau endpoint : `POST /auth/refresh-token` (rotation Sanctum token). Nouveaux services : TenantCacheService (D1 cache Redis tenant-scoped), ProcessPayrollBatchJob (D2 queue payroll async), SendBulkNotificationsJob (D2 queue notifications bulk), SensitiveDataEncryptor (D5 AES-256). Nouveaux tests : QueueJobsTest (4 tests dispatch/tags). Runbooks : RUNBOOK_UPTIME_MONITORING.md (B4), RUNBOOK_ALERTING.md (B6). Plan 15 : 98.5% DONE (320/325).
- v4.16.90 : Plan 14 Phases 2-6 — Solidification technique. Nouveaux endpoints : `POST /social-declarations/dsn-fr` (DSN simplifie France S10/S20/S21/S44), `GET /notifications/stream` (SSE temps reel). Nouveau middleware : `TokenAutoRefreshMiddleware` (rotation JWT automatique via X-New-Token). Nouveaux exports bancaires : CPA/BNA format DZ. Nouveaux composants admin : CommandPalette (Ctrl+K), SkeletonLoader (6 variantes). Composable : useNotificationStream.js (client SSE). Documentation commerciale : dossier technique, comparatif concurrents, benchmarks performance. Scenarios ajoutes dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- v4.16.81 : Iteration 14 — Test Coverage Hardening. 7 nouveaux fichiers tests Feature : AuthRefreshTokenTest (rotation token, invalidation ancien token, preservation abilities), TenantCacheServiceTest (cache tenant-scoped, isolation, round-trip), SensitiveDataEncryptorTest (encrypt/decrypt, idempotence, batch array), CalendarSyncControllerTest (auth, validation), DeviceTokenControllerTest (auth, RBAC manager), PlanningControllerTest (RBAC optimize/coverage), ZktecoControllerTest (auth devices, heartbeat), CotisationSimulationControllerTest (auth, RBAC, validation).
- v4.16.131 : Plan 23 Iteration 5-6 — 11 Model Policies (Absence, Contract, Department, Position, Schedule, Site, ApprovalRequest, Loan, ExpenseClaim, Invoice, WebhookEndpoint) enregistrees dans AuthServiceProvider. Select() sur index queries Approval/Site/Schedule. RBAC Route Matrix mise a jour. Scenarios ajoutes dans SCENARIOS_TEST_API_GITHUB_ACTIONS.md.
- v4.16.130 : Plan 23 Iterations 1-4 — API Production-Grade. 11 nouvelles API Resources (Absence, Department, Position, Schedule, Site, Notification, ApprovalRequest, Invoice, AuditLog, WebhookEndpoint, Payroll). 10 FormRequests extraites (Department, Position, Schedule, Site, Webhook store/update). ApiError backed enum ~40 codes i18n FR/EN/AR/TR. DB::transaction sur ContractController::renew, ApprovalController::approve/reject, NotificationController::markRead/markAllRead. 9 controllers refactorises vers Resources+FormRequests. Scenarios ajoutes dans SCENARIOS_TEST_API_GITHUB_ACTIONS.md.
- v4.16.129 : API Consolidation RBAC. Nouveau middleware `EnsureApiManagerMiddleware` (`api.manager`) avec roles parametrables. Routes restructurees : dashboard/exports/billing/payroll/hr_extended avec guards RBAC. `DemoCompanySeeder` enrichi : contrats, formations, recrutement, prets, notes de frais. API Explorer regroupe par categorie. Test `ApiManagerMiddlewareTest` (5 scenarios). Scenarios ajoutes dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- v4.16.183 : Migration HTTP v1 pour Firebase Push Notifications. Job SendPushNotificationJob cree. Scenarios ajoutes dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- v4.16.183 : Sync mobile FCM post-auth. Les apps employee/manager initialisent `PushNotificationService` apres login/hydratation et enregistrent le token via `POST /api/v1/device-tokens`; les checks attendus restent `DeviceTokenControllerTest`, `FrontendApiContractTest` et les analyses `mobile-apps-ci`.
- v4.16.184 : Platform admin mobile auth hardening. Le mobile super-admin garde le login obligatoire, gere explicitement `202 TWO_FA_REQUIRED`, evite `/platform/auth/me` sans token local et valide le formulaire de creation client. Scenarios couverts par `PlatformAuthTest`, `PlatformCompanyProvisioningTest`, `FrontendApiContractTest` et `mobile-apps-ci`.
- v4.16.184 : Cycle de vie FCM mobile. Les apps employee/manager enregistrent le token apres auth et tentent `DELETE /api/v1/device-tokens` avant logout; la deconnexion reste non bloquante si le reseau echoue. Scenarios couverts par `DeviceTokenControllerTest`, `FrontendApiContractTest` et analyses mobile.
- v4.16.185 : Contrats notifications mobiles. Les apps employee/manager consomment `GET /notifications?unread=true`, l'alias `unread_only=true`, `PUT /notifications/{id}/read`, `PUT /notifications/read-all` et `DELETE /notifications/{id}` avec retry court. `NotificationControllerTest` couvre le scope utilisateur, l'audit communication, les alias mobiles et les routes dashboard historiques.
- v4.16.186 : Preferences notifications mobiles. Les ecrans Compte employee/manager doivent charger et sauvegarder `/api/v1/notification-preferences` avec retry court pour app, push, email et heures calmes. Scenarios couverts par `NotificationPreferenceControllerTest`, `FrontendApiContractTest` et analyses `mobile-apps-ci`.
- v4.16.187 : Actions liste notifications mobiles. Les ecrans notifications employee/manager doivent garder marquage lu, suppression par swipe/menu et refresh provider apres mutation. Scenarios couverts par `NotificationControllerTest`, `FrontendApiContractTest` et analyses `mobile-apps-ci`.
- v4.16.231 : Liste equipe mobile manager. `GET /api/v1/employees` doit rester teste avec le payload complet consomme par `EmployeeResource` (`contacts personnels`, `salary_*`, horaire, biometrie, `extra_data`, `work_state`) afin d'eviter les 500 production sur modeles partiellement charges. Scenarios couverts par `EmployeesRbacTest`, `ApiListQueryContractTest`, `MobilePayloadContractTest` et les smokes Plan 69.3.
- v4.16.188 : Bootstrap mobile anti-ecran gris. Les trois apps passent par `StartupGate`, recuperent `offlineCache` si Hive est corrompu et affichent une erreur exploitable au lieu d'un ecran gris. Scenarios couverts par analyses/builds `mobile-apps-ci` et distribution Firebase.
- Plans 60-65 (Redis Upstash backend) : Double validation avances salaire (Plan 60), PayrollCycleService + PayrollCycleController cycles & solde employe (Plan 61), GeneratePaySlipPdfJob PDF async queue `pdf` (Plan 62), QueueHealthCheck + queues nommees Upstash (Plan 63), AutoCloseAttendanceCommand horaire (Plan 64), ProcessBulkPaymentJob + BulkPaymentController paiement masse avec progression Redis (Plan 65). Scenarios ajoutes dans SCENARIOS_TEST_API_GITHUB_ACTIONS.md.
- v4.16.251 : Lots P0-P3 — Webhooks Svix, Onboarding Wizard, Drip Emails, Offline Sync Mobile, Portail Développeur. Scenarios couverts par tests existants et documentation mise a jour. Scenarios ajoutes dans SCENARIOS_TEST_API_GITHUB_ACTIONS.md.
- v4.16.252 : Module Growth - Partenariat et Parrainage. Nouveau middleware PartnerLinkMiddleware (tracking affilie avec cookie 30j et redirection /signup). Service PartnerService (attribution partenaire, prevention auto-referral, calcul commissions). Tests GrowthModuleTest : attribution, prevention auto-referral, calcul commissions, workflow complet partenaire. Schema SQL fixture mis a jour (eferrer_partner_id sur companies). Scenarios API ajoutes dans SCENARIOS_TEST_API_GITHUB_ACTIONS.md.
- v4.16.255 : Growth Module Auth Fix - Le middleware des routes `/partner/*` utilise `auth:sanctum` (compatible Employee token web/mobile) au lieu de `auth:user_api`. `PartnerDashboardController::resolveGlobalUser()` fait le pont entre l'identite `Employee` Sanctum et l'enregistrement `User` dans `public.users` pour les acces `public.partners`. Surface concernee : `POST /partner/apply`, `POST /partner/payout`, `GET /partner/stats`, `GET /partner/companies`. Contrat existant : `GrowthModuleTest` + `FrontendApiContractTest`.
- v4.24.0 : Solutions sectorielles — questionnaire de pre-qualification public (issue #6662, PR #6663). Nouveaux endpoints publics `GET /api/v1/solutions`, `GET/POST /api/v1/solutions/{code}/survey`, `GET /api/v1/solutions/{code}/pack` (PDF). Moteur de regles deterministe (`Core/Solutions/Survey`), aucune donnee tenant, throttle 10/min. Scenarios couverts par `SolutionSurveyEndpointTest` + `SolutionSurveyEngineTest`.
| Solutions sectorielles | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | SolutionSurveyEndpointTest | backend-tests |
| Absence DDD | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | AbsenceServiceTest | backend-tests |
| Expense DDD | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | ExpenseServiceTest | backend-tests |
| Notification DDD | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | NotificationTest | backend-tests |

- v4.16.256 : Migration routes vers modules DDD — Les 8 derniers contrôleurs (`MeController`, `SiteController`, `EstimationController`, `NotificationStreamController`, `AdvancedReportController`, `AuditLogController`, `EmployeeLoanController`, `PredictionController`) ont été déplacés vers les modules métier (`App\Modules\HR\...`, `App\Modules\Payroll\...`, `App\Modules\Notification\...`). Les anciens contrôleurs ont été supprimés. Scenarios couverts par les tests existants (FrontendApiContractTest, backend feature tests).
| Routes DDD Migration | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | FrontendApiContractTest | backend-tests |
- v4.18.0 : Edge Sync offline-first (PR #813) — Module EdgeSync Phase 4 complet. Endpoints: `POST /edge/auth/register`, `POST /edge/push`, `GET /edge/pull`, `GET /edge/health`, `GET /admin/edge-nodes`, `POST /admin/edge-nodes/{id}/sync`, `POST /admin/edge-nodes/{id}/revoke`. Flutter : `edge_database.g.dart` généré Drift v2.14, `sync_service.dart` adapté connectivity_plus v6. Fix `EdgeSyncServiceProvider::mergeConfigFrom` chemin config corrigé. Scenarios couverts par `EdgeSyncTest`, `EdgeOfflineScenarioTest`.
| EdgeSync API | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | EdgeSyncTest, EdgeOfflineScenarioTest | backend-tests |

- v4.21.0 : Refactor DDD — suppression 90 controllers legacy (`app/Http/Controllers/Api/V1/`) et 26 services (`app/Services/`), migration vers modules DDD. Infrastructure manquante créée pour Growth, Platform, Onboarding, Training. app/DTOs/ racine supprimé (3 DTOs migrés). Surface API inchangée — régression couverte par `FrontendApiContractTest` et feature suite complète.
| DDD Legacy Cleanup | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | FrontendApiContractTest | backend-tests |
- v4.24.0 (issue #1811) : jours fériés par pays — table publique `public_holidays` (national + entreprise, is_recurring, holiday_type), seeder fixes DZ/CM/CI/SN 2024-2027, `PublicHolidayService` (getHolidays/workingDaysBetween/forget, cache Redis 24h), `PayrollCalculator::computeWorkedDays` dynamique (fallback 22), API admin + principal. Scenarios ajoutes dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- Vague QA 2026-08-14 (spec kit, PR #2306) : campagne de test complète plateforme — (1) Backend : suite `tests/Feature/User/UserAuthTest.php` (module user 0→10 tests), export bancaire SEPA sans placeholders (config tenant `metadata.bank`), provision mot de passe employé honorée (`CreateEmployeeDTO`), convention routes notifications documentée (PUT canonique, alias compat), module Cabinet `company_id` UUID réel (migration tenant 000019, fin du hack legacy bigint/clé 0 — tests #1921 10/10), tests unitaires payroll réalignés (caps CNSS CI #1913, ITS 2024 #1918, pilot ML/BF #1829, exception typée #1868). (2) Web App : boutons dashboard câblés (recherche, notifications, activité, Leo IA → annonces, actions rapides), détail bulletin (modal), toggle thème carrières. (3) Admin : widgets Analytics câblés, Super-Console, gestion partenaire (taux commission API), avatar upload. (4) Mobile : patterns interdits supprimés, `leopardo_marketing` compile. Scenarios couverts par les tests listés + smoke API live (signup→verify→login→simulate→employees→announces→exports).
| DeliveryAgency API | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | tests/Feature/Delivery/* | backend-tests |
- Train PM verdissement #6818 (09-05) : sync catalogues i18n mobiles `leopardo_core/l10n` (app_fr/en/ar/tr.arb, 4 fichiers) — parité locale couverte par la garde `i18n paie ×4`/`check-mobile-l10n-sync.sh` (non-régression clés fr/en/ar/tr).

- v4.25.0 (BC-28 CATALOG #6881) : API privée de gestion du catalogue B2B — CRUD catégories (`/catalog/categories`) et produits (`/catalog/products`, publication/dépublication), gate feature flag `b2b_catalog`, RBAC gestion principal/rh, isolation tenant. Scenarios couverts par `tests/Feature/Catalog/CatalogApiTest.php` (RBAC deny-by-default, gate flag, isolation cross-tenant 404, CRUD + publication).
| Catalog B2B API | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | tests/Feature/Catalog/CatalogApiTest.php | backend-tests |

- v4.26.0 (BC-28 C-PUBLIC #6882) : catalogue public isolé — `GET /public/catalog/{companySlug}` (catégories + produits publiés, filtre `?category=`) et fiche `GET /public/catalog/{companySlug}/products/{productSlug}`, SANS auth (`throttle:shop-public` + `catalog.public`), tenant par slug, DTO strict (0 champ interne), cache Redis TTL invalidé à la publication, 404 fail-closed (slug inconnu / flag absent / suspendu). Scénarios couverts par `tests/Feature/Catalog/CatalogPublicApiTest.php`.
| Catalog public B2B | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | tests/Feature/Catalog/CatalogPublicApiTest.php | backend-tests |

- v4.27.0 (BC-28 C-LEAD #6884) : formulaire public de demande de devis — `POST /public/catalog/{companySlug}/inquiries` SANS auth (honeypot anti-spam, consentement RGPD requis, produit publié exigé) → demande `catalog_inquiries` (tenant, minimisation, rétention bornée) + événement `catalog.inquiry_received` (contrat cross-BC, catalogue d'événements v1.0.0) → lead CRM BC-11 source `b2b_catalog` + notification in-app managers (canal app). Scénarios couverts par `tests/Feature/Catalog/CatalogPublicInquiryApiTest.php`.
| Catalog inquiries B2B | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | tests/Feature/Catalog/CatalogPublicInquiryApiTest.php | backend-tests |

- v4.28.0 (BC-28 C-CURRENCY #6886) : devises & unités v1 — whitelists strictes configurables (CatalogPricePolicy), devise par défaut tenant, prix minor units int, formateur intl sans flottant. Scénarios couverts par `tests/Feature/Catalog/CatalogCurrencyRulesTest.php` + `tests/Unit/Catalog/CatalogPriceFormatterTest.php`.
| Catalog devises/unités | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | tests/Feature/Catalog/CatalogCurrencyRulesTest.php | backend-tests |

- v4.29.0 (BC-28 C-BACKOFFICE #6885) : back-office tenant des demandes de devis — `GET /catalog/inquiries` (liste + filtres), `PATCH /catalog/inquiries/{inquiry}/status` (matrice new→contacted→quote_sent→closed|lost, notes horodatées), `GET /catalog/inquiries/export` (CSV). RBAC principal/rh/manager, isolation 404. Scénarios couverts par `tests/Feature/Catalog/CatalogInquiryBackofficeTest.php`.
| Catalog back-office devis | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | tests/Feature/Catalog/CatalogInquiryBackofficeTest.php | backend-tests |

- v4.30.0 (BC-28 C-RGPD #6889) : protection données acheteur devis — `DELETE /catalog/inquiries/{inquiry}` (effacement + propagation leads CRM via `catalog.inquiry_erased`), purge rétention expirée (`catalog:purge-expired-inquiries`), revue non-fuite routes publiques, registre RGPD §10. Scénarios couverts par `tests/Feature/Catalog/CatalogRgpdTest.php`.
| Catalog RGPD devis | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | tests/Feature/Catalog/CatalogRgpdTest.php | backend-tests |

- v4.31.0 (BC-27 SHOWCASE v1 #6870 #6871 #6873 #6874 #6875 #6876 #6891) : vitrine publique — publication/dépublication + aperçu par jeton (`?token=`), SEO (meta SSR, `sitemap.xml`, `robots.txt`), i18n fr/en/ar/tr par locale, thèmes v1, bloc RGPD, section `products` (contrat Shared résolu par le catalogue BC-28), éditeur admin `/showcase` + parcours E2E Playwright. Scénarios couverts par `api/tests/Feature/Showcase/ShowcasePublishWorkflowTest.php`, `ShowcaseI18nThemesProductsTest.php` et `front/admin-dashboard/e2e/showcase-flow.spec.js`.
| Vitrine BC-27 | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | tests/Feature/Showcase/ShowcasePublishWorkflowTest.php | backend-tests |

- v4.32.0 (BC-28 CATALOG #6883 #6888 #6890) : fiche produit publique (`GET /public/catalog/{companySlug}/products/{productSlug}` — photos, `meta` SEO dérivées du contenu, `related` produits publiés, CTA devis) + SEO catalogue (`meta` du snapshot + `GET /public/catalog/sitemap.xml` des produits publiés, brouillons jamais exposés) + parcours E2E création → publication produit → fiche publique + devis. Scénarios couverts par `api/tests/Feature/Catalog/CatalogPublicPageSeoTest.php` et `CatalogEndToEndJourneyTest.php`.
| Catalog fiche produit + SEO | docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md | Tests - Leopardo RH | tests/Feature/Catalog/CatalogPublicPageSeoTest.php | backend-tests |

## Mise a jour 2026-09-10 (2) — acces admin plateforme & etats de chargement

Demande proprietaire : l'admin plateforme est reserve au super-admin de la
plateforme (pas aux utilisateurs d'un tenant), le selecteur de comptes de demo
doit rester reserve au deploiement DEV, et la zone de contenu de l'admin ne doit
plus s'afficher vide a l'arrivee.

- **Surface web admin** — `stores/auth.js` : garde explicite `role === 'super_admin'`
  a la connexion ET a la reprise de session (`/platform/auth/me`). Le backend
  separait deja `super_admins` (schema public) des `employees` du tenant ; la
  garde front rend la regle opposable cote client. Nouveau scenario a couvrir :
  identifiants tenant -> refus explicite, pas d'acces a l'admin.
- **Selecteur de comptes de demo** : toujours conditionne a `GET /demo-users`
  (404 hors mode demo, donc en production) — aucun identifiant dans le bundle,
  verifie sur le bundle de production (0 occurrence de mot de passe de demo).
  Aucun changement de comportement attendu.
- **Etats de chargement** : `layouts/DashboardLayout.vue` entoure le `<router-view>`
  d'un `<Suspense>` avec indicateur (les vues sont chargees dynamiquement : la zone
  de droite restait vide pendant le telechargement du chunk). 10 vues principales
  demarrent desormais avec leur drapeau de chargement a `true` (1er rendu = indicateur,
  plus de contenu a zero puis spinner puis donnees).
- **i18n** : nouvelle cle `auth.platform_admin_only` dans les 4 langues du catalogue
  partage, puis chaine de sync rejouee (admin/web/ARB mobile + `versions.json`).

Scenarios admin a verifier apres deploiement : refus d'un compte tenant, absence du
selecteur de demo hors DEV, affichage d'un indicateur (et non d'une page vide) au
premier rendu de chaque vue principale.
## Mise a jour 2026-09-10 — honnetete de la copie publique & propagation i18n

Contexte : audit de la presentation publique (vitrine Next.js + site GitHub Pages)
et de la veracite des termes employes. La correction a entraine :

- `shared/i18n/locales/{fr,tr}.json` : prix public Operations corrige (99 € -> 79 €,
  prix canonique ADR-0014 / `PlanSeeder`), puis chaine de sync rejouee — les
  catalogues **admin** (`front/admin-dashboard/src/i18n/locales/*`) et **mobile**
  (`front/mobile_apps/leopardo_core/lib/l10n/*.arb`) ont donc ete regeneres
  mecaniquement, sans changement fonctionnel.
- Surface **web admin** : aucun scenario n'est invalide (catalogues uniquement).
  Le prix affiche reste celui de la page pricing ; le controle de non-regression
  porte sur l'egalite des libelles entre catalogue genere et source.
- Surface **mobile** : aucun comportement modifie ; seule la valeur traduite du
  meme bloc est propagee aux ARB.
- Surface **vitrine** (hors perimetre admin/mobile) : suppression des chiffres
  non mesures (« 99.9% », « 50K+ utilisateurs », « SOC2 », « 4.9 App Store »),
  remplacement des revendications de conformite par leurs formulations etayees,
  ajout des mentions « exemple illustratif » manquantes (equipe `/about`,
  detail des etudes de cas), alignement des metriques sur le registre
  `docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md`.
- `dev-hub/tools/check-public-promises.sh` : 7 motifs supplementaires (conformite
  RGPD affirmative, conformite garantie, SOC2/ISO 27001, « always compliant »).

Aucun parcours critique n'est modifie : le lint, le build et les suites vitrine
restent les gates applicables.
- **#7223 — Web E2E admin : rôle `super_admin` + init carte flotte.** Les specs `accounting-dashboard-golden`, `travel-content`, `travel-contacts` et `fleet-no-session-kill` ont été réalignées sur le garde-fou de rôle introduit par #7205 (le backend plateforme renvoie toujours `super_admin`) ; `FleetView.vue` n'initialise plus Leaflet sans conteneur monté (`await nextTick()` + garde). Aucun changement de parcours fonctionnel.
- **#6872 (V-MEDIA, BC-27 SHOWCASE) — médias de vitrine.** Upload/suppression/liste des médias (logo, images de sections) : types et taille validés (`StoreShowcaseMediaRequest`), isolation tenant, rendu public des médias publiés avec en-têtes de cache, UI d'upload dans l'éditeur de vitrine (`ShowcaseMediaUploader.vue`). Scénarios API : `docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md` ; UI : `docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md`.
- **#7235 / #7234 — inscription par profil + vitrine.** Surface **mobile** : aucun comportement modifié ; seules les **valeurs traduites** des ARB (`front/mobile_apps/leopardo_core/lib/l10n/app_{fr,en,ar,tr}.arb`) sont propagées depuis le catalogue partagé — nouvelles clés `signup.*` (profil d'activité, outils, métier), `signupPage.*` (récit de la page d'inscription) et `trial.*` (jours d'essai restants), et mise à jour des libellés `signup.badge/title/subtitle/submitLabel` + `pricing.plans.*.cta` (fin du discours « essai 14 jours » à l'inscription). Aucun parcours mobile n'est modifié : les gates applicables restent le lint, le build et les suites web/vitrine (`e2e/client-company-profile.spec.ts`, `e2e/marketing-funnel.spec.ts`).
- **#7238 / #7240 — inscription : le tunnel s'ouvre sur le choix de l'OFFRE (et l'offre pilote la création du compte).** Le parcours `/signup` gagne une **étape 0 « Offre »** (les 4 offres du catalogue tarifaire, pré-sélectionnées par `?plan=` des CTA `/pricing`, `Continuer` bloqué sans sélection) et le tunnel passe à 4 temps (offre → profil → outils/métier → coordonnées). L'offre choisie est désormais **réellement appliquée** : `POST /trial/signup` restreint `plan` aux codes connus (`Rule::in`) et `VerifyTrialSignup::resolveTrialPlan($code)` crée la société sur l'offre **demandée** (au lieu du premier plan actif, soit Free). Surface **web client** : scénarios `e2e/marketing-funnel.spec.ts`, `e2e/client-company-profile.spec.ts`. Surface **web admin** : aucun comportement modifié — seules les **valeurs traduites** des catalogues (`front/admin-dashboard/src/i18n/locales/*.json`) sont propagées depuis le catalogue partagé (4 clés `signup.stepPlanLabel`/`planTitle`/`planSubtitle`/`planContinue`). Surface **mobile** : seules les ARB (`front/mobile_apps/leopardo_core/lib/l10n/app_{fr,en,ar,tr}.arb`) sont régénérées, aucun parcours modifié.

## Mise a jour 2026-09-13 — inscription sans friction (PR #7275, issues #7273/#7274)

- **Surface web client / vitrine — parcours d'inscription modifie** : le formulaire ne demande
  plus le role (fondateur implicite), ni la taille d'equipe, ni le telephone (l'e-mail est
  verifie par code), ni le pays (resolu cote serveur par geolocalisation via l'en-tete
  plateforme `x-vercel-ip-country` — `request.geo` n'existe plus en Next 16, correction du
  2026-09-13 ci-dessous), avec
  repli `COUNTRY_REQUIRED` + selecteur affiche uniquement si la detection echoue). Le choix
  **entreprise / independant** est conserve. Apres le code de verification, la session est
  ouverte automatiquement (cookie httpOnly) et l'utilisateur entre directement dans son espace.
  L'offre choisie (`?plan=`) est rappelee sur le formulaire. Scenarios applicables :
  `front/web/e2e/marketing-funnel.spec.ts`, `front/web/e2e/client-company-profile.spec.ts`,
  et la suite unitaire `jest` (desormais executee par le job requis
  « Frontend — ESLint + TypeScript »).
- **Surface API** : `POST /api/v1/trial/signup` accepte un champ optionnel `locale`
  (`fr|en|ar|tr`) qui pilote la langue de l'e-mail de verification **et** la langue du tenant
  provisionne ; `POST /api/v1/trial/verify` renvoie `data.token` (jeton de session, `null` si
  l'auto-connexion n'a pas pu etre ouverte). Les chaines de l'e-mail de verification
  (`api/resources/views/emails/trial-verification.blade.php`) sont desormais externalisees dans
  `api/lang/*/emails.php` (garde I18N).
- **Surface mobile** : aucun comportement modifie ; seules les **valeurs traduites** des ARB
  (`front/mobile_apps/leopardo_core/lib/l10n/app_{fr,en,ar,tr}.arb`) sont propagees depuis le
  catalogue partage — 3 nouvelles cles `signup.planSelected`, `signup.planChange`,
  `signup.countryDetectionFailed`. Aucun parcours mobile n'est modifie.
- **Surface web admin** : aucun comportement modifie ; seules les **valeurs traduites** des
  catalogues (`front/admin-dashboard/src/i18n/locales/*.json`) sont propagees depuis le
  catalogue partage (memes 3 cles). Gates applicables : lint, build et suites web/admin.
- **Suite unitaire web remise au vert** : les 5 tests rouges sur `main` (boutique, travel
  portal, absences, lettering) sont corriges — aucun n'etait un « timeout jsdom » : selecteur
  faussement positif, dates figees devenues anterieures au `min` des champs date, periode
  figee, locale non fixee. La suite `jest` est desormais executee par un job **requis**, ce qui
  empeche toute regression unitaire silencieuse.

## Mise a jour 2026-09-13 (2) — tunnel d'inscription : retours fondateur

- **Geolocalisation du pays reellement lue (defaut de la PR #7275, corrige)** : `request.geo`
  a ete retire de `NextRequest` en Next 16, et `next/headers` n'expose ni `geolocation()` ni
  `ipAddress()`. Le repli etait donc TOUJOURS `undefined` : le pays n'etait jamais detecte et
  le selecteur de repli s'affichait pour 100 % des visiteurs, avec un message negatif
  (« nous n'avons pas pu detecter votre pays »). La route lit desormais l'en-tete injecte par
  la plateforme (`x-vercel-ip-country`, plus `cf-ipcountry` pour Cloudflare Pages), ne
  transmet qu'un code ISO a 2 lettres, et le libelle de repli devient neutre
  (« Selectionnez votre pays. », x4 langues). Le test unitaire qui fabriquait un
  `request.geo` inexistant — donc validait une fiction — est remplace par des tests sur les
  en-tetes reels, un cas d'en-tete invalide, et la priorite du choix de l'utilisateur.
- **Page tarifs : acces direct aux offres** : la redirection `/signup` sans `?plan=` pointe
  vers `/pricing#plans` (fragment, pas query : un prefetch de `/signup` suit la
  redirection, et une cible avec query laissait ce prefetch en suspens — e2e vitrine
  `marketing-funnel` en timeout a 90 s), qui affiche immediatement les 4 offres (titre court
  « Choisissez votre offre », libelles du tunnel reutilises) sans le hero marketing de 60 vh,
  sans tableau comparatif, sans FAQ ni bandeau final. Mesure au navigateur (viewport 800 px) :
  le nom du premier plan passe de y=1254 a y=357 ; le selecteur de devise, simple confort
  d'affichage, est masque sur ce chemin. `/pricing` sans parametre est inchange.
- **Colonne gauche de `/signup` allegee et rendue vraie** : la liste « Ce que vous obtenez
  tout de suite » (3 preuves) est retiree, et les 3 etapes decrivent le parcours REEL
  (e-mail + entreprise -> code a 6 chiffres par e-mail -> entree directe, sans mot de passe a
  creer). Deux des trois textes precedents etaient faux : l'etape « outils et metier » avait
  ete retiree par #7249 et l'etape « vous definissez votre mot de passe » est remplacee par
  l'auto-connexion apres verification. Le sous-titre du hero est aligne.
- **Surface web client / vitrine** : aucun changement de contrat API. Scenarios applicables :
  `front/web/e2e/marketing-funnel.spec.ts` et la suite unitaire `jest`
  (`src/app/api/forms/signup/__tests__/route.test.ts`, 8 cas).
- **Surface API** : aucun changement de code dans ce lot. Le correctif d'auto-connexion
  (`data.token`) et de langue de l'e-mail de verification est deja sur `main` (PR #7275).
- **Surface mobile / web admin** : aucun comportement modifie ; seules les valeurs traduites
  sont propagees depuis le catalogue partage (suppression des cles `signupPage.proof*`,
  reecriture de `signupPage.sideTitle`/`step1..3`/`subheadline` et du libelle
  `signup.countryDetectionFailed`).

## Mise a jour 2026-09-13 (3) — SEO & AI-Search de la vitrine (PR #7315, issue #7314)

- **Pourquoi ce lot existe** : audit SEO/AI-SEO de la vitrine. Le SEO classique etait deja
  sain (robots.txt + `Sitemap:`, sitemap 49 URL / 49 en HTTP 200, canonicals, hreflang,
  titres/descriptions localises x4, `SoftwareApplication`/`FAQPage`/`Article`/`JobPosting`),
  mais le SEO generatif etait absent : recherche `llms.txt`, `GPTBot`, `ClaudeBot`,
  `PerplexityBot`, `Google-Extended`, `GEO`, `AEO` dans le depot = **zero occurrence**.
- **Ajouts** : routes `/llms.txt` et `/llms-full.txt` (localisees `?lang=fr|en|tr|ar`,
  `text/plain`, composees depuis les sources existantes — aucune duplication de contenu) ;
  groupe explicite de 17 crawlers IA dans `robots.txt` avec **repetition des prefixes
  proteges** (un groupe dedie ecrase `*` : l'oublier ouvrait `/dashboard`, `/api`,
  `/payroll`) ; noeud `WebSite` + `alternateName`/`sameAs` sur `SoftwareApplication` et
  `Article` ; `BreadcrumbList` serveur (blog, etudes de cas, guides) ; `Article` JSON-LD
  deplace du composant client `BlogArticle` vers `blog/[slug]/layout.tsx` (il n'existait
  qu'apres execution du JavaScript, donc invisible aux crawlers sans JS) ; `x-default`
  hreflang (seo.ts, sitemap.ts, layout racine) ; `VideoObject` serveur sur `/videos`.
- **Contenu / metadonnees** : 12 etudes de cas reecrites (titres 21-35 -> 47-53 car.,
  descriptions 39-54 -> 137-154) **et localisees x4** — c'etait la derniere surface vitrine
  servie en FR sur les pages en/tr/ar ; maillage du hub `/case-studies` retabli (0 -> 12 liens
  sortants vers les etudes de detail) ; 10 titres > 60 car. retailles -> **0 sur 196
  pages-locales mesurees** ; marque dupliquee dans le `<title>` de `/terms` et `/privacy`
  corrigee ; H1 anglais « Integrations » -> « Integrations » accentue ; `<title>` de
  `/download` aligne sur l'acces pilote reel ; hero d'accueil « 8 pays » -> **21** (verite de
  `GET /api/v1/supported-countries`, 21 pays tous `available: true`).
- **Surface web vitrine** : c'est la surface modifiee. Scenario applicable : suite unitaire
  `jest` (`src/lib/__tests__/ai-search.test.ts`, `src/app/__tests__/robots.test.ts`, garde de
  longueur des titres dans `seo-locale.test.ts`, `sitemap.test.ts` realigne sur `x-default`).
  Verification de recette : `196 pages-locales` (49 URL x 4 locales) servies par un build de
  production -> 0 titre > 60, 0 page sans JSON-LD, `/llms.txt` 200 en `text/plain`, hub a 12
  liens x4 locales.
- **Surface API** : aucun changement de code. Aucune route, aucun payload modifie.
- **Surface mobile / web admin** : **aucun comportement modifie**. Les catalogues
  `front/admin-dashboard/src/i18n/locales/*.json` et `front/mobile_apps/leopardo_core/lib/l10n/*.arb`
  evoluent uniquement comme **artefacts generes** par les syncs i18n
  (`shared/i18n/sync/sync-{web,backend,mobile}.js`), du fait de l'ajout des cles partagees
  `seo.llms.*` et `seo.breadcrumb.*` (20 cles x 4 langues). Ces cles n'ont pas d'ecran admin :
  elles alimentent les fichiers AI-search et les fils d'Ariane de la vitrine. Aucun scenario
  de test admin n'est donc impacte.
- **Non fait, volontairement** : aucun signal de confiance fabrique (pas de
  `Review`/`AggregateRating` sur les temoignages et etudes de cas, explicitement fictifs ;
  pas de bios d'auteur — les 4 auteurs du blog sont des personnes fictives). Migration i18n
  `?lang=` -> sous-repertoires `/en/ /tr/ /ar/` laissee en chantier dedie (~40 fichiers :
  middleware, 25 layouts, sitemap, liens internes, 301).

## Mise a jour 2026-09-14 — onboarding client : tunnel honnete, Google, mot de passe (PR #7353, issue #7352)

- **Contexte** : test de bout en bout de l'inscription d'un compte client (navigateur reel + API, dev et prod).
  Le tunnel n'aboutissait pas et le prospect ne pouvait pas le savoir (« Still being created — we will email you the
  access link » alors que le job de provisioning etait en echec).
- **Surface API** : `POST /api/v1/trial/signup` (repli `guided_trial`), `GET /api/v1/trial/status`,
  `POST /api/v1/trial/set-password` (politique de mot de passe : 12 caracteres minimum + 1 chiffre),
  `GET /api/v1/auth/google` (nouveau parametre `intent=signup`), `GET /api/v1/auth/google/callback`
  (renvoie l'identite verifiee sur e-mail inconnu **uniquement** avec l'intention d'inscription ; le parcours
  invitation-first reste inchange). Scenarios a couvrir par les suites existantes
  (`AuthGoogleSignInTest`, `GoogleOAuthStateTest`, tests du module Billing) — aucune suite retiree.
- **Surface web vitrine** : `/signup` (page epuree, bouton « Continuer avec Google », reprise du suivi apres
  rechargement), ecran de suivi du provisioning (message d'echec actionnable), copie FR de la connexion,
  `e2e/marketing-funnel.spec.ts` (assertion alignee sur le formulaire, plus sur le hero retire).
- **Surface mobile / web admin** : **aucun comportement admin modifie.** Seul le libelle du champ de mot de passe de
  la connexion plateforme passe de « Access Key » / « Cle d'Acces » a « Mot de passe » (cles `auth.access_key_label`
  et `auth.access_key_required`, x4 langues), ainsi que l'accent de `shell.pushUnconfigured` en francais. Les scories
  de test admin existantes (`login-smoke.spec.js`, `login-ux.spec.js`, `platform-auth-smoke.spec.js`) ne dependent pas
  de ce libelle : aucun scenario admin n'est impacte, aucune nouvelle spec n'est requise.
## Mise a jour 2026-09-14 — dette front : `middleware` -> `proxy` (Next 16), racine Turbopack, `AnimatePresence`, attributs Vue (PR #7305)

- **Surface web client / vitrine — proxy serveur renomme (aucun changement de comportement)** : la convention
  de fichier Next 16 est passee de `middleware` (depreciee : « The "middleware" file convention is deprecated.
  Please use "proxy" instead. ») a `proxy`. `front/web/src/middleware.ts` -> `front/web/src/proxy.ts`, fonction
  exportee `middleware` -> `proxy`. Les **trois responsabilites critiques portees par ce fichier sont
  inchangees** et couvertes individuellement par le nouveau test unitaire
  `front/web/src/lib/__tests__/proxy-responsibilities.test.ts` : (1) gate d'auth de la zone dashboard (sans
  cookie `leopardo_token` valide -> `/auth/login`) ; (2) `/signup` sans offre souscriptible -> `/pricing#plans` ;
  (3) normalisation de la locale vitrine (`?lang=` puis `Accept-Language`) -> en-tete `x-vitrine-lang`. Les
  suites existantes sont realignees (`proxy-session-token.test.ts`, `session-token-format.test.ts`,
  `protected-prefixes.test.ts`). Scenario de recette manuelle : `curl` sur le dev server — `/signup` 307 ->
  `/pricing#plans`, `/signup?plan=pilot` 200, `/dashboard` 307 -> `/auth/login`, `/employees/42` 307 ->
  `/auth/login`, `/restaurant` 307 -> `/restaurateur`, `/pricing?lang=en` -> `x-vitrine-lang: en`.
- **Surface web vitrine — avertissement framer-motion du funnel** : la `AnimatePresence mode="wait"` de la FAQ
  de `/pricing` recevait une **liste mappee** (`filteredFaq.map(...)`) donc plusieurs enfants par passe ;
  c'est la page servie par la redirection `/signup` sans `?plan=`, d'ou le constat d'audit « reproduit sur
  `/signup` ». Le mode est retire (mode par defaut = liste qui filtre). Garde de non-regression :
  `front/web/src/lib/__tests__/animate-presence-mode-wait.test.ts` (analyse AST : aucune `AnimatePresence`
  `mode="wait"` ne recoit un `xxx.map(...)` comme enfant direct ; reste rouge avant le correctif). Les
  transitions d'etapes du tunnel (`SignupForm`, `checkout`, `RestaurantSolutionWizard`) sont inchangees : leurs
  blocs conditionnels sont mutuellement exclusifs (un seul enfant par passe), verifie par la meme garde.
- **Surface web admin** : la racine du composant `<Sidebar>` (deux noeuds racines : overlay mobile + panneau) ne
  pouvait rien heriter, ce qui produisait a chaque montage du back-office
  `[Vue warn]: Extraneous non-props attributes (class)`. `inheritAttrs: false` + `v-bind="$attrs"` sur le
  panneau rendent l'attribut du consommateur (`DashboardLayout` passe `class="fixed inset-y-0 left-0 z-50"`)
  heritable, rendu inchange. Scenario de non-regression : charger une vue du back-office et verifier l'absence
  de l'avertissement dans la console (cf. `SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md`, section 3).
- **Surface API / mobile** : aucun changement de code, aucun contrat modifie.
- **Non traite, hors perimetre de ce lot** : avertissement Next `scroll-behavior: smooth` (attribut
  `data-scroll-behavior` a poser sur `<html>`) et migration i18n `?lang=` -> sous-repertoires `/en/ /tr/ /ar/`.
>
## Mise a jour 2026-09-16 — positionnement « suite metier » (PR #7518, issue #7428)

- **Surface web admin** : seul le **libelle produit** change. `app.title` du dashboard passe de
  « Leopardo RH » a « **Leopardo — suite metier** » (en : « Leopardo — Business Suite » ; ar :
  « ليوباردو — حزمة الأعمال » ; tr : « Leopardo — İşletme Yönetimi Paketi »), et le namespace
  partage **`seoRoot`** (5 cles de phrases canoniques : racine, manifeste PWA, image OG) est
  propage aux cibles du catalogue par `shared/i18n/sync/sync-web.js`.
  `front/admin-dashboard/src/i18n/locales/*.json` sont des fichiers **generes** (union
  semantique #3853) : le diff du dashboard est mecanique, aucun composant / route / contrat d'API
  n'est touche. Scenario de non-regression : charger une vue connectee du back-office dans les
  **4 locales** et verifier le titre applicatif (« suite metier » localise, jamais
  « logiciel RH ») ainsi que le fait que « Leopardo RH » reste le nom de l'**application** RH &
  paie, pas la categorie du produit (cf. `SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md`, section du
  2026-09-16 ; garde `dev-hub/tools/check-naming-drift.sh`).
- **Surface API / mobile** : aucun changement de code. `api/lang/*/shared.php` et les ARB mobiles
  ne bougent que par la **synchronisation** du catalogue partage (cibles generees : `sync-backend`,
  `sync-mobile`) — voir la note du meme jour dans `SCENARIOS_TEST_MOBILE_FLUTTER.md`.

## Mise a jour 2026-09-18 — entretien conversationnel + checklist personnalisee (PR #7630, issues #7493/#7494)

- **Surface API** : nouvel entretien de preparation tenant-scoped (`GET /api/v1/setup-interview`,
  `PATCH /api/v1/setup-interview/answers`, `POST /api/v1/setup-interview/complete`) porte par
  `SetupInterviewController` + `SetupInterviewPlanner` (mapping reponses -> plan `{solutions, tools}`,
  fail-closed sur allowlist, plancher solo #7423). A la completion, `SeedDefaultSteps` genere la
  checklist d'onboarding **personnalisee** a partir des reponses et des modules actifs (jamais de
  kiosque/geofence sans presence terrain ; etapes deja completees/sautees toujours conservees ;
  tenants sans entretien : 10 etapes par defaut inchangees). Les titres d'etapes et le message
  d'erreur de validation passent par le catalogue `api/lang/*/onboarding.php` (garde i18n
  PA2-I18N-007 / #5432). Scenarios automatises :
  `api/tests/Feature/Onboarding/SetupInterviewControllerTest.php` (contrat, idempotence du
  complete, rejet 422 des reponses hors allowlist sans ecriture) et
  `api/tests/Feature/Onboarding/SetupInterviewSeedingTest.php` (profils restaurateur/solo vitrine,
  convergence des etapes `pending` apres entretien).
- **Surface web** : nouveau parcours `setupInterview` (catalogue `shared/i18n/locales/*.json`,
  synchronise vers `front/web` et `front/admin-dashboard` par `sync-web.js`) — questions
  passables une a une, recapitulatif d'activation, reprise ulterieure.
- **Surface mobile** : cles ARB synchronisees par `sync-mobile.js` (cibles generees), aucun
  contrat modifie.

## Mise à jour 2026-09-19 — lot audit vendeur vitrine (PR #7667, issues #7662–#7665)

- **Surface web (vitrine)** : redirects des URLs devinables (`/login`, `/register`,
  `/onboarding`, `/tarifs`, `/inscription`, `/connexion`, `/a-propos` — 404 vérifiés en prod),
  gate session + robots + sw.js sur `/crm`, `/accounting`, `/edu-manager`, `/fuel`
  (2 nouveaux tests de garde dans `protected-prefixes.test.ts`), 404 globale localisée
  (`vitrine.notFound.*` ×4) et Navbar pilotée par le catalogue (`vitrine.nav.*` ×4 —
  fin des libellés FR sans accents / TR sans diacritiques). Scénarios automatisés :
  Jest front/web complet (127 suites), garde funnel e2e (proxy touché).
- **Surface API / mobile** : aucun changement de code. `api/lang/*/shared.php` et les ARB
  mobiles ne bougent que par la **synchronisation** du catalogue partagé (`sync-backend`,
  `sync-mobile`) — clés additives `vitrine.notFound.*` / `vitrine.nav.*`, aucun contrat modifié,
  aucun scénario mobile nouveau requis.

## Mise à jour 2026-09-19 — hygiène du dépôt, tranche 2 de #7654 (PR #7692)

- **Surface mobile** : documentation uniquement — les READMEs de `leopardo_employee`,
  `leopardo_manager` et `leopardo_hr` pointent désormais vers la régénération à la demande
  des exemples API (`python dev-hub/tools/generate_api_examples.py`) au lieu du dossier
  versionné `docs/api-mock-data/` (sorti du dépôt). **Aucun code, contrat ni scénario mobile
  modifié** — aucun scénario nouveau requis.

## Mise à jour 2026-09-19 — verticale HealthManager BC-31 (PR #7818, issues #7785–#7792)

- **Surface API** : nouvelle surface `/api/v1/health-manager/*` (63 opérations — référentiel
  structure, patients, rendez-vous, consultations/prescriptions, hospitalisations,
  facturation des soins, dashboard). Scénarios automatisés :
  `api/tests/Feature/HealthManager/` (**71 tests / 517 assertions** — matrice canonique par
  domaine : 401 non authentifié / 403 solution inactive `HEALTH_SOLUTION_INACTIVE` /
  403 employé lambda / happy path / 404 cross-tenant), dont les invariants métier :
  conflit de créneau praticien 409, machine à états des rendez-vous 422, lit occupé 409
  sous transaction, confidentialité médicale (réception 403 même en lecture), MRN et
  numérotation facture séquentiels par tenant, prix figés à la ligne, sur-paiement 422.
- **Surface web** : parcours `(dashboard)/health/*` (hub, patients, rendez-vous,
  hospitalisations, facturation, référentiel) gatés par le flag `healthmanager`
  (catalogue `client-features.ts`, non self-activatable) ; `/health` ajouté aux préfixes
  protégés (session + proxy + sw.js — couvert par les gardes `protected-prefixes.test.ts`).
  Jest front/web complet vert (135 suites / 1198 tests).
- **Surface mobile** : aucun contrat modifié — clés `health.*` additives propagées par la
  synchronisation du catalogue partagé, aucun scénario mobile nouveau requis (app dédiée
  éventuelle = lot V1).

## Mise à jour 2026-09-20 — fail-fast sur URL backend manquante en production (PR #7881, issue #7842)

- **Surface web admin** : `front/admin-dashboard/src/services/api.js` ne se replie plus en
  silence sur l'API dev Render (`gestionemployerbackend.onrender.com`) quand `VITE_API_URL`
  est absente. Scénarios : (1) build de production (`import.meta.env.PROD`) **sans**
  `VITE_API_URL` → erreur explicite au chargement de l'application (message technique
  `[admin-dashboard] VITE_API_URL is not set in a production build…`, plus aucun appel vers
  l'API dev) ; (2) build de production **avec** `VITE_API_URL` posée → comportement normal,
  toutes les requêtes partent vers l'URL configurée ; (3) dev/test sans variable → repli dev
  conservé mais signalé par un `console.warn`. Aucun composant / route / contrat d'API
  modifié — le changement est un durcissement de la résolution de configuration.
- **Surface web (vitrine / travel-web)** : même durcissement dans
  `front/web/src/lib/backend-url.ts`, `front/travel-web/src/lib/backend-url.ts` (copies
  synchronisées) et `front/web/src/lib/csp.ts` — throw au **runtime** de production
  (résolution d'URL lors d'une requête, et côté client), jamais pendant la phase de build
  Next (`NEXT_PHASE === PHASE_PRODUCTION_BUILD`, cas du job CI lighthouse qui build sans
  secrets backend). Scénarios automatisés : `front/web/src/lib/__tests__/backend-url.test.ts`
  et `front/web/src/lib/__tests__/proxy-csp.test.ts` (repli + warn en dev/test et en phase de
  build ; erreur actionnable au runtime prod ; aucun warn quand la variable est posée).
- **Surface API / mobile** : aucun changement de code, aucun scénario nouveau requis.

## Mise à jour 2026-09-20 — refonte UX espace web client (PRs #7882..#7886, issues #7860..#7864)

- **Surface API** : `PATCH /auth/profile` accepte désormais `phone` (#7861 — cas PHPUnit
  `test_employee_can_update_name_and_phone_without_email` dans `AuthProfileSettingsTest`) ;
  nouveaux endpoints `POST/GET /billing/collections` (#7863 — suite `BillingCollectionApiTest` :
  création/listing/pagination, validation 422, RBAC principal 403, isolation cross-tenant) et
  type de profil `cash` (`TenantPaymentProfileApiTest` étendu) ; flux d'invitation durci (#7864 —
  `EmployeeInvitationOnboardingTest` : préservation des resource-assignments au resend,
  employé archivé → 410 à l'activation).
- **Surface web** : suites Jest nouvelles/adaptées — `account-page.test.tsx` (8 cas : profil
  éditable, PATCH + resync session, mot de passe, panneau 2FA, carte abonnement),
  `encaissements-page.test.tsx` (6 cas : familles, profil cash sans secret, encaissements
  enregistrés), `employees-page.test.tsx` (11 cas : page unique équipe, grants, invitations,
  archivage), `team-page.test.tsx` (redirection), `layout-header-menu.test.tsx` (menu avatar
  simplifié + hover-intent), `tenant-branding.test.ts` (9 cas : cache + événement).
- **Surface mobile** : clés ARB propagées par `sync-mobile.js` uniquement (catalogue partagé
  `settingsPage.*`), aucun contrat mobile modifié — aucun scénario mobile nouveau requis.

## Mise à jour 2026-09-20 — remise au vert des e2e web sur main (PR #7956, issue #7955)

- **Surface web** : specs Playwright réalignées sur l'UX mergée (#7882..#7886, #7853, #7748) —
  `auth-client-smoke`, `marketing-funnel`, `funnel-tracking`, `payroll-compliance`,
  `shop-order`, fixtures `authenticated.ts` (mocks branding/invitations) ; régression réelle
  réparée dans `/employees` (chargement non bloquant). Admin : `sidebar-unique-entries`
  réaligné (Formations sous « RH & Paie »).
- **Surface mobile** : uniquement la correction d'accents du catalogue partagé propagée aux
  ARB par `sync-mobile.js` (« Barèmes fiscaux »/« Taux légaux », régression #7725) — aucun
  contrat ni écran mobile modifié, aucun scénario mobile nouveau requis.
- **Surface API** : aucun changement de code backend, aucun scénario nouveau requis.
## Mise à jour 2026-09-20 — affectation d'employés aux succursales restaurant (PR #7919, issue #7909)

- **Surface API** : nouveaux endpoints `GET/POST /restaurant/branches/{branch}/staff` et
  `PATCH/DELETE /restaurant/branches/{branch}/staff/{assignment}` (module RestaurantManager,
  table `restaurant_branch_staff`). Scénarios PHPUnit : suite `RestaurantBranchStaffTest`
  (CRUD complet, 409 doublon + restauration d'une affectation soft-deleted, 422 employé
  cross-tenant, 404 branche d'un autre tenant, 403 RBAC ressource-scopée
  `restaurant_branch`).
- **Surface web** : page `/restaurant/team` (BranchSelect partagé, affectation, changement de
  rôle, retrait) — suite Jest `restaurant/team/__tests__` + tuile « Équipe » du hub
  restaurant ; préfixe protégé inchangé (`/restaurant`). Scénario e2e Playwright dédié dans
  `client-business-flows` (parcours affecter → renommer rôle → retirer).
- **Surface mobile** : clés ARB propagées par `sync-mobile.js` uniquement (catalogue
  `restaurant.team.*`), aucun contrat mobile modifié — aucun scénario mobile nouveau requis.

## Mise à jour 2026-09-28 — assistant IA du portail web (PR #8253, issue #8224, BOS-035)

- **Surface web** : nouvelle page protégée `/assistant` (module `front/web/src/modules/assistant/`)
  — panneau de chat, badges `tools_used`, cartes de confirmation write (confirm/reject),
  historique des conversations ; préfixe protégé `/assistant` ajouté (`protected-prefixes.ts`,
  `proxy.ts`, `sw.js`). Scénarios : e2e Playwright `front/web/e2e/assistant.spec.ts` (question →
  tool → réponse ; write → confirmation → exécution) + Jest `assistant-panel.test.tsx`.
- **Surface mobile** : clés ARB propagées par `sync-mobile.js` uniquement (catalogue partagé
  `assistant*`), aucun contrat mobile modifié — aucun scénario mobile nouveau requis.
- **Surface API** : aucun changement backend (consomme les endpoints assistant existants),
  aucun scénario nouveau requis.

## Mise à jour 2026-10-11 — vitrine vague 2 : héro 3D étiquetée et premium, navbar « Carrières », newsletter compacte, connexion harmonisée (PR #8416, issues #8403 → #8410)

- **Surface web** : pages `/`, `/signup`, `/auth/login`, navbar et footer — constellation
  3D agrandie et alignée haut avec étiquettes de modules orbitaux et indice d'interaction
  (#8403, #8404), rendu premium de la scène (#8405), entrée navbar « Carrières » à la
  place de « Contact » (#8406), newsletter footer compacte (#8407), page de connexion
  harmonisée sur la grammaire de /signup (#8408, #8409), scène 3D de fond toujours
  estompée (#8410). UI/i18n uniquement, aucun flux fonctionnel nouveau : les suites Jest
  vitrine existantes couvrent le périmètre (156 suites / 1332 tests vertes — Navbar,
  HeroSection, HeroScene3D, SignupForm, login) ; aucun scénario e2e nouveau requis.
- **Surface mobile** : clés ARB propagées par `sync-mobile.js` uniquement (catalogue
  partagé `vitrine.nav.careers`, `vitrine.hero3d.*`), aucun contrat mobile modifié —
  aucun scénario mobile nouveau requis.
- **Surface API** : aucun changement backend — aucun scénario nouveau requis.

## Mise à jour 2026-10-12 — espace restaurateur : rotations, app mobile serveur et réseaux i18n (PR #8430, issues #8431, #8432)

- **Surface API** : aucune route nouvelle — la page Rotations consomme l'API Planning/HR
  existante (`GET/POST /schedules`, `PUT/DELETE /schedules/{id}`,
  `POST /schedules/{id}/assign-employees`). Les seuls changements backend sont i18n et
  conformité (contrat `LoggerInterface` dans `CompleteSetupInterview` à la place de la
  façade `Log` ; `SolutionActivator` non `final` pour rester mockable) — les scénarios API
  existants restent valables, aucun scénario nouveau requis.
- **Surface web admin** : catalogues `front/admin-dashboard/src/i18n/locales/*.json`
  resynchronisés (clés `restaurant.rotations.*` et `restaurant.home.rotations/mobileServer`)
  — clés additives uniquement, aucune interaction admin modifiée, aucun scénario admin nouveau
  requis.
- **Surface web (front/web)** : nouvelle page protégée `/restaurant/rotations` (préfixe
  `/restaurant` déjà couvert par `protected-prefixes.ts`) et deux nouvelles tuiles du hub
  `/restaurant` — hors périmètre de la présente garde ; vérifications dédiées dans la PR
  (tsc, eslint, tsc vitrine).
- **Surface mobile** : clés ARB propagées par `sync-mobile.js` uniquement (catalogue partagé
  `restaurant.rotations.*`), aucun contrat mobile modifié — aucun scénario mobile nouveau requis.

## Mise à jour 2026-10-13 — héro immersif vague 3 : constellation 12 modules, nuit permanente (PR #8443, issues #8437, #8438, #8439)

- **Surface API** : aucun changement backend — aucun scénario nouveau requis.
- **Surface web (front/web)** : héro d'accueil retravaillé (constellation pleine scène
  à 12 modules en orbite, nébuleuses additives, « Pile Leopardo » 3D retirée, trame du
  héro stage déplacée en globals.css) — UI/i18n uniquement, les suites Jest vitrine
  existantes couvrent le périmètre (HeroBusinessOS3D, HeroSection) ; tolérance
  documentée pour l'hexa #6366f1 (nébuleuse indigo, web-design-hex-allowlist.txt) —
  aucun scénario e2e nouveau requis.
- **Surface mobile** : clés ARB propagées par `sync-mobile.js` uniquement (catalogue
  partagé `vitrine.hero3d.*`), aucun contrat mobile modifié — aucun scénario mobile
  nouveau requis.

## Mise à jour 2026-10-13 — héro « constellation France » + inscription pack-aware (PR #8463, issues #8461, #8462)

- **Surface API** : `VerifyTrialSignup` mémorise `metadata.vertical` après activation des
  solutions sectorielles (miroir du parcours guidé) — assertion ajoutée à
  `TrialSolutionsActivationTest` (suites PHP/PHPStan déléguées à la CI, pas de runtime PHP
  dans le sandbox) ; aucune route, aucun contrôleur, aucun contrat modifié — aucun
  scénario API nouveau requis.
- **Surface web (front/web)** : héro enrichi (France en constellation en toile de fond,
  étoiles filantes, scintillement de la poussière, responsive mobile du premier écran) et
  inscription pack-aware (`/signup?pack=<slug>` → `solutions[]`, bandeau i18n ×4,
  question secteur exclue de l'entretien si verticale connue) — 13 tests Jest ajoutés
  (pack-signup, SignupForm, SetupInterview, FranceConstellation) ; E2E funnel vérifié
  6/6 chromium — aucun scénario e2e nouveau requis.
- **Surface mobile** : clés ARB propagées par `sync-mobile.js` uniquement (catalogue
  partagé `signup.packSelected`), aucun contrat mobile modifié — aucun scénario mobile
  nouveau requis.

## Mise à jour 2026-10-16 — Marketing OS : centre de commande marketing du Super Admin (PR #8529, issue #8528)

- **Surface API HTTP** : nouveau groupe de routes Super Admin
  `platform/marketing-os/*` (+ alias `admin/marketing-os/*`), garde
  `auth:super_admin_api` + `platform.permission:marketing.manage` — tableau de
  bord, stratégie/calendrier (`content_ideas`), médiathèque (`marketing_assets`),
  campagnes (`marketing_campaigns`), publications par plateforme
  (`marketing_publications`), promotion d'une idée en tâche de production
  (`marketing_production_tasks`) et résolution du mode de publication
  `direct`/`assisted`. Scénarios automatisés : `MarketingOsPlatformTest`
  (12 cas — isolation tenant, promotion idée → tâche, ressource reliée à
  plusieurs publications, suivi par plateforme/statut, distinction
  direct/assisté, conservation de l'URL et de la date réelles, RBAC).
- **Surface web (front/admin-dashboard)** : espace « Marketing OS »
  (`views/marketing/MarketingOsView.vue` + panneaux `MarketingOsDashboardPanel`,
  `MarketingOsStrategyPanel`, `MarketingOsCompanyPicker`, `MarketingOsStatusBadge`),
  service `services/marketingOs.js`, entrée de navigation et route protégée —
  clés i18n ×4 (`front/admin-dashboard/src/i18n/locales/*.json`).
- **Surface mobile** : aucun contrat mobile modifié — aucun scénario mobile
  nouveau requis.

## Mise à jour 2026-10-16 — Marketing OS, lots #8530–#8533 : statistiques réelles, visibilité, capacités de publication, IA assistée (PR #8574)

- **Surface API HTTP** : extension du groupe Super Admin `platform/marketing-os/*`
  (+ alias `admin/marketing-os/*`), mêmes gardes `auth:super_admin_api` +
  `platform.permission:marketing.manage`. Nouvelles routes — rafraîchissement des
  statistiques d'une publication (`POST …/publications/{id}/stats/refresh`), objectifs
  de visibilité SEO/SEA/AEO/GEO (`GET`/`POST …/visibility-objectives`), capacités de
  publication par plateforme (`GET …/publishing-capabilities`) et brief de production
  assisté par IA (`POST /v1/marketing/ai/suggest-production-brief`). Scénarios
  automatisés : `MarketingPublicationStatsTest` (statistiques issues d'un appel API
  **réel**, jamais inventées ; `unavailable` sans donnée ; mesure antérieure jamais
  détruite par un échec), `MarketingVisibilityObjectiveTest` (contenu ↔ objectif,
  provenance `measured`/`estimated`/`unavailable` fail-closed, aucune visibilité
  garantie, aucun système parallèle au centre de visibilité existant),
  `MarketingPublishingCapabilityTest` (source de vérité « direct vs assisté »,
  YouTube/TikTok déclarés sans connecteur, Profile Key **chiffrée**, échec alimentant
  `error_message` + `attempt_history` sans écraser l'historique) et
  `MarketingAiProductionBriefTest` (`media_generated` toujours `false`, aucun statut de
  publication modifié par un appel IA).
- **Surface web (front/admin-dashboard)** : `front/admin-dashboard/src/services/marketingOs.js`
  étendu aux nouveaux endpoints (statistiques, visibilité, capacités) — clés i18n ×4
  inchangées côté service.
- **Surface mobile** : aucun contrat mobile modifié — aucun scénario mobile
  nouveau requis.

## Mise à jour 2026-10-16 — KDS/POS branchés sur la file hors-ligne (PR #8535, issues #8493, #8494)

- **Surface API** : aucun changement backend — aucun scénario API nouveau requis.
- **Surface web (front/web)** : les écrans cuisine (KDS) et caisse (POS) passent par la
  file hors-ligne partagée. Nouveaux : hook `useOfflineSync` (câblage DOM online/offline,
  rejeu à la reconnexion, erreurs conservées) et composant `ConnectionIndicator`
  (état de connexion + opérations en attente + « Réessayer »). Scénarios Jest :
  `use-offline-sync.test.ts` (4 cas : envoi en ligne, mise en file hors ligne + rejeu,
  idempotence, échec conservé) ; `restaurant-kitchen-page.test.tsx` (4 cas, inchangés).
  Le POS utilise une clé d'idempotence STABLE (session + panier) → pas de double commande.
- **Surface mobile** : clés ARB propagées par `sync-mobile.js` uniquement (catalogue
  partagé `offline.*`), aucun contrat mobile modifié — aucun scénario mobile nouveau requis.

## Mise à jour 2026-10-16 — BC-24 TRAVEL OS : QR du billet, vérification d'authenticité, position véhicule (PR #8541, issues #8538, #8539, #8540)

- **Surface API** : deux routes ajoutées à `api/routes/modules/travelagency.php` —
  `POST /travel/tickets/verify` (vérification d'authenticité en **lecture seule**,
  distincte de la consommation `check-in` ; statut `valid`/`void`/`expired`/`already_used` ;
  code invalide et référence inexistante → **même** 404 ; cross-tenant → 404 ;
  permission `travel.checkin`) et `POST /travel/vehicles/{travelVehicle}/position`
  (dernière position connue, source obligatoire `gps`/`app`/`estimated`, permission
  `travel.manage`, cross-tenant → 404). Scénarios détaillés :
  `docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- **Surface web (front/travel-web, front/admin-dashboard)** : aucun contrat modifié —
  aucun scénario web nouveau requis. Le QR est rendu dans le PDF du billet existant
  (`api/resources/views/travel/ticket.blade.php`), l'application mobile de scan n'est
  pas modifiée (l'endpoint accepte le contenu scanné **ou** une saisie manuelle).
- **Surface mobile** : aucun contrat mobile modifié — aucun scénario mobile nouveau requis.
- **Scénarios automatisés** : `api/tests/Feature/Travel/TravelOsQrGpsConcurrencyTest.php`
  (20 cas, 55 assertions) — charge utile QR sans PII, aller-retour `parse`, rejet de
  charge utile inconnue, PNG valide, `data:` URI dans le PDF, vérification
  valide/annulé/expiré/déjà utilisé, code invalide, cross-tenant, permission refusée,
  non-consommation par la vérification, idempotence du check-in (opérateur + horodatage
  + unicité de l'événement outbox), billet annulé refusé, véhicule sans position,
  position GPS, position estimée marquée non-GPS, source inconnue rejetée, cross-tenant.

## Mise à jour 2026-10-16 — enrôlement/révocation d'appareils (PR #8537, issue #8499)

- **Surface API** : nouvelle route tenant `DELETE /api/v1/edge/{nodeId}`
  (`EdgeNodeController::revoke`, groupe `auth:sanctum` + `tenant` + `api.manager`)
  — révocation d'un appareil par le tenant lui-même, portée STRICTEMENT limitée à
  la `company_id` de l'utilisateur (nœud d'un autre tenant → 404, jamais de fuite
  inter-tenant). Aucun contrat existant modifié (les routes `GET/POST /api/v1/edge`
  et `POST /{nodeId}/sync` restent inchangées). Scénario : couvert par le test
  d'écran `devices-page.test.tsx` (appel `DELETE /edge/{id}`) ; le test d'intégration
  backend d'isolation inter-tenant est suivi par #8496.
- **Surface web (front/web)** : nouvelle page protégée `/settings/devices` — enrôlement
  (nom, établissement, poste), affichage du jeton UNE SEULE FOIS avec avertissement,
  liste des appareils (état en ligne / dernière synchro), révocation. Scénarios Jest :
  `devices-page.test.tsx` (3 cas : chargement de la liste, jeton affiché une fois +
  avertissement, révocation via `DELETE /edge/{id}`).
- **Surface mobile** : clés ARB propagées par `sync-mobile.js` uniquement (catalogue
  partagé `devices.*`), aucun contrat mobile modifié — aucun scénario mobile nouveau requis.

## Mise à jour 2026-10-16 — verticale agricole Farm OS (BC-37@@FARM@@, PR #8572, issues #8560-#8564)

- **Surface API** : nouvelle surface tenant `/api/v1/farm/*` (27 routes, `api/routes/modules/farm_os.php`)
  — réglages (`PUT /farm/activities`, `PUT /farm/capabilities`, `GET /farm/capabilities/available`),
  `GET /farm/dashboard`, `GET|POST /farm/sites`, `GET|POST /farm/parcels`
  (+ `GET /farm/parcels/{id}/history`), `GET|POST /farm/crop-cycles`
  (+ `POST /farm/crop-cycles/{cycle}/close`), `GET|POST /farm/operations` (+ `/today`, `/late`,
  `/start`, `/complete`, `/skip`, `/cancel`, `/assign`, `/progress`, `/report-problem`).
  Toutes derrière le gate `module.farm_os` (feature flag `companies.features.farm_os`,
  fail-closed ⇒ 403 sans le flag). Aucun contrat existant n'est modifié.

- **Scénarios attendus (À EXÉCUTER en CI — non exécutés dans cet environnement, cf. limites) :**

| Cas | Attendu |
|---|---|
| requête `/api/v1/farm/*` sans `companies.features.farm_os` | 403 (gate module, fail-closed) |
| outil métier non activé (`farm_capabilities.enabled = false`), appel **direct** de l'API | 403 (gating serveur, pas seulement UI) |
| parcelle avec `area_value` sans `area_unit` (ou l'inverse) | 422 |
| parcelle `area_source = measured` **sans** géométrie stockée | 422 (ADR-FARM-0003) |
| parcelle `area_source = measured` **avec** géométrie | 201, `geometry_available = true` |
| superficie saisie sans `area_source` | 201, provenance enregistrée `declared` (jamais `measured` par déduction) |
| planifier `fertilization` / `crop_protection` sans capacité `stocks_inputs` | 403 |
| opération `planned` → `done` directement | 422 (`INVALID_TRANSITION`) |
| modification d'une opération `done` (`/progress`) | 422 (`OPERATION_IMMUTABLE`) |
| `cancel` d'une opération `in_progress` sans motif | 422 (motif requis) |
| rejeu `POST /farm/sites/{site}/operations` avec le même `client_operation_id` | même opération renvoyée, **une seule ligne persistée** |
| `PUT /farm/activities` : ajout d'une activité, retrait d'une autre | ajout idempotent ; retrait = clôture `ended_at`, **aucune donnée supprimée** |
| valeur hors registre fermé (activité, `op_type`, `area_unit`) | 422 (allowlist fail-closed) |
| ressource d'un autre tenant | 404/403 uniforme, aucune fuite inter-tenant |
| `GET /farm/dashboard` | sections filtrées par capacités RÉELLEMENT activées + `pending_capabilities` pour les modules non livrés (jamais de zéro trompeur) |

- **Couverture livrée et RÉELLEMENT EXÉCUTÉE (domaine pur, sans base)** :
  `api/tests/Unit/Modules/FarmOs/` — `FarmAreaGuardTest` (cohérence valeur/unité, `measured` sans
  géométrie refusé, défaut `declared`, conversion m²), `FarmCapabilityRecommenderTest` (profil
  végétal sans cheptel, éleveur sans irrigation, socle toujours proposé, valeurs inconnues
  ignorées), `FarmOperationStatusTest` (machine à états fermée, opération close immuable),
  `FarmOsManifestTest` (identité `farm_os`/`agriculture`, dépendance dure `rh` seule, permissions
  `farm.*`, 13 capacités) → **23 tests, 85 assertions, OK** (PHPUnit 11.5, bootstrap PSR-4 autonome,
  PHP 8.2 local) ; `php -l` → 0 erreur sur les 35 fichiers Farm OS.

- **Limites déclarées** : `composer install` impossible dans l'environnement de la session (vendor
  absent ; `pdo_pgsql`, `redis`, `imagick` et PostgreSQL indisponibles) ⇒ **aucun scénario HTTP/feature
  de ce tableau n'a été exécuté** ; ils restent à couvrir par `api/tests/Feature/FarmOs/**` sur
  runner CI. L'isolation multi-tenant et le gating par permission ont été vérifiés par lecture du
  socle (`BelongsToCompany`, `TenantManager`, `EmployeeModuleGrant`), **pas par exécution**.

- **Surface web / mobile** : aucune page web ni clé ARB Farm OS dans cette tranche ⇒ aucun scénario
  Jest/Flutter nouveau requis. Onboarding (étapes front) et application mobile agricole restent à
  concevoir après audit (Phase 4 de la mission).

## Mise à jour 2026-10-16 (2) — Farm OS étape 4 : PROVISIONNEMENT de l'espace (BC-37, PR #8572)

- **Surface API ajoutée** : `POST /api/v1/farm/provision` (`api/routes/modules/farm_os.php`,
  `FarmSettingsController::provision`) — la surface tenant `/api/v1/farm/*` passe de 27 à **28 routes**.
  Toujours derrière le gate `module.farm_os` (fail-closed ⇒ 403 sans le flag). Aucun contrat existant
  n'est modifié ; réutilise le mécanisme d'activation existant (`SolutionActivator::activateWithDependencies`)
  — **aucun second système de tenants, rôles, permissions ou provisioning**.

- **Scénarios attendus (À EXÉCUTER en CI — non exécutés dans cet environnement, cf. limites) :**

| Cas | Attendu |
|---|---|
| `POST /farm/provision` avec une activité valide et aucun outil décidé | 200 ; flag `farm_os` posé ; outil = proposition du recommandeur ; refus explicites persistés pour le reste |
| rejeu du même `POST /farm/provision` | 200, statut `already_active` ; **aucune** ligne dupliquée (`farm_activities`, `farm_capabilities`) |
| `POST /farm/provision` avec `capabilities: {parcels_crops: false}` | outil refusé persisté et **respecté** (jamais réactivé par le recommandeur) |
| refus explicite rejoué une seconde fois | le refus **reste** un refus (idempotence ≠ réinitialisation) |
| profil `cereal_crops` uniquement | jamais de capacité `livestock` ; jamais d'action « cheptel » dans `next_actions` |
| profil `cattle` uniquement | capacité `livestock` activée ; jamais de capacité `irrigation` |
| `POST /farm/provision` sans `activities` ou avec une valeur hors registre fermé | 422 (allowlist fail-closed) |
| `POST /farm/provision` puis relecture de la `checklist` | `farm_first_site` / `farm_first_parcel` / `farm_first_operation` à `false` — le provisioning ne prétend jamais avoir créé de donnée métier |
| provisioning de l'exploitation A | aucune ligne `farm_*` pour l'exploitation B ; `farm_os` non activé pour B (isolation multi-tenant) |

- **Couverture livrée et RÉELLEMENT EXÉCUTÉE (domaine pur, sans base)** : `api/tests/Unit/Modules/FarmOs/`
  + `api/tests/Unit/Core/Feature/` → **43 tests, 741 assertions** (PHPUnit 11.5, PHP 8.2 local) ;
  `php -l` → 0 erreur sur les fichiers modifiés. Les 2 échecs restants de cette sélection
  (`ModuleRegistryGuardTest` clé « fuel » ; `SetupInterviewPlannerTest` mapping `commerce`→`retail`)
  sont **pré-existants sur `main`** (run CI `38855132171`, commit `bb0d4153`) et hors périmètre Farm OS.

- **Contrat de provisioning couvert par tests feature à exécuter en CI** (aucune base ici) :
  `api/tests/Feature/Modules/FarmOs/FarmProvisioningTest.php` — 9 cas (activation, idempotence,
  non-destruction d'un refus, modularité végétal/élevage, checklist honnête, actions suivantes bornées
  aux outils activés, isolation multi-tenant).

- **Limites déclarées** (mise à jour 2026-10-19) : PostgreSQL 15 + PostGIS sont désormais
  disponibles dans l'environnement de session (`php8.2-pgsql`, base `leopardo_test`) ⇒ **les
  scénarios Feature Farm OS sont exécutés localement** : `tests/Unit/Modules/FarmOs` **87 tests /
  472 assertions** et `tests/Feature/Modules/FarmOs` **104 tests / 249 assertions**, 0 échec.
  Invocation locale : `CI=true DB_HOST=127.0.0.1 php vendor/bin/phpunit tests/Feature/Modules/FarmOs`
  (`CI=true` neutralise la réécriture d'hôte `isRunningInsideDocker()` de `tests/TestCase.php`).
  Reste **non exécutable localement** : `vendor/bin/pint` exige PHP ≥ 8.3 (session en 8.2) ⇒ la
  conformité de style est vérifiée en CI par le job `Backend Quality` ; le formatage a toutefois été
  contrôlé hors CI avec `php-cs-fixer` 3.64 en appliquant les règles du preset Laravel.

- **Suite de régression d'isolation ajoutée (2026-10-19)** : `FarmTenantIsolationTest` (7 cas) couvre
  récoltes, cheptel, finance et file de synchronisation ; `FarmInputConcurrencyTest` (8 cas) couvre le
  rejeu du même `client_movement_id`, le refus de surconsommation, l'ajustement absolu convergent et la
  cohérence `quantity_after` ↔ quantité du lot. **Toute ligne étrangère est insérée en SQL brut puis
  relue via `crossTenantForPlatformAdmin()`** : `BelongsToCompany` écrase `company_id` à l'écriture
  (#7646), donc un objet construit en PHP ne prouverait rien.
  Ces tests ont révélé un **trou d'isolation réel** : `FarmProfitabilityService::linkSale()` acceptait
  n'importe quel identifiant de lot de récolte ou d'animaux sans vérifier son propriétaire. La garde
  `FARM_FINANCE_SALE_TARGET_NOT_IN_COMPANY` refuse désormais l'imputation sur une ligne appartenant à
  un autre tenant.
- **Surface API (SUPERAPP-P0, #8611)** : nouvelle route publique `GET /api/v1/public/discover`
  (découverte cross-verticale, sans auth, `throttle:shop-public`). Scénarios couverts par
  `api/tests/Feature/Platform/PublicDiscoveryTest.php` : (1) agrégation multi-verticale
  (restaurant + hébergement + vitrine publiés) ; (2) fail-closed — entité `is_public=false`,
  société `suspended`/`expired` ou feature verticale absente → **jamais** retournée ;
  (3) `?type=` inconnu → `422` ; (4) `?near=lat,lng` → seules les entités géolocalisées
  dans `radius_km`, triées par distance croissante ; (5) DTO public strict (aucun `id`,
  `company_id`, `status`) ; (6) `per_page` borné à 50. Aucune migration, aucun nouveau rôle.

## Mise à jour 2026-10-18 — pilotage des stacks de déploiement (PR #8699, issues #8671-#8674)

- **Surface API (superadmin)** : deux routes plateforme sous
  `platform.permission:infra.manage` —
  `GET /api/v1/platform/infra/stacks` (état des stacks dev/prod lues dans
  `deploy/*-stacks.json` + `dispatch_configured`) et
  `POST /api/v1/platform/infra/stack-switch` (bascule d'un tier).
  Contrats documentés dans `api/openapi.yaml` (schémas `PlatformInfraStack`,
  `PlatformInfraTier`).
- **Scénarios attendus côté API** :
  | Cas | Attendu |
  |---|---|
  | appel sans `infra.manage` (support, finance, marketing) | **403** |
  | `GET /stacks` avec jeton configuré | 200, `dispatch_configured: true`, une entrée par tier |
  | `GET /stacks` sans `GITHUB_STACK_SWITCH_TOKEN` | 200, `dispatch_configured: false` (écran informatif) |
  | `POST` sur la stack déjà active | **409**, `reason: deja_active` |
  | `POST` sur une stack `planned` (sans `health_url`) | **409**, `reason: non_basculable` |
  | `POST` sur une stack absente du registre | **422**, `reason: stack_inconnue` |
  | `POST` avec `confirm` absent/faux | **422** (garde d'interface) |
  | `POST` valide, jeton absent | **503** (fail-closed, rien n'est déclenché) |
  | `POST` valide, jeton présent | **202**, `data.previous` = stack active avant bascule |
  | rejeu du même `POST` après succès du workflow | la stack cible est devenue active → **409** (aucun ping-pong) |
- **Surface infrastructure (non testable en PHPUnit)** : `scripts/stack-switch.sh`
  refuse en prod `--seed`, `--force` seul, une cible sans `health_url`, une cible
  malade, et une cible dont `db.host` diffère de la stack active. Scénarios
  couverts par exécution réelle du script sur le registre du dépôt
  (`--dry-run`) et par `scripts/stack-health.sh --tier prod`.
- **Surface admin (Vue)** : carte `InfraStacksCard.vue` sur Supervision Système —
  bouton désactivé si `dispatch_configured=false`, refus de l'API remonté en
  toast. Scénario manuel : couper `GITHUB_STACK_SWITCH_TOKEN` sur le service API
  et vérifier que l'écran reste lisible et n'offre pas de bascule.

## Chat horizontal — groupes de discussion (#8746)

- **Contexte** : le fil de discussion existant (`PA2-COMM-002`, `/conversations`)
  est binaire (employé ↔ manager). Cette livraison ajoute `kind = group` avec
  `conversation_participants` (rôles `creator`/`admin`/`member`, curseur de
  lecture par participant). L'appartenance d'un groupe est régie **uniquement**
  par la table de participants ; celle d'un fil `direct` reste déduite de
  `employee_id`/`manager_id`.
- **Surfaces API** : `POST /api/v1/conversations/groups`,
  `GET|POST /api/v1/conversations/{thread}/participants`,
  `DELETE /api/v1/conversations/{thread}/participants/{employee}`,
  `POST /api/v1/conversations/{thread}/leave`.
  Contrats documentés dans `api/openapi.yaml` (paramètre `ConversationThreadId`,
  schémas `ConversationThread`, `ConversationParticipant`).
- **Scénarios attendus côté API** :
  | Cas | Attendu |
  |---|---|
  | création avec participants du même tenant | **201**, `kind: group`, `created_by`, une ligne `creator` + N lignes `member` |
  | création avec un `employee_id` d'un autre tenant | **422**, aucune ligne créée |
  | création sans aucun autre participant | **422** |
  | ajout par un `member` | **404** (jamais 403 : ne révèle pas l'existence du fil) |
  | ajout par `creator`/`admin` | **201**, `role: member` |
  | ré-ajout d'un membre déjà présent | **201**, ligne existante (idempotent, pas de doublon) |
  | retrait d'un `member` par un `admin` | **204**, ligne supprimée |
  | retrait d'un `admin` par un autre `admin` | **404** |
  | retrait du `creator` (même par lui) | **422** |
  | `leave` par un `member` | **204**, puis `GET /conversations/{thread}` → **404** |
  | `leave` par le `creator` | **422** (doit transmettre son rôle) |
  | tout endpoint de groupe par un non-participant | **404** sur les 5 |
  | `GET /conversations` depuis un autre tenant | **200** avec `data: []` (aucune fuite) |
  | lecture par un participant | met à jour **son** `last_read_message_id`, pas celui des autres |
  | fil `direct` existant après migration | `kind: direct`, **aucune** ligne de participation, contrat inchangé |
- **Isolation multi-tenant** : les identifiants de participants sont résolus avec
  `company_id` explicite ; un id étranger produit 422 et jamais une ligne
  silencieusement ignorée. `BelongsToCompany` + `findForActor()` restent le
  garde-fou de lecture.
- **Non couvert par tests** : charge (`k6`), reconnexion UI (dépend de la phase
  P6), `migrate` complet hors environnement de test. Spécifications fournies au
  §13 de `docs/mission-business-os/16_chat_horizontal_audit.md`.

## Mise a jour 2026-10-19 — Chat horizontal, phases P2 a P6 (PR feat/bos-chat-phases-2-6, issues #8747, #8748, #8749, #8750, #8751)

Perimetre : extension du module existant `App\Modules\Notification` (BC-13 COMMS).
Aucun nouveau BC, aucun second systeme de messagerie, d'auth, de notification ou
de fichiers.

- **Surfaces API ajoutees ou etendues** :
  `GET /api/v1/conversations/{thread}/messages` (curseur `before_seq` /
  `after_seq` / `per_page`), `PATCH|DELETE /api/v1/conversations/{thread}/messages/{message}`,
  `POST /api/v1/conversations/{thread}/reports`,
  `GET|POST /api/v1/conversations/blocks`,
  `DELETE /api/v1/conversations/blocks/{employee}`,
  `GET|POST /api/v1/conversations/reports`,
  `GET /api/v1/conversations/moderation/reports`,
  `POST /api/v1/conversations/moderation/reports/{report}/resolve`.
  Contrats dans `api/openapi.yaml` (schemas `ConversationMessage`,
  `ConversationBlock`, `ConversationReport`, parametre `ConversationMessageId`).
- **Migrations** : `2026_10_19_000928_8747_...` (colonnes `seq`,
  `client_message_id`, `reply_to_message_id`, `edited_at`, `deleted_at`,
  `deleted_by_id`, `attachment_disk`, `last_message_seq` + index partiels) ;
  `2026_10_19_000929_8750_...` (`conversation_blocks`, `conversation_reports`).
  Les deux sont **additives** et reentrantes (`schemaTableExists` /
  `schemaHasColumn`), avec backfill de `seq` pour l'existant.
- **Scenarios attendus cote API** :
  | Cas | Attendu |
  |---|---|
  | envoi nominal | **201**, `seq` alloue, `last_message_seq` incrementé |
  | rejeu du meme `client_message_id` | **200**, meme `id`, **aucun** second message |
  | meme `client_message_id` dans un autre fil | **201** (unicite **par fil**) |
  | deux envois concurrents, meme cle | **une seule** ligne creee (course rattrapee, 23505) |
  | `reply_to_message_id` d'un autre fil | **422** |
  | `GET` sans parametre | derniere page, ordre `seq` croissant |
  | `before_seq` | messages anterieurs, ordre croissant, `has_more` fiable |
  | `after_seq` | uniquement les messages posterieurs (rattrapage) |
  | `per_page=100000` | **200** mais `per_page: 100` (plafonne) |
  | `PATCH` par l'auteur | **200**, `edited_at` renseigne, aucun nouveau message |
  | `PATCH` par un autre participant (meme manager) | **404**, corps inchange |
  | `PATCH` sur un message supprime | **422** |
  | `DELETE` par l'auteur | **204**, `deleted_at` pose, `seq` conserve, corps vide |
  | `DELETE` repete | **204** (idempotent) |
  | message supprime dans l'historique | ligne presente, `deleted: true`, `body: ""` |
  | message sans corps ni piece jointe | **422** |
  | piece jointe seule | **201**, `body: ""` |
  | piece jointe > 5 Mo | **422** |
  | piece jointe dont le MIME declare ment (ELF en `.pdf`) | **422** (type reel via `finfo`) |
  | telechargement par un non-participant | **404** |
  | fichier absent du disque | **410** |
  | non-participant sur `GET`/`POST`/`PATCH`/`DELETE` | **404** sur chacun, jamais 403 |
  | fil ou message d'un autre tenant | **404** |
  | blocage d'un employe | **201**, unique par paire, idempotent |
  | auto-blocage | **422** |
  | blocage d'un employe d'un autre tenant | **422**, aucune ligne |
  | creation de fil direct apres blocage (les deux sens) | **422** |
  | ajout du bloque dans un groupe | **422** |
  | tiers non concerne par le blocage | **201** (fil cree) |
  | signalement d'un message du fil | **201**, `reported_employee_id` = auteur |
  | signalement d'un message d'un autre fil | **422** |
  | signalement par un non-participant | **404** |
  | motif hors liste fermee | **422** |
  | `GET /conversations/reports` | **200**, uniquement les signalements de l'appelant |
  | file de moderation par un non-manager | **422** |
  | file de moderation par un manager d'un autre tenant | **200** avec `data: []` |
  | cloture d'un signalement deja clos | **200**, premiere decision preservee |
  | envoi au-dela du quota | **429** |
- **Isolation multi-tenant** : toute lecture passe par `findForActor()`
  (scope `company_id` + appartenance) ; les identifiants cibles (blocage,
  signalement) sont resolus **dans le tenant de l'appelant** et un identifiant
  etranger produit **422**, jamais une ligne silencieuse. La file de moderation
  est explicitement tenant-scopee.
- **Lea** : aucun scenario croise. Le chat humain et l'assistant IA restent deux
  systemes separes ; aucun test de chat ne traverse `PlatformAdminAiChatTest`.
- **Resultats reels** (`CI=true`, PHP 8.4 + PostgreSQL 15) :
  `ConversationMessagesTest` **15 tests** verts, `ConversationModerationTest`
  **18 tests** verts, suite conversation **53 tests / 259 assertions** vertes ;
  `pint --test` **PASS** ; **PHPStan niveau 8 `[OK] No errors`** ; frontend
  `tsc --noEmit` 0 erreur, `eslint` 0 warning, `jest` 6/6 sur `lib/chat.ts`.
- **Non couvert par tests** : charge (`k6`) — aucun environnement deploye
  joignable ; la reprise de connexion est couverte cote client (`mergeMessages`)
  et cote API (`after_seq`), mais pas en E2E navigateur ; `CHAT_ATTACHMENT_DISK`
  n'est pas positionne en production, donc la perte des pieces jointes au
  redeploiement (risque R1) reste **ouverte** tant que l'exploitant ne l'a pas
  configure.

## Mise a jour 2026-10-20 — Chat horizontal, lot UI Vue/Flutter + R1 + charge (#8754)

Surfaces **sans nouvelle route** : ce lot ne modifie aucune API. Il ajoute des
clients (Vue, Flutter) et des tests de charge sur `/conversations/*` deja livre.
Le contrat d'autorisation est donc **inchange** et reste verifie par les tests
existants (`ConversationMessagesTest`, `ConversationModerationTest`).

| Cas | Attendu |
|---|---|
| piece jointe ecrite sur le disque A, configuration basculee vers B, telechargement | **200** (le disque est relu depuis le message, pas depuis la config) |
| piece jointe ecrite apres bascule de configuration | **200** depuis le nouveau disque |
| fichier reellement absent du disque | **410**, jamais 500 ni 404 |
| telechargement par un non-participant | **404**, jamais 403 |
| liste des fils sans contexte tenant (super-admin hors impersonation) | **401** -> etat UI explicite « contexte entreprise requis » |
| messagerie desactivee pour l'entreprise | **403** -> etat UI « messagerie desactivee » |
| envoi avec un `client_message_id` deja utilise | **200** (rejeu reconnu), jamais un doublon |
| envoi au-dela du quota d'ecriture | **429** |
| reprise apres coupure reseau (`after_seq`) | messages manques recuperes, **aucune perte, aucun doublon**, ordre `seq` croissant |

- **Isolation** : le client Vue n'affiche que ce que l'API renvoie ; aucun filtre
  d'acces cote navigateur n'est presente comme une securite. Aucun scenario ne
  traverse `PlatformAdminAiChatTest` — le chat humain et **Lea** restent deux
  systemes separes.
- **Resultats reels executes** (`CI=true`, PHP 8.4 + PostgreSQL 15) :
  `ConversationAttachmentPersistenceTest` **4 tests / 19 assertions** verts ;
  `--filter=Conversation` **88 tests / 392 assertions** vertes ;
  `jest src/lib/__tests__/chat.test.ts` **6/6** ;
  **PHPStan niveau 8 `[OK] No errors`** sur `app/Modules/Notification` + le test R1 ;
  `pint --test` PASS sur les fichiers du lot ; `eslint` **0 erreur** ;
  `vite build` **reussi** (30,80 s).
- **Tests de charge reellement executes** (k6 v0.49.0, API locale `artisan serve`
  mono-processus, `CACHE_STORE=array`) : `smoke` **674/674** checks, p95 **111 ms** ;
  `launch` (15 VU) **4365/4396** checks, p95 **827 ms** — seuil de 800 ms
  **franchi**, consigne tel quel ; reconnexion **12/12**, **0 message perdu,
  0 doublon**. Ces valeurs sont un **plancher** de developpement, jamais une
  mesure de capacite de production.
- **Decouverte operationnelle** : avec `CACHE_STORE=redis` et aucun Redis
  joignable, **toutes** les routes repondent `429 TOO_MANY_REQUESTS_DEGRADED`
  (fail-closed du throttle) — le chat depend donc entierement de Redis/Upstash.
- **Non couvert** : Flutter **non compile** (aucun SDK Dart dans l'environnement
  d'execution) ; E2E navigateur de reconnexion ; scenario `growth` (120 VU) ecrit
  mais non execute ; `CHAT_ATTACHMENT_DISK` toujours **non positionne** en
  production, donc **R1 reste ouvert** (`docs/mission-business-os/17_chat_attachments_runbook.md`).
