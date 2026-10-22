# Farm OS — AppFlow (parcours utilisateurs)

**Version :** 2.0 — 2026-10-18 · **Branche :** `bc/bc-37-farm-os`

> Les parcours ci-dessous sont alignes sur les routes **reellement livrees** (`api/routes/modules/farm_os.php`, 83 routes).

---

## 1. Parcours de l'agriculteur (proprietaire) — de la vitrine a la premiere operation

```text
[Vitrine Leopardo]
      |
      | selectionne « Farm OS »
      v
[Etape 1 — Identifier l'activite]        PUT /farm/activities
   selection MULTIPLE parmi 16 activites (registre ferme FarmActivity)
      |
      v
[Etape 2 — Configurer l'exploitation]    GET/PATCH /farm/settings
   nom, pays, region, langues, fuseau, devise, unites, superficie,
   nb parcelles, nb travailleurs, equipements
   -> TOUS LES CHAMPS OPTIONNELS (profil completable)
      |
      v
[Etape 3 — Recommander les outils]       GET /farm/capabilities/available
   13 outils proposes selon l'activite
   l'utilisateur ACCEPTE / REFUSE / MODIFIE   PUT /farm/capabilities
      |
      v
[Etape 4 — Provisionner l'espace]        POST /farm/provision
   - idempotent (rejeu => already_active)
   - recuperable (rejeu sur echec)
   - un outil refuse RESTE refuse
   - fail-closed (allowlist d'activites)
      |
      v
[Checklist des etapes restantes]         (relue en BASE, jamais codee en dur)
      |
      +--> creer la premiere parcelle    POST /farm/sites/{site}/parcels
      +--> creer la premiere activite    POST /farm/parcels/{parcel}/crop-cycles
      +--> creer la premiere operation   POST /farm/sites/{site}/operations
      |
      v
[Tableau de bord]                        GET /farm/dashboard
   etat vide utile : create_first_site -> create_first_parcel -> ready
```

### Ajouter une activite plus tard

```text
[Parametres] -> ajoute « elevage ovin »
      |
      v
PUT /farm/activities  (ADDITIF)
   -> l'entreprise n'est PAS recreee
   -> les donnees existantes sont CONSERVEES
   -> les nouveaux outils sont proposes
```

---

## 2. Parcours du responsable d'exploitation — planifier et affecter

```text
[Tableau de bord]
   taches du jour / interventions en retard
      |
      v
[Planifier une intervention]             POST /farm/sites/{site}/operations
   type, parcelle, date debut, date fin
      |
      v
[Affecter un travailleur ou une equipe]  POST /farm/operations/{op}/assign
   - employe ET equipe verifies comme appartenant a l'ENTREPRISE
   - affectation d'un autre tenant => 422 FARM_ASSIGNMENT_*
   - idempotent (repetition => pas de nouvelle notification)
   - l'auteur n'est pas notifie de sa propre action
   - seuls les membres ACTIFS de l'equipe sont notifies
      |
      v
[Suivre l'avancement]                    GET /farm/operations/{op}
   start -> progress -> complete / skip / cancel
      |
      v
[Consulter les alertes]
   - intrants sous seuil declare        GET /farm/inputs/alerts
   - entretiens dus                     GET /farm/equipment/maintenance-due
   - rappels sanitaires                 GET /farm/livestock/summary
```

---

## 3. Parcours du travailleur agricole — sur le terrain, parfois hors reseau

```text
[Application web de l'espace professionnel]
   (aucune application mobile dediee n'est publiee :
    le catalogue le dit explicitement)
      |
      v
[Mes taches du jour]                     GET /farm/operations/today
      |
      +--> demarrer                         POST /farm/operations/{op}/start
      +--> declarer l'avancement            POST /farm/operations/{op}/progress
      +--> signaler un probleme             POST /farm/operations/{op}/report-problem
      +--> cloturer                         POST /farm/operations/{op}/complete
      |
      v
[PERTE DE RESEAU]
      |
      v
[Travail hors ligne — registre FERME (8 types)]
   autorise :
     - record_observation        - record_operation
     - record_input_movement     - record_harvest_movement
     - record_livestock_event    - record_equipment_usage
     - record_expense            - attach_photo
   REFUSE (et conserve) :
     - toute facturation, tout paiement
     - toute suppression
     - toute modification d'un montant deja enregistre
      |
      v
[RETOUR DU RESEAU]
      |
      v
POST /farm/sync/operations   (1 a 200 operations)
      |
      +-- identifiant deja vu + MEME contenu  -> replayed: true (RIEN re-applique)
      +-- identifiant deja vu + contenu DIFFERENT -> CONFLIT (les 2 valeurs gardees)
      +-- operation non prevue -> REJETEE (visible dans l'historique)
      +-- observation / photo / evenement -> appliquee (additive)
      +-- STOCK ou FINANCE -> manual_review (ARBITRAGE HUMAIN)
      |
      v
[Arbitrage des conflits]                 GET /farm/sync/conflicts
                                         POST /farm/sync/conflicts/{id}/resolve
   strategie : local_wins / server_wins / manual_review
   -> la decision est CONSERVEE (audit)
   -> un conflit resolu ne peut pas etre re-resolu
      |
      v
[Historique des erreurs]                 GET /farm/sync/errors
   une operation en echec reste VISIBLE, jamais perdue
```

