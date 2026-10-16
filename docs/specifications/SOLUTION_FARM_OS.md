# Farm OS — Spécification de la verticale agricole (BC-37 AGRICULTURE)

**Statut :** Tranche 0/1 implémentée (socle + parcellaire + interventions) — extensions planifiées
**Bounded context :** BC-37 FARM OS
**Code solution :** `farm_os` (feature flag `companies.features.farm_os`, gate `module.farm_os`)
**Industrie :** `SolutionIndustry::Agriculture`
**Documents de conception associés :** audit FAQarm OS (Phase 1), TAD, TRD, AppFlow/UIX/UI Design, Backend Schema, Implementation Plan (livrés avec la mission ; ce document est la référence versionnée côté dépôt).

---

## 1. Objet

Farm OS permet à un agriculteur, un éleveur ou une entreprise agricole de Leopardo Business OS de gérer
réellement son exploitation depuis une plateforme intégrée : parcelles et cultures, calendrier des
travaux, travailleurs et équipes, intrants et stock, matériel, cheptel, récoltes et traçabilité,
achats et ventes, coûts et rentabilité, tableau de bord et assistant.

Farm OS n'est **pas** une application parallèle : c'est une **verticale** branchée sur les socles
existants (catalogue de solutions, activation idempotente, RBAC, tâches, notifications, fichiers,
facturation, comptabilité, géospatial, hors-ligne).

## 2. Principes non négociables

1. **Réutilisation avant création.** Aucun nouveau système de tenants, rôles, permissions ni
   provisioning. Farmer utilise `TenantManager`, `EmployeeModuleGrant`, `SolutionActivator`,
   `ModuleRegistry`, `Planning`, `Notification`, `Cabinet`, `Billing`, `Accounting`, `Expense`,
   `Fleet`, `Geo`, l'orchestrateur `AI` et la file hors-ligne `front/web/src/lib/offline`.
2. **Modularité par capacités, pas par flags** (ADR-FARM-0001). Un seul flag de solution ; les
   13 outils sont des *capacités* persistées dans `farm_capabilities` et gardées côté serveur par
   `FarmCapabilityGate`. L'utilisateur accepte, refuse ou modifie — un refus est respecté.
3. **Honnêteté des données** (ADR-FARM-0003). Aucune superficie présentée comme mesurée si elle est
   déclarée (`area_source`), aucune heure moteur ou position GPS inventée, aucun conseil
   agronomique ou vétérinaire présenté comme certain, aucune donnée externe sans source et
   fraîcheur, aucun canal de téléchargement affiché s'il n'existe pas.
4. **Aucune dépense externe.** Pas d'API payante, d'abonnement ni d'infrastructure supplémentaire
   sans autorisation explicite.
5. **Cohérence des quantités.** Une récolte crée **un** lot de stock (contrainte unique) ; toute
   sortie est un mouvement typé. Une quantité n'est jamais comptabilisée deux fois.
6. **Idempotence terrain.** Toute écriture acceptant un rejeu (mobile, hors ligne) porte un
   `client_operation_id` unique par tenant.

## 3. Périmètre implémenté (Tranche 0/1)

### 3.1 Fondations et intégration

| Élément | Emplacement |
|---|---|
| Industrie `agriculture` (registre fermé, extension additive) | `api/app/Core/Solutions/Enums/SolutionIndustry.php` |
| Manifest `farm_os` | `api/app/Modules/FarmOs/Domain/Manifests/FarmOsManifest.php` |
| Enregistrement au catalogue | `api/app/Modules/FarmOs/Providers/FarmOsServiceProvider.php` |
| Entrée registre unifié + projection flags | `api/app/Core/Feature/Domain/ModuleRegistry.php`, `api/config/feature-flags.php` |
| Gate module | alias `module.farm_os` — `api/bootstrap/app.php` + `EnsureFarmOsModuleMiddleware` |
| Routes | `api/routes/modules/farm_os.php` (require depuis `api/routes/api.php`) |
| Bounded context | `dev-hub/governance/bounded-context-registry.json` (BC-37) |
| Tables | `api/database/migrations/tenant/2026_10_16_000100_8379_create_farm_os_tables.php` |

Modules : requis `rh` ; optionnels `planning`, `notifications`, `geo`, `fleet`, `billing`,
`accounting`, `expense`, `crm`, `catalog`, `ai`.

### 3.2 Registres fermés (enums)

