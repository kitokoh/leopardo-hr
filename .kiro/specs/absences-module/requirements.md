# Requirements — Module Absences

## Vue d'ensemble

Le module Absences expose une API REST JSON permettant aux employés de soumettre des demandes d'absence et aux managers de les approuver ou rejeter. Il gère le solde de congés via la table `leave_balance_logs`.

Tables existantes en base : `absences`, `absence_types`, `leave_balance_logs`.

---

## Requirement 1 — Liste des absences

**User Story :** En tant qu'employé ou manager, je veux consulter la liste des absences afin de suivre les demandes en cours et passées.

### Acceptance Criteria

1. WHEN un employé appelle `GET /absences` THEN le système SHALL retourner uniquement ses propres absences, paginées
2. WHEN un manager appelle `GET /absences` THEN le système SHALL retourner toutes les absences de la company, paginées
3. WHEN le paramètre `employee_id` est fourni par un manager THEN le système SHALL filtrer les résultats sur cet employé
4. WHEN les paramètres `month` et `year` sont fournis THEN le système SHALL filtrer les absences dont la période chevauche le mois demandé
5. WHEN le paramètre `status` est fourni THEN le système SHALL filtrer les absences par statut (`pending`, `approved`, `rejected`, `cancelled`)
6. WHEN la liste est retournée THEN le système SHALL inclure les métadonnées de pagination (`current_page`, `per_page`, `total`)

---

## Requirement 2 — Création d'une demande d'absence

**User Story :** En tant qu'employé, je veux soumettre une demande d'absence afin d'informer mon manager de mon indisponibilité.

### Acceptance Criteria

1. WHEN un employé soumet `POST /absences` avec des dates valides et un `absence_type_id` existant THEN le système SHALL créer une absence avec le statut `pending`
2. WHEN le type d'absence a `deducts_leave = true` et le solde de congés de l'employé est insuffisant THEN le système SHALL rejeter la demande avec le code d'erreur `INSUFFICIENT_LEAVE_BALANCE`
3. WHEN les dates de la nouvelle demande chevauchent une absence existante non annulée du même employé THEN le système SHALL rejeter la demande avec le code d'erreur `ABSENCE_DATE_CONFLICT`
4. WHEN la demande est créée avec succès THEN le système SHALL retourner la ressource créée avec le statut HTTP 201
5. WHEN `end_date` est antérieure à `start_date` THEN le système SHALL retourner une erreur de validation 422
6. WHEN `days_count` n'est pas fourni THEN le système SHALL le calculer automatiquement à partir de `start_date` et `end_date`

---

## Requirement 3 — Détail d'une absence

**User Story :** En tant qu'employé ou manager, je veux consulter le détail d'une absence afin d'en connaître tous les attributs.

### Acceptance Criteria

1. WHEN un employé appelle `GET /absences/{id}` sur sa propre absence THEN le système SHALL retourner la ressource complète
2. WHEN un employé appelle `GET /absences/{id}` sur l'absence d'un autre employé THEN le système SHALL retourner HTTP 403
3. WHEN un manager appelle `GET /absences/{id}` sur n'importe quelle absence de la company THEN le système SHALL retourner la ressource complète
4. WHEN l'absence n'existe pas THEN le système SHALL retourner HTTP 404

---

## Requirement 4 — Approbation d'une absence

**User Story :** En tant que manager, je veux approuver une demande d'absence afin de valider l'indisponibilité de l'employé et déduire son solde.

### Acceptance Criteria

1. WHEN un manager appelle `PUT /absences/{id}/approve` sur une absence `pending` THEN le système SHALL passer le statut à `approved` et enregistrer `approved_by`
2. WHEN le type d'absence a `deducts_leave = true` THEN le système SHALL déduire `days_count` du solde de congés de l'employé de manière atomique (transaction DB)
3. WHEN la déduction est effectuée THEN le système SHALL créer une entrée dans `leave_balance_logs` avec `delta` négatif et `reason = 'absence_approved'`
4. WHEN l'absence n'est pas en statut `pending` THEN le système SHALL retourner le code d'erreur `ABSENCE_NOT_PENDING` avec HTTP 422
5. WHEN un employé (non manager) appelle cet endpoint THEN le système SHALL retourner HTTP 403
6. WHEN l'approbation réussit THEN le système SHALL retourner la ressource mise à jour

---

## Requirement 5 — Rejet d'une absence

**User Story :** En tant que manager, je veux rejeter une demande d'absence afin d'en informer l'employé avec une raison.

### Acceptance Criteria

1. WHEN un manager appelle `PUT /absences/{id}/reject` avec une `rejected_reason` THEN le système SHALL passer le statut à `rejected`
2. WHEN `rejected_reason` est absent ou vide THEN le système SHALL retourner une erreur de validation 422
3. WHEN l'absence était déjà `approved` et son solde avait été déduit THEN le système SHALL restaurer le solde de congés et créer une entrée `leave_balance_logs` avec `delta` positif et `reason = 'absence_rejected'`
4. WHEN l'absence n'est pas en statut `pending` ou `approved` THEN le système SHALL retourner le code d'erreur `ABSENCE_NOT_PENDING` avec HTTP 422
5. WHEN un employé (non manager) appelle cet endpoint THEN le système SHALL retourner HTTP 403

---

## Requirement 6 — Annulation d'une absence

**User Story :** En tant qu'employé, je veux annuler ma propre demande d'absence en attente afin de la retirer si mes plans changent.

### Acceptance Criteria

1. WHEN un employé appelle `DELETE /absences/{id}` sur sa propre absence `pending` THEN le système SHALL passer le statut à `cancelled`
2. WHEN l'absence n'est pas en statut `pending` THEN le système SHALL retourner le code d'erreur `ABSENCE_NOT_PENDING` avec HTTP 422
3. WHEN un employé tente d'annuler l'absence d'un autre employé THEN le système SHALL retourner HTTP 403
4. WHEN l'annulation réussit THEN le système SHALL retourner HTTP 200 avec la ressource mise à jour

---

## Requirement 7 — Sécurité et isolation multitenant

**User Story :** En tant qu'opérateur de la plateforme, je veux que chaque company ne voie que ses propres données d'absence afin de garantir l'isolation des tenants.

### Acceptance Criteria

1. WHEN toute requête sur `/absences` est traitée THEN le système SHALL appliquer le scope `company_id` via le middleware `tenant` et le trait `BelongsToCompany`
2. WHEN un token Sanctum invalide ou absent est fourni THEN le système SHALL retourner HTTP 401
3. WHEN un employé tente d'accéder à une absence d'une autre company THEN le système SHALL retourner HTTP 404 (pas de fuite d'information)
