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
