# Farm OS — TAD (Technical Architecture Document)

**Version :** 2.0 — 2026-10-18
**Branche :** `bc/bc-37-farm-os` · **PR :** #8572
**Principe directeur :** *ne creer aucun systeme transversal qui existe deja.* Farm OS est une **solution verticale** branchee sur les socles Leopardo, pas une application parallele.

> Ce document decrit l'architecture **reellement livree**, pas une cible theorique. Chaque affirmation est adossee a un chemin de code.

---

## 1. Positionnement architectural

```text
Leopardo Business OS
├── Platform Core
│   ├── Identity / Tenant / RBAC ....... REUTILISE (aucun nouveau systeme)
│   ├── Feature Registry ............... REUTILISE (ModuleRegistry + feature-flags)
│   ├── Solutions (catalogue/activateur) REUTILISE + 1 manifest
│   ├── Onboarding (entretien→plan) .... REUTILISE + allowlists etendues
│   └── AI orchestrator ................ REUTILISE (contexte prepare, pas de nouveau provider)
├── Shared Business Modules
│   ├── HR (employes, contrats) ........ REUTILISE
│   ├── Notification ................... REUTILISE (rappels agricoles)
│   ├── Cabinet (documents) ............ REUTILISE (file_id)
│   ├── Billing + Accounting + Expense . REUTILISE (reference, jamais de duplication)
│   └── EdgeSync (moteur de sync) ...... REUTILISE
└── Operational Solutions
    └── ★ Farm OS (BC-37)  ← NOUVEAU
        ├── activites & capacites agricoles
        ├── exploitations (sites) & parcelles
        ├── cycles culturaux & interventions
        ├── intrants / stock agricole
        ├── materiel agricole
        ├── elevage
        ├── recoltes / entreposage / tracabilite
        ├── depenses & rentabilite
        ├── hors ligne (file idempotente)
        ├── assistant agricole
        └── tableau de bord exploitation
```

## 2. Structure du module (imposee par `ARCHITECTURE.md`)

```text
api/app/Modules/FarmOs/
├── Application/
│   └── Actions/                 # 1 cas d'usage = 1 classe, methode execute()
│       ├── AssignFarmOperation.php
│       ├── CreateParcel.php
│       ├── ProvisionFarmOs.php
│       ├── ScheduleFarmOperation.php
│       ├── SyncFarmActivities.php
│       ├── TransitionFarmOperation.php
│       └── UpdateFarmCapabilities.php
├── Domain/
│   ├── Enums/                   # 30 registres FERMES (extensions additives)
│   ├── Exceptions/              # codes d'erreur STABLES (ASCII)
│   ├── Manifests/FarmOsManifest.php
│   ├── Models/                  # Eloquent tenant-scoped (BelongsToCompany)
│   └── Services/                # logique metier transactionnelle
├── Infrastructure/
│   └── Context/FarmCompanyContext.php
├── Interfaces/
│   └── Api/V1/
│       ├── Controllers/         # 1 controleur par domaine fonctionnel
│       └── Requests/            # validation de forme
└── Providers/FarmOsServiceProvider.php
```

Enregistrement de la route : `api/routes/modules/farm_os.php`, charge comme les autres verticales, avec `middleware('module.farm_os')`.

## 3. Les points d'enregistrement (procedure obligatoire)

| # | Fichier | Action | Garde |
|---|---|---|---|
| 1 | `Core/Solutions/Enums/SolutionIndustry.php` | ajouter `Agriculture = 'agriculture'` **en fin de liste** | registre ferme : additive seulement |
| 2 | `Modules/FarmOs/Domain/Manifests/FarmOsManifest.php` | creer (`CODE = 'farm_os'`) | — |
| 3 | provider Farm OS | `$catalogue->register('farm_os', …)` | pattern Fundraising/Vtc |
| 4 | `Core/Feature/Domain/ModuleRegistry.php` | entree `farm_os` (kind `solution`, default `false`) | parite flags ↔ KNOWN_MODULES |
| 5 | `config/feature-flags.php` | projection `farm_os` | fail-closed |
| 6 | `Company::KNOWN_MODULES` (derive) | verifie par la garde de parite | admin plateforme peut activer |
| 7 | `dev-hub/governance/bounded-context-registry.json` | **BC-37 AGRICULTURE** | garde de registre BC |
| 8 | `CODEOWNERS` | BC-37 | gardes gouvernance |
| 9 | docs architecture | compteur modules | `check-architecture-docs-parity.sh` |
| 10 | fixture `CreatesMvpSchema` | 31 tables Farm OS | suite backend |
| 11 | couverture OpenAPI | 73 chemins `/farm/*` | `check-openapi-route-coverage.py` |
| 12 | front : prefixes proteges + i18n `fr/en/ar/tr` + catalogue logiciel | cle `farm` | tests Jest + parite i18n |

