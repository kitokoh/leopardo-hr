# Farm OS — Implementation Plan (tranches, dependances, matrices, tests, risques, ADR)

**Version :** 2.0 — 2026-10-18 · **Branche :** `bc/bc-37-farm-os` · **PR :** #8572

---

## PARTIE 1 — Etat d'avancement par tranche

| Tranche | Contenu | Etat |
|---|---|---|
| **T0** | Fondations : industrie `agriculture`, manifest, `ModuleRegistry`, BC-37, fixture, OpenAPI, i18n | **LIVRE** |
| **T1.1** | Exploitations/sites, parcelles, cycles culturaux, garde de superficie | **LIVRE** |
| **T1.2** | Interventions agricoles (machine a etats fermee, idempotence terrain) | **LIVRE** |
| **T1.3** | Equipes + affectation controlee cote serveur + notification | **LIVRE** |
| **T1.4** | Onboarding : activites, profil, outils recommandes | **LIVRE** |
| **T1.5** | Etape 4 — provisioning idempotent de l'espace | **LIVRE** |
| **T2.1** | Intrants et stocks (catalogue, lots, journal immuable) | **LIVRE** |
| **T2.2** | Materiel agricole (compteurs manuels, entretien, pannes, couts, documents) | **LIVRE** |
| **T3.1** | Elevage (cheptel, effectif, sante, reproduction, production, alimentation) | **LIVRE** |
| **T3.2** | Recoltes, entreposage, tracabilite (coherence anti-double-comptage) | **LIVRE** |
| **T4.1** | Depenses, campagnes, rattachement de ventes, rentabilite honnete | **LIVRE** |
| **T5.1** | Applications : catalogue « Mes logiciels », outils par role | **LIVRE** |
| **T5.2** | Hors ligne : file idempotente, conflits explicites, historique d'erreurs | **LIVRE** |
| **T6.1** | Assistant agricole (orchestrateur existant, contexte borne) | **LIVRE** |
| **T6.2** | Matrice des integrations externes (aucune API payante) | **LIVRE** |
| **T7.1** | Tableau de bord etendu aux modules livres | **LIVRE** |
| **T8** | Documents de conception (ce dossier) | **LIVRE** |

### Ce qui n'est PAS livre (assume)

| Element | Raison |
|---|---|
| Demandes de prix, commandes clients, factures, paiements | Appartient au socle de ventes — Farm OS **reference** par identifiant |
| Telemetrie capteur, GPS automatique | Aucune integration reelle ; saisie manuelle assumee |
| Meteo, prix de marche, imagerie satellite | Aucune source branchee ; declare indisponible |
| Application mobile Farm OS dediee | Aucune application publiee ; le catalogue le dit |
| Diagnostic veterinaire automatise | Ne remplace pas un professionnel qualifie |
| Prevision de recolte | Non modelisee ; seul le stock restant est expose |

---

## PARTIE 2 — Matrice des fonctionnalites par type d'exploitation

Legende : ● requis · ○ optionnel (propose) · – non propose

| Outil (capacite) | Cereales | Maraichage | Arboriculture | Viticulture | Serre | Elevage | Aviculture | Apiculture | Aquaculture | Mixte | Cooperative | Services |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Parcelles & cultures | ● | ● | ● | ● | ● | ○ | ○ | ○ | ○ | ● | ● | – |
| Planning des travaux | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ○ | ● |
| Travailleurs | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ● | ● | ● |
| Materiel | ● | ● | ● | ● | ○ | ● | ○ | ○ | ○ | ● | ● | ● |
| Stocks & intrants | ● | ● | ● | ● | ● | ● | ● | ○ | ● | ● | ● | ○ |
| Irrigation & suivi | ○ | ● | ○ | ● | ● | – | – | – | – | ● | ○ | ○ |
| Cheptel | – | – | – | – | – | ● | ● | ● | ● | ● | ○ | – |
| Achats & fournisseurs | ● | ● | ● | ● | ○ | ● | ● | ○ | ● | ● | ● | ● |
| Recoltes & entreposage | ● | ● | ● | ● | ● | ○ | ● | ● | ● | ● | ● | – |
| Ventes & clients | ● | ● | ● | ● | ● | ○ | ○ | ● | ● | ● | ● | ● |
| Finance & depenses | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● | ● |
| Rapports & indicateurs | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ● | ● | ● |
| Notifications & assistant | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ○ | ● | ○ | ○ |

> Regle : ces propositions sont **modifiables** par l'utilisateur. Elles ne bloquent jamais l'acces a un outil accepte manuellement.

---

## PARTIE 3 — Matrice des roles et permissions

