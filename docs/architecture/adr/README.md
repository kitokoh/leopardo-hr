# Architecture Decision Records

Ce dossier capture les decisions structurantes qui doivent rester stables pour Leopardo. Une ADR est ajoutee quand un choix influence la securite, l'exploitation, le multi-tenant, les contrats API ou les couts de changement futurs.

> **Ce registre est verifie en CI** par `dev-hub/tools/check-docs-hygiene.sh` (workflow
> `docs-hygiene-guard.yml`) : chaque fichier `NNNN-*.md` doit avoir **exactement une**
> entree ici, aucun identifiant ne peut etre en doublon, et chaque ligne du tableau doit
> etre bien formee. Un statut ecrit ici doit correspondre au statut du fichier.

## Registre

| ADR | Statut | Decision |
|---|---|---|
| [0001](0001-multi-tenant-postgresql.md) | Acceptee | Multi-tenant PostgreSQL avec schemas separes et mode shared controle |
| [0002](0002-api-contracts-openapi.md) | Acceptee | OpenAPI canonique dans `api/openapi.yaml`, publie via Swagger UI |
| [0003](0003-ci-github-actions-source-of-truth.md) | Acceptee | GitHub Actions est la source de verite de validation |
| [0004](0004-open-core-marketplace-boundaries.md) | Acceptee | Open core cadre, marketplace via contrats publics et enterprise core prive |
| [0005](0005-clean-architecture-modules.md) | Acceptee | Adoption de la Clean Architecture / decoupage en modules DDD sous `api/app/Modules/` |
| [0006](0006-auth-in-core.md) | Acceptee | Auth dans `app/Core/Auth/` plutot qu'un module, pour eviter les dependances circulaires |
| [0007](0007-progressive-migration-strategy.md) | Acceptee | Strategie de migration progressive flat -> modules DDD (skeleton, cablage, nettoyage) |
| [0008](0008-payment-consent-signature-model.md) | Acceptee | Modele de consentement/signature de paiement, sans PKI premature |
| [0009](0009-ai-agent-tool-contracts-and-human-validation.md) | Acceptee | Contrats d'outils pour l'agent IA : permissions RBAC, audit et validation humaine avant toute ecriture |
| [0010](0010-marketplace-plugin-permissions-billing-webhooks.md) | Acceptee | Plugins marketplace : scopes Sanctum existants, gating via FeaturePlanMatrix, revenu via Partner/Commission, webhooks via AVAILABLE_EVENTS |
| [0011](0011-billing-payroll-domain-boundary.md) | Acceptee | Billing/Payroll : frontiere de domaine — casse la dependance circulaire (regles de facturation dans Billing, calculs de paie dans Payroll) |
| [0012](0012-focus-core-depth-peripheral-maintenance.md) | Proposee | Programme FOCUS : profondeur du noyau (HR/Payroll/Attendance), maintenance du peripherique |
| [0013](0013-notifications-read-path-unification.md) | **Remplacée** (2026-09-16) | Notifications in-app : la bascule vers `app_notifications` est abandonnee — le store canonique est la table `notifications` ; `AppNotification` est deprecie (PR #7537, issue #7481) |
| [0014](0014-plan-pricing-canonical-decisions.md) | Acceptee | Plans tarifaires canoniques, plan Free public, duree d'essai |
| [0015](0015-onboarding-steps-canonical.md) | Proposee | Onboarding : 6 etapes seedees canoniques (dont optionnelles), Quick Start < 15 employes |
| [0016](0016-attendance-smartattendance-fusion.md) | Proposee | Fusion progressive Attendance + SmartAttendance en un module unique (5 phases, zero perte de donnees, contrat API preserve) |
| [0017](0017-paiement-en-ligne-portail-client.md) | Proposee | Paiement en ligne des factures (portail client) : passerelle(s) et architecture |
| [0018](0018-crm-module-boundaries.md) | Ratifiee | Frontieres de dependances du module CRM client, verrouillees par des gardes CI — issue #5745 (CRM PRE) |
| [0019](0019-mcp-external-exposure-deferred.md) | Decisee | Assistant IA (BC-23) : exposition MCP externe differee (v1 = contrat interne seul, pont hybride possible plus tard) — spike #6861 |
| [0020](0020-couches-application-vides-decisions.md) | Actee | Couches `Application/` vides du backlog #6841 : decisions structurelles Fleet, Restaurant, Payroll — aucun code applicatif |
| [0021](0021-chaine-video-topologie-edge.md) | Acceptee | Topologie de la chaine video : noeud Edge |
| [0022](0022-renommage-depot-leopardo.md) | Proposee — decision owner requise | Renommage du depot `leopardo-hr` : inventaire des 378 references, 3 options (renommer `leopardo-suite` / conserver / migration exhaustive), recommandation A — issue #7848 |
| [0023](0023-admin-dashboard-typage.md) | Proposee | admin-dashboard (Vue 3/JS) : typage TypeScript incremental (JSDoc + checkJs progressif puis vue-tsc), gel du JS nouveau via CI — issue #7849 |
| [0024](0024-tables-tenant-scoping-transitif-employe.md) | Acceptee | 4 tables tenant scopees employe sans `company_id` (device_tokens, calendar_connections, calendar_events, app_notifications) : statu quo documente, scoping transitif via employee/user — issue #7984 |
| [0025](0025-sanctum-ttl-30j-glissants.md) | Proposee | Tokens Sanctum : TTL 30 jours glissants justifie (decision proprietaire #7491) + controles compensatoires (purge quotidienne, prefixe `leo_`) — issue #7655 point 3 |
| [0026](0026-registre-unifie-modules-features-solutions.md) | Proposee — validation owner requise | Registre unifie modules/features/solutions : source PHP versionnee unique (pas de table), dual-read reversible, parite feature map prouvee, kill switch priorite DB, `metadata.modules` derive — issue #8148 (BOS-010) |
| [0027](0027-frontiere-notification-communication.md) | Proposee — validation owner requise | Frontiere Notification/Communication : Notification = preferences/push/annonces internes, Communication = canaux externes + threads ; `CommunicationEvent` deplace vers Communication (alias 1 release, table `communication_events` inchangee) ; direction Notification -> Communication — issue #8219 (BOS-025) |
| [0028](0028-shared-schema-definitif.md) | Proposee — validation owner requise | Schema partage definitif : le mode « un schema par tenant » est acte mort — garde de creation conservee, inventaire borne des references legacy, nettoyage physique differe (audit prod puis migration additive) — issue #8203 (BOS-005), amende ADR-0001 |
| [0029](0029-multi-org-comptes-entreprises.md) | Proposee — validation owner requise | Multi-org : go-limite — un compte, plusieurs entreprises via `user_employee_links` + switch explicite post-auth (`POST /auth/switch-company`) ; unicite email et `user_lookups` inchanges ; cas H (cabinet comptable) en acces sequentiel ; holding G-multi-entites reporte (F2) — issue #8225 (BOS-036) |
| [0030](0030-surfaces-publiques-web-vs-marketplace.md) | **Remplacée par ADR-0034** | Surfaces publiques : `front/web` = mono-tenant, `front/marketplace` = agregation cross-tenant ; premisse caduque — les deux apps ont ete retirees (#9627/#9628) et leur contenu vit dans `public-surfaces` — issue #8226 (BOS-051) |
| [0031](0031-absence-module-is-a-facade.md) | Acceptee | Module `Absence` : facade HTTP assumee, domaine absence/conge propriete de Planning ; aucun domaine ajoute dans `Absence/`, nouvelle regle metier -> `Planning` ; couplage unidirectionnel interface->domaine, declare au registre — issue #7959 point 2 |
| [0032](0032-autorisation-avant-validation-et-query-encoding.md) | Acceptee | Autorisation avant validation, et encodage des query strings dans les tests — tranche #9098 (dette de tests backend) |
| [0033](0033-colonnes-identite-et-ecritures-best-effort.md) | Acceptee | Colonnes d'identite assignables et ecritures best-effort dans une transaction — tranche #9098 (dette de tests/API Travel) |
| [0034](0034-public-surfaces-norme-front-public.md) | **Acceptee** | `front/web/src/modules/public-surfaces` est la norme du front grand public : structure en 5 couches par verticale, registre fail-closed, noyau pour le partage, aucune nouvelle app publique ; remplace ADR-0030 — issues #9630, #9627, #9628, #9629 |
| [0035](0035-public-surfaces-norme-apps-mobiles-grand-public.md) | **Acceptee** | La doctrine `public-surfaces` s'applique aussi aux apps mobiles grand public : `leopardo_public` en 5 couches, `domain/` pur oppose par un test, registre fail-closed branche dans la fiche commerce, codes API declares (`hospitality_property`), chemins alignes sur le web ; etend ADR-0034 — issues #9640, #9641, #9642. **Revisee 2026-11-19** : la regle de couche s'etend aux 11 apps professionnelles via `mobile-layer-registry.txt` (dette `flat` declaree), la doctrine de surface reste grand public — issues #9645-#9648. **Revisee 2026-11-19** : la regle de couche s'etend au front web via `web-layer-registry.txt` (`copy/` est une couche dont le domaine ne depend jamais) — issues #9652-#9654 |
| [0036](0036-renommage-api-endpoint-registry.md) | Acceptee | Renommage du registre mobile : FeatureRegistry -> ApiEndpointRegistry (BOS-015, issue #8202) ; clarification homonymie avec ModuleRegistry. **Renumérotée depuis 0028 le 2026-11-22** (collision d'identifiant avec ADR-0028 tenancy) — issue #9770 |
| [0037](0037-levee-gel-scope-mvp.md) | **Acceptee** | Levée du gel de scope MVP du 2026-04-21 : le périmètre livré a dépassé le MVP (41 modules, dont 13 postérieurs à avril 2026) et la catégorie produit a changé (#7428, « suite métier »). Le gel est remplacé par un cadre explicite — critère d'entrée d'un module (problème client nommé ou décision propriétaire tracée), obligation de registre (ADR-0026), obligation de tests (module sans dossier de tests = module non livré), revue périodique du portefeuille. Amende `GO_NO_GO_MVP.md` sans réécrire son historique — issue #9803 |

## Format

Chaque ADR contient :

- Contexte
- Decision
- Consequences
- Regles operationnelles

## Regles de tenue du registre

1. **Un identifiant = une ADR.** Un numero deja pris ne se reutilise jamais, meme si
   l'ADR est remplacee : on ecrit une nouvelle ADR qui la remplace et on met a jour le
   statut de l'ancienne (`Remplacée par ADR-NNNN`).
2. **Toute ADR sur le disque figure ici**, et toute entree ici pointe vers un fichier
   existant. Les deux sens sont verifies en CI.
3. **Le statut du registre est celui du fichier.** Si le fichier dit `Remplacée`, le
   registre ne peut pas dire `Proposee`.
4. **Une ligne = une ligne de tableau.** Pas de cellule orpheline sur la ligne suivante.