## 4. Decision d'activation et de modularite — **ADR-FARM-0001**

**Contexte.** La mission exige qu'un agriculteur « n'active que les outils dont il a besoin » (13 outils), tout en interdisant de « creer un nouveau systeme si un service transversal existe ».

**Options.**
- **A (rejetee)** — 13 feature flags, un par outil : 13 × plusieurs points d'enregistrement, contrat `/auth/me` gonfle, risque de desynchronisation eleve.
- **B (retenue)** — **un seul flag de solution `farm_os`** ; la modularite interne est portee par un **registre ferme de capacites declare en code** (`FarmCapability`, 13 cases), et l'activation par tenant est stockee dans `farm_capabilities`. Le gating est **serveur** (`FarmCapabilityGate`), la modularite visible cote client est projetee dans la reponse `farm/settings`.
- **C** — hybride : flag `farm_os` + flags pour les seuls sous-domaines lourds si un pilote le justifie.

**Decision :** option **B**, avec clause de revue : si un sous-domaine devient facturable/distribuable separement, il passera par un **second manifest** — jamais par des flags ad hoc.

**Consequences.** + Le front filtre par capacite renvoyee par l'API, pas par flag. − Un outil desactive n'apparait pas dans `/auth/me` : le front doit lire `farm/settings`. Risque assume et teste.

## 5. Decision de domaine — **ADR-FARM-0002 : stock**

**Contexte.** Aucun service de stock transversal n'existe : le stock est implemente par verticale. La mission interdit « une nouvelle abstraction centrale si les mecanismes existants suffisent » **et** interdit « un systeme financier parallele ».

**Decision.** Farm OS possede son **propre registre de stock agricole**, minimal et tenant-scoped, **et n'introduit aucune abstraction dans `Core/`**. La coherence recolte ↔ stock ↔ vente est garantie par une **regle de comptage unique** : une quantite recoltee cree **un** lot de stock (et un seul) ; toute sortie est un mouvement type referencant ce lot. **Aucune** ecriture comptable n'est produite par Farm OS en direct.

**Revertibilite.** Si un service de stock partage est extrait plus tard, Farm OS devient un **client** de ce service : les tables `farm_*` sont projetables.

## 6. Decision d'honnetete — **ADR-FARM-0003 : superficie, GPS, telemetrie, agronomie**

**Decision.** Aucune donnee n'est presentee comme mesuree si elle est declaree :

| Donnee | Regle |
|---|---|
| Superficie de parcelle | `area_value` + `area_unit` + **`area_source` ∈ {declared, imported, measured}** — l'UI affiche la provenance |
| Geometrie | nullable ; si absente, **aucune** vue carte ni calcul de surface |
| Heures moteur / kilometrage materiel | saisie **manuelle** ; `meter_source` ∈ {manual, imported} — **pas de case `sensor`** |
| Position GPS d'engin | **non revendiquee** |
| Interventions | type, date, quantite issues d'une **declaration utilisateur** |
| Conseil agronomique | le calendrier est un **modele de planification** configurable, jamais une prescription |
| Meteo / prix de marche | **aucune donnee simulee** ; soit source identifiee + fraicheur, soit etat « non disponible » |
| Veterinaire | aucune fonction de diagnostic automatique |
| Cout inconnu | `NULL`, **jamais zero** |
| Seuil d'alerte non declare | n'apparait **jamais** dans les alertes |
| Duree de gestation non saisie | ne produit **aucune** date de mise bas |
| Rentabilite sur donnees incompletes | `is_reliable: false` + `missing_inputs` explicite |

## 7. Decision d'integration — **ADR-FARM-0004 : partir de l'interne**