| Permission | Proprietaire / Admin | Resp. exploitation | Chef d'equipe | Ouvrier | Resp. stock | Resp. materiel | Resp. commercial | Comptable |
|---|---|---|---|---|---|---|---|---|
| `farm.admin` | ✔ | – | – | – | – | – | – | – |
| `farm.manager` | ✔ | ✔ | ✔ (perimetre equipe) | – | – | – | – | – |
| `farm.worker` | ✔ | ✔ | ✔ | ✔ | – | – | – | – |
| `farm.stock` | ✔ | ✔ | – | – | ✔ | – | – | – |
| `farm.equipment` | ✔ | ✔ | – | – | – | ✔ | – | – |
| `farm.livestock` | ✔ | ✔ | ✔ | ✔ (declaration) | ○ | – | – | – |
| `farm.sales` | ✔ | ✔ | – | – | – | – | ✔ | – |
| `farm.accounting` | ✔ | ✔ | – | – | – | – | – (sauf CA) | ✔ |
| `farm.regulated_inputs` | ✔ | ✔ | – | – | ✔ | – | – | – |

Visibilite financiere : **jamais** pour chef d'equipe, ouvrier, resp. stock, resp. materiel. Un ouvrier ne voit que ses taches et les parcelles autorisees.

---

## PARTIE 4 — Catalogue des 13 outils activables (capacites)

| Code capacite | Libelle | Modules/socle reutilises |
|---|---|---|
| `parcels_crops` | Gestion des parcelles et cultures | Farm OS (parcels, crop_cycles) |
| `work_planning` | Planning des travaux agricoles | Farm OS operations + Planning |
| `workers` | Gestion des travailleurs | HR + `EmployeeModuleGrant` |
| `equipment` | Gestion du materiel | Farm OS equipment |
| `stocks_inputs` | Stocks et intrants | Farm OS stock |
| `irrigation` | Irrigation et suivi des operations | Farm OS operations + observations |
| `livestock` | Gestion du cheptel | Farm OS livestock |
| `purchases_suppliers` | Achats et fournisseurs | Farm OS + Expense/Accounting |
| `harvest_storage` | Recoltes et entreposage | Farm OS harvest + stock |
| `sales_customers` | Ventes et clients | Farm OS + CRM/Billing |
| `finance_expenses` | Gestion financiere et depenses | Expense + Accounting |
| `reports_kpis` | Rapports et indicateurs | Farm OS reporting |
| `notifications_assistant` | Notifications et assistant agricole | Notification + AI orchestrator |

---

## PARTIE 5 — Matrice des integrations externes et de leurs couts

| Integration | Valeur metier | Disponibilite | Cout | Decision |
|---|---|---|---|---|
| Meteo agricole | Rappels/planification | Fournisseurs varies | Freemium/payant | **Port declare, adaptateur « non configure »** — aucune activation sans autorisation |
| Cartographie / GPS | Localisation parcelles | Core `Geo` deja present | Nul (interne) | **Retenu** (geometrie nullable) |
| Imagerie satellite / NDVI | Suivi cultures | Payant | Eleve | **Non retenu** |
| Capteurs & telemetrie | Releves automatiques | Materiel + integration | Eleve | **Non retenu** (saisie manuelle assumee) |
| Donnees de marche | Prix de vente | Sources publiques/payantes | Variable | **Port declare**, source + fraicheur obligatoires |
| Catalogues fournisseurs | Saisie facilitee | Rare/partenaire | Variable | **Non retenu** (catalogue interne) |

> Regle : **aucune API payante, aucun abonnement, aucune depense d'infrastructure** sans autorisation explicite de l'utilisateur.

---

## PARTIE 6 — Plan de tests hors-ligne et de synchronisation