`FarmActivity` (16 activités : céréales, maraîchage, arboriculture, viticulture, serre, bovin,
ovin/caprin, aviculture, porcin, laitier, apiculture, aquaculture, mixte, coopérative, services,
agroalimentaire) · `FarmCapability` (13 outils) · `FarmOperationType` (10 types d'intervention) ·
`FarmOperationStatus` (machine à états fermée) · `AreaUnit` · `AreaSource`.

### 3.3 API v1 (`/api/v1/farm/*`, gate `module.farm_os`)

```text
GET    /farm/settings                  PATCH/PUT /farm/activities
GET    /farm/capabilities/available    PUT       /farm/capabilities
GET    /farm/dashboard
GET    /farm/sites                     POST      /farm/sites          GET /farm/sites/{site}
GET    /farm/parcels                   POST      /farm/sites/{site}/parcels
GET    /farm/parcels/{parcel}          GET       /farm/parcels/{parcel}/history
GET    /farm/crop-cycles               POST      /farm/parcels/{parcel}/crop-cycles
POST   /farm/crop-cycles/{cycle}/close
GET    /farm/operations                GET       /farm/operations/today | /late
POST   /farm/sites/{site}/operations   GET       /farm/operations/{operation}
POST   /farm/operations/{operation}/{start|complete|skip|cancel|assign|progress|report-problem}
```

### 3.4 Permissions déclarées par le manifest

`farm.admin` · `farm.manager` · `farm.worker` · `farm.stock` · `farm.equipment` ·
`farm.livestock` · `farm.sales` · `farm.accounting` · `farm.regulated_inputs`.
Installées par `SolutionPermissionInstaller` (socle `employee_module_grants`), révoquées à la
désactivation sauf partage avec une autre solution active.

### 3.5 Intégrité et honnêteté appliquées

- `FarmAreaGuard` : valeur + unité ensemble ; `measured` refusé sans géométrie ; défaut `declared`.
- `FarmOperationStatus` : transitions contrôlées ; `done` **immuable** ; motif obligatoire pour
  annuler une opération démarrée.
- `ScheduleFarmOperation` : rejeu idempotent via `client_operation_id` (unique par tenant).
- `SyncFarmActivities` : ajouter une activité **ne supprime rien** (clôture datée `ended_at`).
- Tableau de bord : sections filtrées par capacités réelles ; `pending_capabilities` déclare
  explicitement ce qui n'est pas encore livré plutôt que de l'afficher à zéro.
- Géométrie nullable : `geometry_available` pilote l'affichage de la carte côté front.

## 4. Tests livrés

`tests/Unit/Modules/FarmOs/` : `FarmAreaGuardTest` (provenance et cohérence des superficies),
`FarmCapabilityRecommenderTest` (profil végétal sans cheptel, éleveur sans irrigation, socle
toujours proposé, valeurs inconnues ignorées), `FarmOperationStatusTest` (machine à états,
immuabilité), `FarmOsManifestTest` (identité, dépendances, permissions, registres).

## 5. Extensions planifiées (non livrées — jamais annoncées comme faites)

Tranche 2 : intrants/stock/matériel/récoltes · Tranche 3 : élevage (cheptel, reproduction,
santé animale, productions) · Tranche 4 : achats/ventes/coûts/rapports/hors-ligne · Tranche 5 :
assistant agricole et ports d'intégration externes inactifs par défaut.

## 6. Issues GitHub et traçabilité de la mission

Les issues Farm OS ont été créées **avant** l'ouverture de la PR de cette tranche. Point de collaboration
important : deux créations successives ont eu lieu le même jour (2026-10-16, ~05:09 et ~05:10 UTC),
produisant **11 groupes thématiques dupliqués ×2** (#8549→#8560 puis #8551→#8570). Aucune n'a été
supprimée ni réécrite par cette PR : supprimer une issue référencée peut casser des liens depuis
d'autres branches, c'est une décision de mainteneur.

**Référence canonique (la plus complète et labellisée) :** #8560, #8561, #8562, #8563, #8564,
#8565, #8566, #8567, #8568, #8569, #8570.
**Doublons précoces à fermer en tant que `duplicate`** (décision mainteneur) : #8549→#8560,
#8550→#8561, #8551→#8562, #8552→#8563, #8553→#8564, #8554→#8565, #8555→#8566, #8556→#8567,
#8557→#8568, #8558→#8569, #8559→#8570.

### Correspondance groupes thématiques ↔ code livré

| Issue canonique | Groupe | État du code |
|---|---|---|
| #8560 | T0.1 Fondations / catalogue / gate | **Livré** (manifest, provider, ModuleRegistry, feature-flags, alias, routes, BC-37, parité docs) |
| #8561 | T0.2 Onboarding agricole | Partiel : persistance des activités + recommandation livrées ; étapes 2/3/4 côté front à faire |
| #8562 | T1.1 Exploitations et parcelles | **Livré** (sites, parcelles, historique, garde de superficie) |
| #8563 | T1.2 Interventions et calendrier | **Livré** (10 types, machine à états, idempotence terrain) |
| #8564 | T1.3 Travailleurs et équipes | Partiel : équipes + affectation livrées ; matrice de rôles/invitations à finaliser |
| #8565 | T2.1 Intrants et stocks | Non commencé (conception seulement) |
| #8566 | T2.2 Matériel agricole | Non commencé (conception seulement) |
| #8567 | T3.1 Élevage | Non commencé (conception seulement) |
| #8568 | T3.2 Récoltes et traçabilité | Non commencé (conception seulement) |
| #8569 | T4.1 Ventes, achats, coûts | Non commencé (conception seulement) |
| #8570 | T5.1 Mobile, hors-ligne, assistant | Non commencé (conception seulement) |

Un fichier de test `tests/Unit/Modules/FarmOs/**` correspondant à **T0.1/T1.1/T1.2** est livré par cette tranche.

> L'issue de diagnostic #8571 créée accidentellement pendant la vérification d'accès (contrôle d'écriture
> de l'API) a été **fermée** avec explication — jamais supprimée. La référence `#8380..#8389` du message
> de commit de cette tranche est erronée : elle visait des numéros estimés ; la traçabilité réelle est
> le tableau ci-dessus. L'historique n'a pas été réécrit (pas de `push --force`, interdit par la mission).

## 7. Hors périmètre assumé

Télémétrie et capteurs · OCR · imagerie satellite · météo et prix de marché temps réel · POS ·
paie agricole par pays · application mobile dédiée avant audit Phase 4.