**Decision.** Toutes les tranches = **saisie manuelle + donnees internes**, sans aucune dependance externe payante. Les interfaces d'integration (meteo, imagerie, marche) sont **specifiees comme ports** avec un adaptateur « non configure » honnete — **aucun appel reseau, aucun abonnement, aucune depense** sans autorisation explicite.

## 8. Invariants d'architecture (les plus importants)

### 8.1 Journal immuable + cache derive

Pour le stock d'intrants, le stock de recolte et l'effectif du cheptel, la quantite courante est un **cache derive d'un journal immuable** :

```
quantite_courante  <-  dernier `quantity_after` / `head_count_after` du journal
```

Aucun appelant ne modifie directement la quantite courante. Consequence : le rapprochement est **verifiable** (`sorties + restant == recolte`) et une meme quantite ne peut pas etre comptabilisee deux fois.

### 8.2 Le sens est porte par le TYPE, jamais par le signe

Toutes les quantites de mouvement sont **positives**. Le sens vient de `movement_type` / `event_type`. Cela rend impossible de « sortir en negatif » pour contourner une garde de stock.

### 8.3 Idempotence par identifiant client

Chaque ecriture exposable hors ligne accepte un identifiant genere par l'appareil (`client_movement_id`, `client_event_id`, `client_operation_id`, `client_usage_id`, `client_maintenance_id`, `client_breakdown_id`, `client_harvest_id`, `client_expense_id`, `client_sale_link_id`, `client_equipment_id`, `client_batch_id`, `client_animal_id`, `client_location_id`, `client_campaign_id`), **unique par entreprise**. Le rejeu renvoie l'enregistrement existant sans rien re-appliquer.

### 8.4 Fail-closed

`FarmCapabilityGate` refuse par defaut : un outil non active est refuse cote serveur, et le catalogue applicatif ne peut pas afficher un outil que la porte refuserait.

### 8.5 Purete des couches

- **Aucune facade Laravel** (`DB`, `Schema`) dans `Domain/` et `Application/`. La connexion vient de `Model::query()->getConnection()`, le nom de table de `(new Model)->getTable()`, et les ecritures sont enveloppees dans `$connection->transaction(...)`.
- `__()` **est autorise** dans `Application/` : la regle de purete bannit les facades, pas l'helper de traduction.
- Aucun import croise entre modules metier (verifie par le garde de dependances bornees).

## 9. Multi-tenant, securite, performance

- **Isolation** : toutes les tables Farm OS portent `company_id` **NOT NULL** + trait `BelongsToCompany` (scope global).
- **Autorisation** : middleware `module.farm_os` (module actif) **puis** `FarmCapabilityGate` (capacite active) **puis** verification d'appartenance metier.
- **Jamais** de `company_id` accepte du client comme autorite.
- **Idempotence terrain** : toute ecriture acceptant un rejeu porte un identifiant client avec `UNIQUE(company_id, client_*)`.
- **Performance** : index `(company_id, …)` sur chaque table ; pagination obligatoire ; pas de N+1 (`with()` explicite).
- **Migrations** : idempotentes (`schemaTableExists`), `down()` non destructeur.

## 10. Mode hors-ligne (conception des l'origine)

| Capacite hors ligne | Mecanisme |
|---|---|
| Consulter les taches/interventions deja synchronisees | cache local |
| Consulter parcelles et donnees autorisees | snapshot tenant-scoped, jamais cross-tenant |
| Saisir une observation / une operation realisee | file + identifiant client |
| Photos / pieces jointes | file d'upload differe, via `file_id` |
| Mouvements de stock | file **strictement idempotente** (conflits → revue) |
| Enregistrements financiers | **hors ligne interdit** en ecriture (coherence) — lecture seule |

**Ne jamais promettre** : la synchronisation n'est annoncee que pour les operations du registre ferme `FarmOfflineOperationType` (8 types), avec etat de file visible et bouton de rejeu.

## 11. Ce qui n'est PAS fait (limites explicites)

- Pas de nouveau fournisseur IA, pas de moteur SQL libre, pas d'agent autonome.
- Pas de telemetrie, pas de capteurs, pas d'OCR, pas d'imagerie satellite.
- Pas d'application mobile Farm OS dediee.
- Pas de POS ni de materiel de vente au comptoir.
- Pas de fusion avec `RestaurantManager`, `Retail` ou `Pharmacy` : Farm OS ne reutilise pas leurs tables metier, seulement leurs **patterns**.