---

## 4. Parcours du gestionnaire de stock

```text
[Reception d'une livraison]              POST /farm/inputs/{input}/receive
   lot, quantite, peremption (si pertinente), fournisseur
      |
      v
[Sortie vers une operation]              POST /farm/inputs/{input}/issue
   quantite, operation concernee
      |
      v
[Inventaire]                             POST /farm/inputs/{input}/adjust
   quantite CIBLE absolue (pas un delta)
      |
      v
[Alertes]                                GET /farm/inputs/alerts
   UNIQUEMENT les intrants avec seuil DECLARE
   (un intrant sans seuil n'apparait jamais)
```

---

## 5. Parcours du responsable commercial

```text
[Vente conclue dans le socle Leopardo]
   (facture emise par le service de VENTES EXISTANT)
      |
      v
[Rattacher la vente a un objet agricole]  POST /farm/finance/sale-links
   sale_document_id (OBLIGATOIRE), montant, lot de recolte ou lot d'animaux
   - Farm OS ne cree AUCUN document commercial
   - un meme document ne peut pas etre impute 2x au meme lot
      |
      v
[Sortie de stock associee]                POST /farm/harvests/lots/{lot}/movements
   movement_type = sale, sale_document_id OBLIGATOIRE
   - refuse si la quantite depasse le stock restant
      |
      v
[Rentabilite]                             GET /farm/finance/profitability
   costs_real / costs_estimated / revenue_recorded / margin_estimated
   is_reliable + missing_inputs
   -> une marge sur donnees incompletes n'est JAMAIS presentee comme certaine
```

---

## 6. Parcours de l'eleveur

```text
[Creer un lot de cheptel]                POST /farm/livestock/batches
   espece, race, sexe  -> effectif initial ZERO
      |
      v
[Entree d'effectif]                      POST /farm/livestock/batches/{b}/events
   purchase / birth / transfer_in
      |
      +--> identifier un animal          POST /farm/livestock/animals
      |
      v
[Suivi quotidien]
   +-- alimentation                      POST .../feed
   +-- production (lait, oeufs)          POST .../production
   +-- sante / vaccination / rappel      POST .../health-events
   +-- reproduction / gestation          POST .../reproduction-events
      |
      v
[Sortie d'effectif]                      POST .../events
   sale / death / slaughter / transfer_out / cull
   - refuse si la quantite depasse l'effectif PRESENT
   - un animal deja sorti ne peut pas sortir 2x
      |
      v
[Rentabilite par lot]                    GET /farm/finance/profitability
```

---

## 7. Parcours de l'assistant agricole

```text
[Question de l'exploitant]
      |
      v
GET /farm/assistant/context
   contexte STRICTEMENT interne, borne aux outils ACTIVES :
     - taches (total, en retard, du jour)
     - intrants a reapprovisionner
     - entretiens dus
     - cheptel present + rappels sanitaires
     - stock de recolte par culture
   + externalDataPolicy() : meteo / prix / satellite / capteurs
     declares INDISPONIBLES (source: null, freshness: null)
      |
      v
[Orchestrateur IA EXISTANT de Leopardo]  (App\AI\AgentRunner)
   - aucun nouveau fournisseur IA
   - aucun moteur SQL libre
   - toute action modifiant des donnees passe par les mecanismes
     de validation et de confirmation DEJA definis
      |
      v
[Reponse] + GET /farm/assistant/advisor-questions
   des QUESTIONS a poser a un conseiller agricole,
   jamais des recommandations presentees comme certaines
```

---

## 8. Etats d'echec et d'attente (obligatoires)

| Situation | Ce que voit l'utilisateur |
|---|---|
| Solution inactive | « Farm OS n'est pas active » + bouton d'activation (pas de page blanche) |
| Capacite non activee | « Cet outil n'est pas active » + « Activer l'outil » |
| Provisioning partiel | Etape listee « A corriger » + cause lisible + bouton « Reessayer » |
| Aucune donnee | Etat vide utile : 1 phrase + 1 action |
| Hors ligne | Bandeau + compteur d'operations en attente + bouton « Reessayer » |
| Ecriture rejetee (validation) | Message champ par champ, valeur conservee |
| Conflit de synchronisation | « Conflit detecte » + valeurs locale et serveur + choix explicite **jamais** automatique |
