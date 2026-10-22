# Farm OS — TRD (Technical & Functional Requirements Document)

**Version :** 2.0 — 2026-10-18 · **Branche :** `bc/bc-37-farm-os` · **PR :** #8572
**Perimetre :** exigences fonctionnelles et techniques de la verticale Farm OS (agriculture vegetale, elevage, exploitations mixtes).

> Chaque exigence porte un identifiant stable, un statut honnete (**LIVRE** / **PARTIEL** / **NON LIVRE**) et son critere de verification. Aucune exigence n'est declaree LIVRE sans code correspondant.

---

## 1. Exigences fonctionnelles

### 1.1 Onboarding et provisionnement

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| FR-ONB-01 | Identifier l'activite (selection multiple, registre ferme) | LIVRE | `FarmActivity` (16 cases) |
| FR-ONB-02 | Configurer l'exploitation (nom, pays, langues, devise, unites) | LIVRE | `FarmSettings` |
| FR-ONB-03 | Recommander les outils selon l'activite | LIVRE | `FarmCapability` + `SyncFarmActivities` |
| FR-ONB-04 | Provisionner l'espace de facon **idempotente** | LIVRE | `ProvisionFarmOs` (rejeu => `already_active`) |
| FR-ONB-05 | Provisionner de facon **recuperable** | LIVRE | transaction + rejeu sur echec |
| FR-ONB-06 | Un outil refuse reste refuse au rejeu | LIVRE | test `ProvisionFarmOs` |
| FR-ONB-07 | Liste de controle des etapes restantes, relue en base | LIVRE | `checklist` derive de donnees reelles |
| FR-ONB-08 | Activer une activite supplementaire sans recreer l'entreprise | LIVRE | `SyncFarmActivities` (additif) |

### 1.2 Exploitations et parcelles

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| FR-PAR-01 | Nom, reference, superficie, unite | LIVRE | `farm_parcels` |
| FR-PAR-02 | Localisation / geometrie si disponible | LIVRE | `geometry_wkt` (optionnel) |
| FR-PAR-03 | Type de culture et historique des cultures | LIVRE | `farm_crop_cycles` |
| FR-PAR-04 | Dates de semis, plantation, recolte | LIVRE | `farm_crop_cycles` |
| FR-PAR-05 | Interventions realisees et prevues | LIVRE | `farm_operations` |
| FR-PAR-06 | Observations et documents | LIVRE | `farm_operations` + `file_id` |
| FR-PAR-07 | Responsables et travailleurs affectes | LIVRE | `AssignFarmOperation` |
| FR-PAR-08 | **Ne pas pretendre mesurer une superficie automatiquement** | LIVRE | `area_source` + `FarmAreaGuard` |

### 1.3 Planification des cultures

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| FR-PLN-01 | Types d'operation (preparation, semis, fertilisation, irrigation, desherbage, surveillance, recolte, stockage) | LIVRE | `FarmOperationType` |
| FR-PLN-02 | Chaque operation planifiable, affectable, suivie, cloturable | LIVRE | cycle de vie complet |
| FR-PLN-03 | Rappels selon dates et regles configurees | LIVRE | `planned_start_at` / `planned_end_at` |
| FR-PLN-04 | **Ne pas presenter un calendrier generique comme une prescription** | LIVRE | aucune recommandation agronomique produite |

### 1.4 Travailleurs et equipes

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| FR-WRK-01 | Invitation des employes via le socle RH existant | LIVRE | `Employee` (BC-04) |
| FR-WRK-02 | Roles adaptes au systeme existant | LIVRE | roles RH + `farm_team_members` |
| FR-WRK-03 | L'employe consulte ses taches et declare l'avancement | LIVRE | `progress` / `report-problem` |
| FR-WRK-04 | Le responsable affecte un travailleur a une parcelle / intervention | LIVRE | `AssignFarmOperation` |
| FR-WRK-05 | **Permissions controlees cote serveur** | LIVRE | `FarmCapabilityGate` + verification d'appartenance |
| FR-WRK-06 | Affectation d'un employe d'un AUTRE tenant refusee | LIVRE | 422 `FARM_ASSIGNMENT_*` |

