# 02 — TRD : Technical Requirements Document

**Base :** `main` @ `e9c5f986c` · **Branche :** `mission/leopardo-business-os`
**Convention :** chaque exigence porte un identifiant stable, réutilisé par la matrice des
écarts (`08_gap_matrix.md`), les issues GitHub et le plan de test (`15_test_plan.md`).
Mention **[existant]** = déjà satisfait par le code sur `main` (référence de fichier citée) ;
**[à faire]** = écart ouvert.

---

## 1. Exigences fonctionnelles — Onboarding universel

| ID | Exigence | État |
|---|---|---|
| **ONB-01** | La vitrine expose un point d'entrée « Trade » qui propose International (importateur / exportateur / les deux) vs Domestique (grossiste / détail) | [existant — `vitrine/lib/trade-intent.ts`, `TradeOnboardingChoice.tsx`] **mais `TRADE_MODES` ne distingue pas les sous-profils** → [à faire] |
| **ONB-02** | Le choix de marché et de sous-profil est **transporté jusqu'au provisioning** (pas seulement jusqu'à l'URL `/signup`) | [à faire — `tradeIntentKey` n'est pas consommé côté serveur ; `/signup` ignore `?trade=`) |
| **ONB-03** | Le visiteur peut créer un espace **ou** rejoindre un espace existant sans créer de doublon | [partiel — `tradeJoinHref` → `/auth/login` ; aucune détection « appartient déjà à un tenant » |
| **ONB-04** | Le parcours collecte : profil professionnel, informations entreprise, pays/devise/fuseau (allowlist), établissement principal, modules pertinents, données métier initiales, équipe et permissions | [partiel — entreprise/pays/modules oui ; **profil professionnel et établissement principal absents**] |
| **ONB-05** | Le provisionnement est **idempotent** : deux soumissions concurrentes ou un rejeu de job ne créent jamais deux tenants | [existant — `ProvisionGuidedTrial` #3600, `DB::transaction`, slug unique ; **non couvert par un test de course**] → [à faire] test |
| **ONB-06** | Un onboarding interrompu est **reprenable** avec le même état serveur (aucun stockage navigateur comme source de vérité) | [partiel — `setup_interview` et `welcome_seen_at` en `companies.metadata` ; le parcours pré-login n'a aucun état serveur] |
| **ONB-07** | Une première opération métier guidée est proposée et **réalisable** à la fin du parcours | [partiel — checklist `onboarding_steps` par verticale ; pas de « première vente/service » générique] |
| **ONB-08** | Le parcours est disponible en fr / en / ar / tr sans chaîne codée en dur | [existant — catalogues `shared/i18n`, garde `content-naming-guard`/`i18n-enterprise`] |

---

## 2. Exigences fonctionnelles — Tenants réellement opérationnels

| ID | Exigence | État |
|---|---|---|
| **TEN-01** | Configuration initiale par profil (restaurant, groupe de restaurants, station-service, école, agence de voyage, retail, santé, pharmacie, hôtellerie, VTC, delivery, cagnottes) | [existant — 12 manifests + `SectorTemplateService`] |
| **TEN-02** | Établissements multiples avec **données réellement séparées** par établissement | [partiel — `sites` générique sans scope ; `RestaurantBranch`/`FuelSite`/`TravelOffice` portent leur propre modèle ; **aucun filtre par établissement transversal**] |
| **TEN-03** | Employés créables, invitables, affectables à un établissement | [existant — `Employee`, `UserInvitationService`, `EmployeeResourceAssignment`, `RestaurantBranchStaff`] |
| **TEN-04** | Rôles et permissions effectivement appliqués sur les routes de chaque module | [partiel — `api.module.grant:<module>` + policies ; enforcement à auditer verticale par verticale (§ gap T-02)] |
| **TEN-05** | Produits/services et stocks utilisables dès l'onboarding | [existant par verticale — `Catalog*`, `RestaurantProduct`, `FuelProduct` ; pas de catalogue générique] |
| **TEN-06** | Tâches opérationnelles (assignation, échéance, statut) | [existant — `Planning`] |
| **TEN-07** | Terminaux (kiosque, POS, imprimante) enrôlables et révocables | [partiel — `edge_nodes` + `/settings/devices` ; see OL-04] |
| **TEN-08** | Rapports et exports disponibles par tenant | [existant — `Platform` reports + `Infrastructure/Exports` par module] |
| **TEN-09** | Notifications configurables par utilisateur et par canal | [existant — `NotificationPreferenceController`, `Notification` module] |
| **TEN-10** | Documents (génération, stockage, partage) | [existant — module `Documents`/`Showcase`, `EmployeeDocument`] |
| **TEN-11** | Administration du tenant (paramètres, branding, facturation, support) self-service | [partiel — branding/banking/support oui ; **aucun écran d'administration unifié du tenant**] |
| **TEN-12** | **Première opération métier réussie** vérifiée de bout en bout par verticale | [à faire — aucun scénario E2E par verticale aujourd'hui] |

---

## 3. Exigences fonctionnelles — Centre « Mes logiciels »

| ID | Exigence | État |
|---|---|---|
| **APP-01** | Chaque application expose rôle, profils, plateformes, prérequis, statut réel, version, mode de connexion, permissions, état de configuration | [existant — `software-catalog.ts` (7 sections) + page `/mon-logiciel` #8597] |
| **APP-02** | Le statut est **dérivé** des canaux réels (`published`/`pilot`/`not_distributed`) | [existant — `softwareAvailability()`] |
| **APP-03** | La visibilité suit strictement les modules activés du tenant | [existant — `visibleSoftware(access)`] |
| **APP-04** | Aucun lien de téléchargement fictif ; bouton désactivé si aucun canal | [existant — cibles `soon` sans `href`] |
| **APP-05** | La **version réelle** distribuée est visible | [à faire — aucune version n'est exposée dans la fiche] |
| **APP-06** | L'**état de configuration** par tenant (app enrôlée ? configurée ?) est affiché | [à faire — la fiche décrit l'app, pas son état chez ce tenant] |
| **APP-07** | Les apps sans canal iOS ne prétendent pas être disponibles sur iOS | [existant — `nativeSoon()`] |

---

## 4. Exigences fonctionnelles — Hors-ligne et synchronisation

| ID | Exigence | État |
|---|---|---|
| **OFF-01** | Chaque application déclare explicitement son mode hors-ligne (`verified` / `implemented` / `absent`) | [à faire — `offline: boolean` déclaratif dans le catalogue, sans preuve] |
| **OFF-02** | L'état de connexion est visible en permanence dans les UIs terrain | [existant — `ConnectionIndicator`, `edge-health`] |
| **OFF-03** | La file d'attente est visible (nombre, âge, dernier échec) et permet un rejeu manuel | [partiel — compteur `pending_sync` + bouton « Réessayer » ; pas d'âge ni de diagnostic d'échec] |
| **OFF-04** | Le rejeu est **idempotent** (aucun doublon de vente/commande) | [partiel — clé stable POS/KDS ; **non prouvé par test de bout en bout**] → [à faire] |
| **OFF-05** | Les conflits sont détectés, tracés et arbitrables (local / cloud / manuel) | [partiel — modèle `SyncQueue.conflict_resolution` + `SyncLog` ; **aucune UI d'arbitrage**] |
| **OFF-06** | La révocation d'un appareil coupe l'accès de façon effective | [existant — `DELETE /api/v1/edge/{nodeId}` scoped tenant ; test d'isolation suivi par #8496] |
| **OFF-07** | Un diagnostic sync/installation est consultable par le support | [à faire — voir SUP-04] |
| **OFF-08** | Aucune revendication hors-ligne sans test réel reproductible | [règle de gouvernance — § `15_test_plan.md` OL-1..OL-5] |

---

## 5. Exigences fonctionnelles — Superadmin

| ID | Exigence | État |
|---|---|---|
| **SUP-01** | Liste des tenants avec solutions activées et état d'abonnement | [existant — `views/companies`, `PlatformCompanyFeatureController`] |
| **SUP-02** | Vue du **provisionnement** : étapes franchies, échecs, tenants bloqués | [à faire] |
| **SUP-03** | Catalogue verticales/applications avec maturité et canaux réels | [partiel — `views/solutions` = statistiques de survey uniquement] |
| **SUP-04** | Diagnostic sync / installation par tenant et par nœud | [partiel — `views/edge` ; pas de vue par tenant] |
| **SUP-05** | Statut des intégrations (opérationnel vs non configuré), dérivé de la présence de clé | [partiel — pattern `TradeIntegrationRegistry` sur branche Trade ; **absent sur `main`**] |
| **SUP-06** | Erreurs techniques consultables sans exposer de données privées client | [partiel — `QueueObservability`, `PlatformNotificationObservability`] |
| **SUP-07** | Branding global de la plateforme | [partiel — tokens globaux ; pas d'écran dédié] |
| **SUP-08** | Toute action superadmin est permissionnée **et** journalisée | [existant — `platform.permission:*` + `audit_logs` ; à vérifier exhaustivement (§ gap S-02)] |
| **SUP-09** | Aucun accès abusif aux données privées : impersonation tracée et bornée | [existant — `PlatformImpersonationSession`] |

---

## 6. Exigences fonctionnelles — Branding et erreurs

| ID | Exigence | État |
|---|---|---|
| **BRD-01** | Logo, variantes, favicon, icônes et métadonnées cohérents sur toutes les surfaces | [existant — `assets/branding`, `public/brand`, `manifest`] |
| **BRD-02** | Écrans de connexion, d'onboarding et de chargement portent l'identité | [partiel] |
| **BRD-03** | Pages 404 / 403 / 500 claires, sans fuite technique | [partiel — `not-found.tsx`, `error.tsx` ; **pas de 403 ni de 500 dédiée**] |
| **BRD-04** | Personnalisation par tenant (logo, couleurs) contrôlée et bornée | [partiel — `CompanyBrandingController`, upload sans validation de format exhaustif] |
| **BRD-05** | Animations légères respectant `prefers-reduced-motion` | [existant — `GeoBackdrop`, `HeroSection` neutralisent sous reduced-motion] |

---

## 7. Exigences non fonctionnelles

| ID | Exigence | Cible mesurable |
|---|---|---|
| **NFR-01** | Latence API p95 sur les routes de lecture tenant | ≤ 400 ms (mesure CI k6, `k6-load-smoke.yml` existant) |
| **NFR-02** | Provisionnement d'un tenant (hors OTP) | ≤ 15 s p95 ; transaction unique, aucun état partiel |
| **NFR-03** | Isolation inter-tenant | 0 fuite : tout accès cross-tenant → 404, prouvé par test |
| **NFR-04** | Idempotence | tout endpoint de création rejouable accepte une clé d'idempotence |
| **NFR-05** | Accessibilité | WCAG 2.1 AA sur les parcours d'onboarding et Mon Logiciel |
| **NFR-06** | Internationalisation | 4 locales complètes, parité vérifiée en CI |
| **NFR-07** | Sécurité | aucun secret en clair ; audit Composer/npm ; CodeQL |
| **NFR-08** | Observabilité | correlation ID, outbox, logs structurés, pas de PII |
| **NFR-09** | Compatibilité | navigateurs evergreen + Android 8+ (Flutter) |
| **NFR-10** | Réversibilité | toute migration a un `down()` non destructif (garde existante) |

---

## 8. Contraintes de livraison

- **C1** — Une seule branche de mission ; pas une branche par issue.
- **C2** — `main` protégé : 4 checks requis (`PHPStan Strict`, `Module Structure Validator`,
  `Frontend ESLint+TS`, `actionlint`), `enforce_admins = true`.
- **C3** — Aucun test supprimé pour verdir la CI ; aucun contournement de protection.
- **C4** — Entrée `CHANGELOG` obligatoire dès qu'un périmètre critique change
  (`check-governance.ps1`, motif `^(api/|docs/GESTION_PROJET/|.github/|…)`).
- **C5** — Aucune dépense externe, aucune campagne, aucune publication sans autorisation.
- **C6** — Aucune intégration déclarée opérationnelle sans clé/config réelle.

---

## 9. Traçabilité exigence → composant

| Exigence | Composant(s) concerné(s) |
|---|---|
| ONB-01..03 | `vitrine/lib/trade-intent.ts`, `TradeOnboardingChoice.tsx`, `/signup`, `SelfServiceTrialController` |
| ONB-04..07 | `CompanyProvisioningService`, `ProvisionGuidedTrial`, `SeedDefaultSteps`, `SetupInterviewPlanner` |
| TEN-02 | `Core/Tenant/Models/Site`, `RestaurantBranch`, `FuelSite`, `TravelOffice` |
| APP-05..06 | `lib/software-catalog.ts`, `mon-logiciel/page.tsx` |
| OFF-01..05 | `web-offline/lib/*`, `useOfflineSync`, `ConnectionIndicator`, `EdgeSync/SyncQueue` |
| SUP-02..05 | `Modules/Platform/*`, `admin-dashboard/src/views/*` |
| BRD-03 | `front/web/src/app/{error,not-found}.tsx` |
