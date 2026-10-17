# 03 — AppFlow : parcours applicatifs

**Base :** `main` @ `e9c5f986c`
**Convention :** `✅` = chemin réellement câblé (fichier cité) · `⚠️` = partiel/cassé à un
point précis · `⛔` = inexistant. Chaque étape ⚠️/⛔ renvoie à un identifiant d'exigence du
TRD et à un gap de la matrice (`08_gap_matrix.md`).

---

## 1. Parcours A — Onboarding universel « Trade » (priorité mission)

### A.1 État réel du parcours

```
[Vitrine] /
   │
   ├─✅ CTA / navbar → /trade
   │      (vitrine/lib/trade-intent.ts, TradeOnboardingChoice.tsx)
   │
   ├─✅ Choix 1 : International | Domestique
   │      → normalisé par normalizeTradeIntent() (fail-closed)
   │      ⚠️ « importateur / exportateur / les deux » et « grossiste / détail »
   │         ne sont PAS des sous-profils : TRADE_MODES = ['international','domestic']
   │         seulement (gap O-01)
   │
   ├─✅ Choix 2 : Créer un espace | Rejoindre une entreprise
   │      create → /signup?trade=<mode>
   │      join   → /auth/login
   │
   ├─⚠️ /signup reçoit ?trade= mais NE LE LIT PAS
   │      seul ?sector= et ?pack= sont consommés (lib/signup-sector.ts,
   │      pack-signup.ts) → l'intention Trade est perdue (gap O-02)
   │
   ├─✅ Formulaire d'inscription (SignupForm, 1391 lignes) : identité, entreprise,
   │      pays (allowlist /supported-countries), effectif, plan, solutions[]
   │
   ├─✅ POST /api/forms/signup (Next) → POST /api/v1/trial/signup (Laravel, throttle 5/15)
   │      ⚠️ aucune clé d'idempotence cliente : un double-clic sur réseau lent
   │         est borné par le throttle mais pas dédupliqué (gap O-05)
   │
   ├─✅ POST /trial/verify (OTP, throttle:trial-verify, verrou 5 échecs)
   ├─✅ POST /trial/set-password (ou définir le mot de passe sans mailer)
   │
   ├─✅ ProvisionGuidedTrial — idempotent (#3600), transactionnel
   │      crée Company + schema + manager principal + onboarding_steps + solutions
   │      ⚠️ pas d'établissement principal créé (gap O-03)
   │      ⚠️ aucun test de concurrence (double soumission simultanée) (gap O-05)
   │
   ├─✅ Première connexion → /onboarding/welcome-ack
   ├─✅ /setup-interview : 4 questions (sector, premises, priorities, scheduled_hours)
   │      ⚠️ ce n'est PAS le parcours complet de la mission : pas de profil
   │         professionnel, pas d'établissement, pas de données métier initiales,
   │         pas d'équipe/permissions, pas de découverte d'applications,
   │         pas de première opération guidée (gaps O-03, O-04, O-06, O-07)
   │
   ├─✅ GET /onboarding-setup/checklist + PATCH /{stepKey}/complete|skip
   │      10 étapes génériques + étapes verticales (SeedDefaultSteps)
   │
   └─⚠️ Fin de parcours : aucune « première opération métier guidée » générique
          (gap O-07)
```

### A.2 Parcours cible (à implémenter)

```
/  ──► /trade ──► [ Marché ] International | Domestique
                    │            │
                    │            └─► [ Profil domestique ] Grossiste | Détaillant
                    └─► [ Profil international ] Importateur | Exportateur | Les deux
                                  │
                                  ▼
                        [ Action ] Créer un espace | Rejoindre une entreprise
                                  │                    │
                                  │                    └─► /auth/login
                                  │                        → détection d'appartenance
                                  │                        → demande d'accès au tenant
                                  ▼
                        [ Étape 1 ] Profil professionnel (nom, fonction, e-mail vérifié)
                        [ Étape 2 ] Entreprise (nom, pays/devise/fuseau — allowlist,
                                    identifiant légal optionnel)
                        [ Étape 3 ] Établissement principal (nom, adresse, GPS opt.)
                        [ Étape 4 ] Modules pertinents (pré-cochés par marché+profil,
                                    allowlist serveur, dépendances vérifiées)
                        [ Étape 5 ] Données métier initiales (catalogue/services,
                                    fournisseurs ou clients — jeu de démo opt-in)
                        [ Étape 6 ] Équipe et permissions (invitations + rôles)
                        [ Étape 7 ] Découverte des applications (Mon Logiciel filtré)
                        [ Étape 8 ] Première opération guidée (une action réelle,
                                    réussie, journalisée)
```

**Invariants du parcours cible :**