| # | Test | Type | Ce qui est prouve |
|---|---|---|---|
| OL-1 | Creation d'operation hors ligne → rejeu → **une seule** operation | integration | idempotence par `client_operation_id` |
| OL-2 | Deux appareils modifient la meme operation hors ligne | integration | conflit **detecte**, journalise, jamais ecrase silencieusement |
| OL-3 | Retour reseau partiel (50 % d'echec) | integration | les echecs restent en file avec `last_error`, rejeu possible |
| OL-4 | Mouvement de stock rejoue 3× | integration | quantite identique (pas de double decrement) |
| OL-5 | Vente hors ligne d'une quantite > disponible | integration | refus, aucune incoherence stock/vente |
| OL-6 | Ecriture financiere hors ligne | integration | **bloquee** (lecture seule) |
| OL-7 | Isolation : tenant A hors ligne ne voit jamais les donnees de tenant B | integration | snapshot local tenant-scoped |
| OL-8 | Perimetre de lecture hors ligne | unitaire | seules les capacites activees sont disponibles hors ligne |
| OL-9 | Conflit resolu « garder local » / « garder serveur » | integration | trace d'audit + coherence stock |
| OL-10 | Purge du cache apres deconnexion | unitaire | aucune donnee d'un tenant ne survit a un changement de compte |

---

## PARTIE 7 — Risques et mitigations

| Risque | Probabilite | Impact | Mitigation |
|---|---|---|---|
| Oubli d'un point d'enregistrement | Elevee | CI rouge / verticale invisible | checklist T0 + gardes CI locales avant push |
| Conflit avec une autre PR de verticale | Moyenne | conflit de merge | BC-37, edition en fin de fichier, merge de `main` avant PR |
| Volumetrie mobile (photos, offline) | Moyenne | poids, batterie | upload differe, bornes de taille, images differees |
| Double comptage recolte/stock/vente | Moyenne | donnees fausses | un lot par recolte, `quantity_remaining` derive, tests OL-4/OL-5 |
| Superficie presentee comme mesuree | Moyenne | mensonge produit | `area_source` + `FarmAreaGuard` + composant `AreaValue` |
| Rentabilite sur donnees incompletes | Moyenne | decision fausse | etiquetage reel/estime/manquant + `is_reliable` |
| Perte de donnees a l'ajout d'activite | Faible | grave | tests FR-ONB-08 (aucune migration destructive) |
| Non-regression d'autres verticales | Moyenne | casse invisible | aucune modification des tables partagees ; suites existantes rejouees |
| Tests non executables (ni PHP 8.3+ ni DB en sandbox) | **Averee** | risque de declaration fausse | declarer explicitement non execute + prerequis ; jamais de « tests verts » inventes |

---

## PARTIE 8 — Strategie de tests (globale)

| Niveau | Contenu | Outil |
|---|---|---|
| Unitaire | enums, `FarmAreaGuard`, machine a etats des operations, contrats de coherence (stock, recolte, elevage, finance, hors ligne) | PHPUnit |
| Integration API | onboarding → provisioning → parcelle → operation → cloture ; 403 par role ; 422 sur valeurs hors allowlist | PHPUnit Feature |
| Multi-tenant | creation croisee : 2 tenants, tentatives d'acces croisees | PHPUnit Feature |
| Idempotence | rejeu d'activation, de provisioning, d'ecriture terrain | PHPUnit + front unit |
| Hors-ligne | OL-1 → OL-10 | Node tests + PHPUnit |
| Front | composants, formulaire d'onboarding, etats vides, filtre par capacites | Jest/Vitest |
| Securite | permission manquante, escalade de role, donnee hors tenant | PHPUnit Feature |
| Non-regression | suites existantes + gardes CI | CI |
| Qualite | PHPStan 8, Pint, Module Structure Validator, purete des couches, OpenAPI, i18n | CI |

### Tests livres (fichiers reels)

`tests/Unit/Modules/FarmOs/` : `FarmAreaGuardTest`, `FarmCapabilityRecommenderTest`, `FarmOperationStatusTest`, `FarmOsManifestTest`, `FarmAssignmentExceptionTest`, `FarmInputStockContractTest`, `FarmEquipmentContractTest`, `FarmLivestockContractTest`, `FarmHarvestContractTest`, `FarmFinanceContractTest`, `FarmOfflineContractTest`.

`tests/Feature/Modules/FarmOs/` : `FarmProvisioningTest`, `FarmEquipmentTest`, `FarmLivestockTest`, `FarmHarvestTest`, `FarmFinanceTest`, `FarmOfflineSyncTest`.

---

## PARTIE 9 — ADR (resume des decisions)

| ADR | Decision | Statut |
|---|---|---|
| ADR-FARM-0001 | Un **seul** feature flag `farm_os` ; modularite par capacites en base (pas 13 flags) | Accepte |
| ADR-FARM-0002 | Stock agricole **local au module**, aucune abstraction dans `Core/`, pas de comptabilite parallele | Accepte |
| ADR-FARM-0003 | Honnetete des donnees : `area_source`, compteurs saisis, aucune telemetrie, aucun conseil presente comme certain | Accepte |
| ADR-FARM-0004 | Demarrer par l'interne (saisie manuelle) ; integrations externes en **ports** inactifs par defaut | Accepte |
| ADR-FARM-0005 | Reutilisation stricte du socle roles/permissions (`EmployeeModuleGrant`), aucune structure centrale nouvelle | Accepte |
| ADR-FARM-0006 | BC-37 (BC-36 etant revendique par TRADE OS) | Accepte |
| ADR-FARM-0007 | Equipes operationnelles (`farm_teams`) plutot qu'extension des tables RH partagees | Accepte |
| ADR-FARM-0008 | Hors-ligne limite a la consultation + saisie d'operations/observations ; **ecriture financiere interdite hors ligne** | Accepte |
| ADR-FARM-0009 | Journal immuable + cache derive pour toute quantite (stock, recolte, effectif) | Accepte |
| ADR-FARM-0010 | Le sens d'un mouvement est porte par le **type**, jamais par le signe | Accepte |
| ADR-FARM-0011 | Conflits de synchronisation explicites : stock et finances partent en `manual_review`, jamais « dernier ecrit gagne » | Accepte |
| ADR-FARM-0012 | Aucun nouveau fournisseur IA : l'assistant reutilise `App\AI\AgentRunner` et ne recoit qu'un contexte prepare | Accepte |