### 1.5 Intrants et stocks

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| FR-STK-01 | Catalogue produits, unites, conditionnements | LIVRE | `farm_inputs` |
| FR-STK-02 | Entrees, sorties, ajustements | LIVRE | `FarmInputStockService` |
| FR-STK-03 | Fournisseurs et achats | PARTIEL | `supplier_id` reference le socle ; pas d'ecran d'achat dedie |
| FR-STK-04 | Affectation des intrants aux operations | LIVRE | `farm_operation_id` sur le mouvement |
| FR-STK-05 | Seuils d'alerte | LIVRE | `alert_threshold` (nullable) |
| FR-STK-06 | Historique des mouvements | LIVRE | journal immuable |
| FR-STK-07 | Suivi des lots et dates de peremption | LIVRE | `farm_input_lots` |
| FR-STK-08 | **Aucun conseil de dosage non verifie** | LIVRE | `is_regulated` = drapeau seul |

### 1.6 Materiel agricole

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| FR-EQP-01 | Identification et caracteristiques | LIVRE | `farm_equipment` |
| FR-EQP-02 | Date d'acquisition | LIVRE | `acquired_on` |
| FR-EQP-03 | Affectation a un site ou une equipe | LIVRE | `farm_site_id`, `assigned_team_id` |
| FR-EQP-04 | Heures de fonctionnement ou kilometrage | LIVRE | `meter_type` + journal de releves |
| FR-EQP-05 | Historique d'utilisation | LIVRE | `farm_equipment_usage_logs` |
| FR-EQP-06 | Entretien prevu et realise | LIVRE | `farm_equipment_maintenances` |
| FR-EQP-07 | Pannes et reparations | LIVRE | `farm_equipment_breakdowns` |
| FR-EQP-08 | Couts de maintenance | LIVRE | `cost_amount` (nullable) |
| FR-EQP-09 | Documents associes | LIVRE | `farm_equipment_documents` |
| FR-EQP-10 | **Saisie manuelle uniquement, aucune telemetrie** | LIVRE | `FarmMeterSource` = `manual` / `imported` seulement |
| FR-EQP-11 | Un compteur ne recule pas | LIVRE | `FARM_EQUIPMENT_METER_REGRESSION` |

### 1.7 Elevage

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| FR-LVS-01 | Registre individuel ou par lot | LIVRE | `farm_animals` + `farm_livestock_batches` |
| FR-LVS-02 | Espece, race, sexe, age, origine | LIVRE | champs declares (age jamais invente) |
| FR-LVS-03 | Effectifs, achats, ventes, transferts, naissances, mortalite | LIVRE | `FarmLivestockEventType` |
| FR-LVS-04 | Reproduction, gestation, mises bas | LIVRE | `farm_reproduction_events` |
| FR-LVS-05 | Alimentation, rations, consommation, couts | LIVRE | `farm_feed_records` |
| FR-LVS-06 | Sante, vaccinations, traitements, rappels | LIVRE | `farm_animal_health_events` |
| FR-LVS-07 | Production de lait, oeufs, autres | LIVRE | `farm_production_records` |
| FR-LVS-08 | Stocks d'aliments et produits veterinaires | LIVRE | reutilise le catalogue d'intrants |
| FR-LVS-09 | Taches quotidiennes, employes, alertes | LIVRE | `farm_operations` + rappels sanitaires |
| FR-LVS-10 | Depenses, recettes, rentabilite par animal / lot / atelier | LIVRE | `farm_expenses` + `farm_sales_links` |
| FR-LVS-11 | **Aucun diagnostic veterinaire automatise** | LIVRE | aucun diagnostic produit |
| FR-LVS-12 | Une sortie ne peut pas depasser l'effectif present | LIVRE | `FARM_LIVESTOCK_INSUFFICIENT_HEAD` |
| FR-LVS-13 | Une tete ne peut pas sortir deux fois | LIVRE | `FARM_LIVESTOCK_ANIMAL_ALREADY_LEFT` |

### 1.8 Recoltes, entreposage et tracabilite

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| FR-HRV-01 | Enregistrement par parcelle, culture, date, lot | LIVRE | `farm_harvest_lots` |
| FR-HRV-02 | Quantites et unites | LIVRE | `quantity_harvested` + `unit` |
| FR-HRV-03 | Qualite / classement si disponible | LIVRE | `quality_grade` (nullable) |
| FR-HRV-04 | Lieu de stockage | LIVRE | `farm_storage_locations` |
| FR-HRV-05 | Mouvements entre emplacements | LIVRE | `transfer` (ne retire rien du lot) |
| FR-HRV-06 | Pertes et ajustements | LIVRE | `loss` / `adjustment` |
| FR-HRV-07 | Ventes associees | LIVRE | `sale_document_id` obligatoire |
| FR-HRV-08 | Historique des lots | LIVRE | journal immuable |
| FR-HRV-09 | **Aucun double comptage recolte / stock / vente** | LIVRE | `reconcile()` : `dispatched + remaining == harvested` |