1. **État serveur**, pas navigateur : chaque étape est persistée sur le tenant
   (`companies.metadata.onboarding`) ; la reprise repart de la dernière étape confirmée.
2. **Idempotence** : une clé d'idempotence cliente par soumission ; deux soumissions
   simultanées produisent un tenant, jamais deux (contrainte d'unicité + gestion de course
   `23505` sur le slug).
3. **Aucun doublon de tenant** : avant création, recherche par e-mail professionnel +
   domaine ; si un tenant existe déjà → proposition de rattachement, pas de création.
4. **Établissement principal obligatoire** : un tenant sans établissement ne peut pas
   produire de pointage, de vente, ni de stock.
5. **Sortie** : le parcours se termine par une opération métier **réussie**, pas par un
   écran de félicitations.

---

## 2. Parcours B — Restaurant (mono-établissement)

| Étape | État | Détail vérifié |
|---|---|---|
| 1. Inscription | ✅ | `/signup?sector=restaurant` → `packSolutionCode('restaurant')` |
| 2. Provisioning | ✅ | `restaurant` → cascade `restaurantmanager` (flag opérationnel) |
| 3. Configuration | ✅ | `SeedDefaultSteps` + étapes showcase ; `SectorTemplateService` |
| 4. Établissement | ✅ | `RestaurantBranch` (type d'établissement, horaires, zones, tables) |
| 5. Équipe | ✅ | `Employee` + `RestaurantBranchStaff` (affectation) |
| 6. Produits / stock | ✅ | `RestaurantProduct`, `RestaurantIngredient`, `InventoryMovement` |
| 7. Vente (POS) | ✅ | `restaurant/pos` → file hors-ligne (`dispatch()`) |
| 8. Cuisine (KDS) | ✅ | `restaurant/kitchen` → transitions idempotentes |
| 9. Kiosque | ✅ | `/kiosk` (RESTO-807) + `public/restaurant/kiosk/*` |
| 10. Applications | ✅ | Mon Logiciel filtre sur `restaurant` + `restaurant_kitchen` |
| 11. Rapports | ✅ | exports module + `Platform` reports |
| 12. Première opération E2E prouvée | ⛔ | aucun scénario E2E restaurant exécuté en CI (gap T-03) |

## 3. Parcours C — Groupe de restaurants (multi-établissements)

| Étape | État | Détail |
|---|---|---|
| 1. Création du tenant | ✅ | comme B |
| 2. Création de N branches | ✅ | `RestaurantBranch` + `RestaurantBranchRepository` |
| 3. Données séparées par branche | ⚠️ | les ventes/stock portent `branch_id`, mais **aucune contrainte d'unicité ni vue consolidée générique** ; le filtrage dépend de chaque écran (gap T-01) |
| 4. Staff multi-branches | ✅ | `RestaurantBranchStaff` (rotations #8450) |
| 5. Vue groupe consolidée | ⚠️ | `REST-OS-8` ouvert (issue #8610) — pas encore livré |
| 6. Rapports par branche et consolidés | ⚠️ | par branche oui ; consolidé non (gap T-01) |

## 4. Parcours D — Station-service

| Étape | État | Détail |
|---|---|---|
| 1. Inscription `?sector=fuel_station` | ✅ | → solution `fuel_station` |
| 2. Provisioning + flag `fuel` | ✅ | `FuelStationManifest` |
| 3. Sites (`FuelSite`) | ✅ | `FuelSiteController`, policy par site |
| 4. Produits / cuves / pistolet | ✅ | `FuelProduct`, `FuelMeterInterval` |
| 5. Ventes + moyens de paiement | ✅ | `payment_method` + CHECK (PR #8615, #8579/#8580) |
| 6. Clôture de caisse | ✅ | `FuelCashSession` + `Movement` |
| 7. Annulation compensatoire | ✅ | `FuelSaleService::cancel()` (aucun DELETE) |
| 8. App mobile pompiste | ⛔ | contrats backend livrés, UI Flutter non intégrée (issue #8585) |
| 9. Logo de station / vue comparative | ⛔ | issue #8583 ouverte |
| 10. Première opération E2E prouvée | ⚠️ | tests Feature Fuel : 256 tests / **13 échecs préexistants** dont 12 arbitrages produit |

## 5. Parcours E — Agence de voyage

✅ Inscription `?sector=travel` → `travelagency` · ✅ agences (`TravelOffice`) · ✅ trajets,
sièges, réservations, billets, QR + vérification · ✅ boutique publique · ✅ marketplace
inter-agences · ✅ comptes voyageurs (`travel_customer` guard). **⚠️** un seul manifest
`pilot` ; `front/travel-web` déployé séparément.

## 6. Parcours F — École / Santé / Pharmacie / Hôtellerie / Retail / VTC / Delivery / Cagnottes

| Verticale | Manifest | Modules requis | Établissement | E2E |
|---|---|---|---|---|
| EduManager | ✅ `pilot` | rh, attendance, … | ⛔ pas de modèle d'établissement scolaire générique | ⛔ |
| HealthManager | ✅ `pilot` | rh, … | ⛔ | ⛔ |
| Pharmacy | ✅ `pilot` | rh, catalog, … | ⛔ | ⛔ |
| HospitalityManager | ✅ `pilot` | rh, … | ✅ `HospitalityProperty` | ⛔ |
| Retail | ✅ `pilot` | rh, catalog, stock | ⛔ | ⛔ |
| Vtc | ✅ `pilot` | rh, fleet, geo | ⛔ | ⛔ |
| Delivery | ✅ `pilot` | rh, catalog | ⛔ | ⛔ |
| Fundraising | ✅ `pilot` | — (public web) | n/a | ⛔ |

**Constat :** l'absence d'un socle d'établissement **transversal** force chaque verticale à
réinventer son modèle de site, et empêche une vue groupe générique (gap T-01).

---

## 7. Parcours G — « Rejoindre une entreprise » (sans doublon)

```
Utilisateur possédant déjà un compte
  └─✅ /auth/login
       └─⚠️ aucune détection « cet e-mail a déjà une entreprise »
            → l'écran de création reste accessible, un second tenant peut être créé
              par inadvertance (gap O-08)
       └─✅ demande d'accès / QR entreprise (`/company/qr-onboarding/*`)
```

## 8. Parcours H — Centre « Mes logiciels »

```
/dashboard → /mon-logiciel
  ├─✅ visibleSoftware(access) : filtre par modules ACTIVÉS du tenant
  ├─✅ fiche par app : rôle, profils, fonctionnalités, prérequis,
  │     méthode de connexion, permissions, étapes de mise en service
  ├─✅ badge dérivé : published | pilot | not_distributed
  ├─⚠️ pas de version réelle affichée (gap A-01)
  ├─⚠️ pas d'état de configuration par tenant (app enrôlée ? liée ?) (gap A-02)
  └─⚠️ pas de lien direct vers l'écran d'enrôlement d'un appareil
```

## 9. Parcours I — Hors-ligne terrain

```
1. ✅ Enrôlement d'un appareil (/settings/devices) → jeton affiché UNE FOIS
2. ✅ Appairage Edge (edge_nodes) → /api/v1/edge/health = online
3. ✅ Coupure réseau → ConnectionIndicator = offline + opérations en attente
4. ⚠️ File visible : compteur oui, âge et dernier échec non (gap F-03)
5. ✅ Reconnexion → rejeu automatique (useOfflineSync.dispatch)
6. ⚠️ Idempotence du rejeu : clé stable POS/KDS, mais NON prouvée E2E (gap F-04)
7. ⛔ Conflit détecté → aucune UI d'arbitrage (gap F-05)
8. ✅ Révocation → DELETE /api/v1/edge/{nodeId}, scope tenant
9. ⛔ Diagnostic support : aucun écran unifié (gap F-07)
```

## 10. Parcours J — Superadmin

```
/admin (Vue) → login plateforme (PlatformAuthController, 2FA)
  ├─✅ Entreprises (liste, abonnement, solutions activées, santé)
  ├─✅ Utilisateurs plateforme, support/tickets, annonces
  ├─✅ Edge nodes (sync forcée, révocation) — permission edge.manage
  ├─✅ Files/queues, notifications, IA, webhooks
  ├─✅ Impersonation tracée
  ├─⚠️ Provisionnement : aucune vue des étapes/échecs (gap S-01)
  ├─⚠️ Catalogue verticales/apps : absent (views/solutions = stats survey) (gap S-03)
  ├─⚠️ Statut d'intégrations : pas de registre générique (gap S-05)
  └─⚠️ Branding global : pas d'écran dédié (gap B-04)
```

## 11. Parcours K — Erreur et reprise

| Situation | État | Détail |
|---|---|---|
| 404 route inconnue | ✅ | `not-found.tsx` + `views/NotFoundView.vue` |
| 403 permission refusée | ⚠️ | message d'erreur API, **aucune page 403** dédiée (gap B-02) |
| 500 erreur serveur | ⚠️ | `error.tsx` générique, pas de page 500 dédiée ni de correlation ID visible (gap B-03) |
| Session expirée | ✅ | `token.refresh` + redirection login |
| Onboarding interrompu | ⚠️ | reprise OK après login ; **aucune reprise avant OTP** (gap O-06) |
| Provisioning partiel | ⚠️ | la transaction protège le cas nominal ; pas de vue de reprise admin (gap S-01) |
