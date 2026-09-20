# Changelog

Toutes les évolutions notables de ce projet sont documentées dans ce fichier.

Le format s'appuie sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/)
et le projet vise l'adhésion au [Semantic Versioning](https://semver.org/lang/fr/).

> **Note — journal historique.** Ce fichier était auparavant un journal d'exécution
> détaillé des agents (385 Ko). Le détail complet de chaque livraison reste disponible
> dans l'historique git de ce fichier, notamment jusqu'au commit
> `0bba16974c1b032a9b9bf19bd4b1db7e9bbc6da2`
> (`git log -p 0bba16974c1b032a9b9bf19bd4b1db7e9bbc6da2 -- CHANGELOG.md`).

## [Unreleased]

### Added

- **Vertical Voyage (Travel)** : référentiel réseau (gares, lignes, arrêts), gestion des voyages et tarifs, réservations/billets avec check-in, rapports gérant et export CSV, équipages liés à la RH, clés API distributeurs, marketplace publique inter-agences et site public Next.js `front/travel-web` (recherche, sièges, checkout, comptes clients cross-agences) avec pipeline de déploiement Vercel.
- **Vertical Restaurant** : profil public par branche + annuaire avec recherche/proximité (RESTO-901), commande en ligne et avis modérés (RESTO-902), encaissement réel via les profils de paiement du tenant (Stripe sur les clés du restaurateur), pilote RBAC ressource-scopé par succursale.
- **Vertical Commerce/Retail & Leopardo Marché (BC-17)** : module vendeur actif (produits, stock, POS, espace `/commerce`), marketplace grand public multi-vendeurs cross-tenant (API publique, checkout invité, web), comptes acheteurs plateforme (favoris, historique, avis vérifiés post-livraison).
- **Vertical Santé/Pharmacie** : MVP PharmaManager complet pour les officines (#7798–#7804).
- **Verticaux Fuel, Edu, Fleet, Cameras** : généralisation du patron RBAC ressource-scopé, consolidation des migrations, module Caméras horizontal auto-activable + application mobile « Leopardo Caméras » (BC-19).
- **Paie multi-pays** : moteur de paie durci (indemnités congés payés, solde de tout compte, certificat de travail, journal mensuel, workflow de clôture via API), mentions légales DZ, cumuls annuels sur bulletin PDF, benchmark 10 000 employés, gate de couverture Payroll ≥ 80 % bloquant.
- **Facturation & paiements (BC-21)** : configuration des passerelles PSP depuis l'admin plateforme (stockage chiffré + fallback env), crédits IA achetables (ledger, checkout, décompte réel), emails facture/reçu avec PDF joint i18n ×4, rapprochement `stripe_invoice_id`, prix des plans paramétrables en base.
- **Module Communication (R0–R6)** : connexion Gmail par utilisateur (OAuth, tokens chiffrés), sync incrémentale, classification IA + liaison CRM, relances automatiques, réponses assistées avec politiques par boîte, UI « Boîte connectée » côté client et plateforme.
- **RBAC & espace client** : cycle de vie des accès ressource (invitation pré-assignée, vue « qui a accès à quoi », révocation en cascade), grants de modules composables par collaborateur, délégation plateforme (rôles internes, écran Équipe), gestion des collaborateurs côté client.
- **Onboarding & acquisition** : entretien de préparation conversationnel (remplace la modale 10 étapes), première connexion sans mot de passe en clair (lien magique), tracking par étape du tunnel d'acquisition avec dashboard des conversions, écran de bienvenue unique.
- **Marketing & support** : campagnes email effectives + IA + interactions sociales, publication sociale via jobs queued (LinkedIn/Meta/Twitter), espace tickets support client avec notifications email bidirectionnelles.
- **Comptabilité** : les 7 écrans web du rôle comptable dans le dashboard admin (documents/factures, plan comptable, grand livre/journal, lettrage…).
- **Branding & i18n** : image de marque du tenant dans l'espace client, thème dashboard et PDF ; internationalisation ×4 locales des surfaces publiques et marketing.

### Changed

- **Positionnement produit** : Leopardo cesse d'être présenté comme un logiciel RH — README, vitrine, console admin, workflows CI et docs alignés « suite métier » (#7428 et suites).
- **Gouvernance du dépôt** : `.gitignore` durci, miroir OpenAPI et SDK générés sortis du versioning, gros artefacts purgés (audit #7654), cartographie et cliquet anti-croissance de la dette PHPStan (#7655), navigation client et sidebar admin refondues (BC-01).
- **Ops/infra** : topologie Render dev/prod honnête (`APP_ENV=staging` + `DEPLOY_TIER=dev`), worker Render dédié queue+scheduler, drills de restauration des backups (PostgreSQL 18, clé `age`, fail-fast), boucle `schedule:run` intérimaire.
- **Migrations & schéma** : consolidation des migrations divergentes CRM/Fuel/EdgeSync/Restaurant/Travel/Edu, règle « une table, une migration » gardée en CI, tests sur les vraies migrations.

### Fixed

- **CI/CD** : suites backend fiabilisées (timeouts de verrous Postgres, reaper idle-in-transaction, dérive de fixtures), gardes governance suivant les déplacements de docs, workflows SHA-pinnés, gate de déploiement non contournable, déploiements Vercel/staging réparés.
- **Isolation tenant** : raccordement systématique des modèles à `BelongsToCompany` (hook `creating` forçant le `company_id`, garde `updating` anti cross-tenant), contexte tenant des jobs queued verrouillé par un test d'architecture, routes plateforme dédupliquées sous `platform.permission`.
- **Front web** : CSP en ENFORCE avec nonce par requête, panneaux d'en-tête, formulaires publics anti-spam (honeypot, time-trap, contrôle d'origine), schémas zod branchés.
- **Audit externe 2026-09-20 (en cours)** : URL backend Render dev codée en dur comme fallback silencieux dans 4 fronts remplacée par un fail-fast en production (#7842) ; assainissement de la gouvernance racine — CHANGELOG réécrit, AGENTS.md recentré, docs de pilotage déplacées, doublon `BRANCH_PROTECTION_REQUIRED.md` résorbé (#7843).

### Security

- **Multi-tenant** : défense en profondeur SmartAttendance, écriture cross-tenant par mass assignment fermée, piste d'audit lisible après purge d'un tenant.
- **Secrets & tokens** : tokens d'auth en cookie httpOnly (front/web + admin-dashboard), bearer super-admin hors du DOM + CSP durcie au build, token kiosk hors DOM + PIN admin, supervision queue sans credentials de prod dans les runners GitHub, alerte hebdo secrets exposés, fin des fallbacks « password123 ».
- **Edge** : TLS interne par défaut sur le LAN client + manifeste d'installation signé RS256.
- **Conformité** : droit à l'effacement RGPD (anonymisation employé), rétention et purge biométrique automatique, journalisation des accès aux données sensibles, bandeau de consentement et traceurs coupés par défaut, chiffrement au repos du metadata des documents de paie.
- **Dépendances** : bumps de sécurité (guzzle CVE-2026-59883/69245/69246, commonmark, shell-quote, sharp…).
- **Audit externe 2026-09-20 (en cours)** : EdgeSync `applyGeneric` — allowlist du nom de table dynamique et forçage tenant du payload (#7840) ; travel-web — token d'auth migré de localStorage vers le pattern cookie httpOnly (#7841) ; durcissement conteneurs et défauts (Dockerfile.edge non-root, mots de passe par défaut, collision port 5432, header `X-XSS-Protection` déprécié retiré) (#7847).