### 1.9 Achats, ventes, depenses, rentabilite

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| FR-FIN-01 | Fournisseurs, acheteurs, commandes | PARTIEL | references par identifiant vers le socle |
| FR-FIN-02 | Demandes de prix et achats | NON LIVRE | hors perimetre de cette iteration |
| FR-FIN-03 | Commandes clients, livraisons, factures | NON LIVRE | **volontairement** : reutiliser le socle de ventes |
| FR-FIN-04 | Paiements et restes dus | NON LIVRE | **volontairement** : appartient au socle |
| FR-FIN-05 | Rapports de vente | LIVRE | `farm_sales_links` + `profitability` |
| FR-FIN-06 | Depenses par exploitation / parcelle / culture / lot / campagne | LIVRE | `farm_expenses` (6 axes) |
| FR-FIN-07 | Couts intrants, main-d'oeuvre, materiel, stockage, transport | LIVRE | `FarmExpenseCategory` |
| FR-FIN-08 | Chiffre d'affaires enregistre | LIVRE | `revenue_recorded` |
| FR-FIN-09 | Marge estimee | LIVRE | `margin_estimated` |
| FR-FIN-10 | **Distinguer reel / estime / manquant** | LIVRE | `FarmDataQuality` + `missing_inputs` |
| FR-FIN-11 | **Aucun systeme financier parallele** | LIVRE | aucune facture, aucun paiement, aucune ecriture |

### 1.10 Applications et hors ligne

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| FR-APP-01 | Auditer les applications existantes | LIVRE | `FarmAppCatalogueService` |
| FR-APP-02 | Determiner si une application mobile dediee est necessaire | LIVRE | conclusion : non necessaire a ce stade |
| FR-APP-03 | Experience adaptee aux profils | LIVRE | `GET /farm/apps/roles/{role}/tools` |
| FR-APP-04 | **Aucun faux lien de telechargement** | LIVRE | `mobile_available: false` explicite |
| FR-OFF-01 | Consultation des taches deja synchronisees | LIVRE | `GET /farm/operations` |
| FR-OFF-02 | Consultation des parcelles autorisees | LIVRE | `GET /farm/parcels` |
| FR-OFF-03 | Saisie d'observations | LIVRE | `record_observation` |
| FR-OFF-04 | Enregistrement de certaines operations | LIVRE | `record_operation` |
| FR-OFF-05 | Photos et pieces jointes | LIVRE | `attach_photo` (via `file_id`) |
| FR-OFF-06 | Synchronisation avec identifiants uniques | LIVRE | `client_operation_id` |
| FR-OFF-07 | Idempotence | LIVRE | rejeu => `replayed: true` |
| FR-OFF-08 | Resolution explicite des conflits | LIVRE | `FarmConflictStrategy` |
| FR-OFF-09 | Historique des erreurs | LIVRE | `GET /farm/sync/errors` |
| FR-OFF-10 | Coherence conservee pour finances et stock | LIVRE | `manual_review` impose |
| FR-OFF-11 | **Ne pas promettre de fonction hors ligne non implementee** | LIVRE | registre ferme + rejet explicite |

### 1.11 Assistant agricole et integrations

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| FR-AI-01 | Auditer l'orchestrateur IA existant | LIVRE | `App\AI\AgentRunner` reutilise |
| FR-AI-02 | Retrouver ses informations, resumer activites et taches | LIVRE | `buildContext()` |
| FR-AI-03 | Preparer un planning de travail | LIVRE | section `tasks` |
| FR-AI-04 | Identifier les stocks a renouveler | LIVRE | section `inputs_to_reorder` |
| FR-AI-05 | Preparer des rapports | LIVRE | sections agregees |
| FR-AI-06 | Formuler des questions a un conseiller | LIVRE | `advisorQuestions()` |
| FR-AI-07 | **Donnees auxquelles l'utilisateur a reellement acces** | LIVRE | contexte borne aux capacites activees |
| FR-AI-08 | Actions modifiant des donnees via les mecanismes de validation existants | LIVRE | aucun acces direct en ecriture |
| FR-AI-09 | **Aucune fausse meteo, faux prix, recommandation certaine** | LIVRE | `externalDataPolicy()` declare tout indisponible |
| FR-AI-10 | **Aucun agent autonome, aucun moteur SQL libre** | LIVRE | contexte prepare uniquement |
| FR-INT-01 | Identifier les integrations utiles et leur disponibilite | LIVRE | `GET /farm/integrations` |
| FR-INT-02 | Evaluer valeur, fiabilite, cout, conditions | LIVRE | matrice documentee |
| FR-INT-03 | **Aucune API payante, aucun abonnement** | LIVRE | tout declare non branche |

### 1.12 Tableau de bord

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| FR-DSH-01 | Taches du jour | LIVRE | `today_operations` |
| FR-DSH-02 | Interventions en retard | LIVRE | `late_operations` |
| FR-DSH-03 | Etat des cultures | LIVRE | `crop_cycles` |
| FR-DSH-04 | Recoltes prevues | PARTIEL | stock restant livre ; prevision non modelisee |
| FR-DSH-05 | Stocks a renouveler | LIVRE | `inputs_to_reorder` |
| FR-DSH-06 | Equipements necessitant un entretien | LIVRE | `equipment_maintenance_due` |
| FR-DSH-07 | Evenements d'elevage | LIVRE | `livestock` |
| FR-DSH-08 | Ventes et depenses enregistrees | LIVRE | `finance` |
| FR-DSH-09 | Alertes et notifications | LIVRE | rappels sanitaires + seuils |
| FR-DSH-10 | Operations en attente de synchronisation | LIVRE | `pending_sync` |
| FR-DSH-11 | Etats vides utiles | LIVRE | `empty_state` + `state` par section |

---

## 2. Exigences techniques

| ID | Exigence | Statut | Verification |
|---|---|---|---|
| TR-01 | Reutiliser l'architecture multi-tenant existante | LIVRE | `BelongsToCompany` |
| TR-02 | Controle des acces cote serveur | LIVRE | `FarmCapabilityGate` |
| TR-03 | Isolation des donnees par exploitation | LIVRE | tests d'isolation par module |
| TR-04 | Journalisation des operations sensibles | LIVRE | journaux immuables |
| TR-05 | Migrations reversibles | LIVRE | `down()` non destructif |
| TR-06 | Protection des fichiers et documents | LIVRE | `file_id` vers le service existant |
| TR-07 | Eviter requetes couteuses | LIVRE | index sur `(company_id, ...)` |
| TR-08 | Operations de synchronisation idempotentes | LIVRE | identifiants client |
| TR-09 | Preserver les fonctionnalites des autres verticales | LIVRE | 0 echec FarmOs dans la suite Feature |
| TR-10 | Aucune nouvelle abstraction centrale | LIVRE | 0 import croise hors contrat |
| TR-11 | PHPStan modules + strict sans erreur | LIVRE | verifie par sous-repertoire |
| TR-12 | Contrat OpenAPI a jour | LIVRE | 73 chemins `/farm/*`, redocly 0 erreur |
| TR-13 | Fixture de schema miroir | LIVRE | `CreatesMvpSchema` (31 tables) |
| TR-14 | Aucun libelle accentue en dur | LIVRE | codes d'erreur ASCII + cles i18n |

---

## 3. Criteres d'acceptation globaux

1. Un agriculteur peut creer son espace et effectuer ses premieres operations **sans intervention manuelle**.
2. Aucune fonctionnalite n'est presentee comme operationnelle si elle n'est pas implementee et testee.
3. Aucune donnee manquante n'est remplacee par zero.
4. Aucune quantite n'est comptabilisee deux fois.
5. Aucun systeme parallele (tenant, roles, provisionnement, finance, IA, sync).
6. Les autres verticales ne regressent pas.

---

## 4. Hors perimetre (assume)

Telemetrie/capteurs · OCR · imagerie satellite · meteo temps reel · prix de marche temps reel · POS · tracabilite reglementaire certifiee · paie agricole specifique par pays · application mobile dediee · application desktop.
