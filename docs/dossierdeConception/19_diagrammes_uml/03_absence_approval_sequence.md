# Diagramme de séquence — Demande et Approbation d'Absence

```mermaid
sequenceDiagram
    autonumber

    participant E as Employé (Mobile)
    participant M as Manager (Mobile/Web)
    participant AC as AbsenceController
    participant AS as AbsenceService
    participant DB as Employee DB
    participant LBL as leave_balance_logs
    participant NS as NotificationService

    E->>AC: POST /absences<br/>{absence_type_id, start_date, end_date, comment}

    Note over AC: Vérification RBAC

    AC->>AS: processAbsenceRequest(data)

    AS->>DB: SELECT * FROM absence_types<br/>WHERE id = :type_id
    DB-->>AS: {is_paid, deducts_leave}

    AS->>DB: SELECT * FROM holidays<br/>WHERE date BETWEEN :start AND :end
    AS->>AS: Calcul jours ouvrables<br/>(exclure weekends + jours fériés)
    DB-->>AS: days_count

    alt is_paid AND leave_balance < days_count
        AS-->>AC: 422 INSUFFICIENT_LEAVE_BALANCE
        AC-->>E: 422 Solde de congé insuffisant
    else Solde suffisant

        AS->>DB: SELECT * FROM absences<br/>WHERE employee_id = :id<br/>AND status != 'cancelled'<br/>AND dates OVERLAP
        DB-->>AS: existing absences

        alt Chevauchement détecté
            AS-->>AC: 422 OVERLAP_WITH_EXISTING
            AC-->>E: 422 Chevauchement avec une absence existante
        else Pas de chevauchement

            AS->>DB: INSERT absences (status='pending')
            DB-->>AS: absence créée

            AS-->>AC: 201 absence record

            AS->>NS: Notify managers<br/>Push + Email : "Nouvelle demande de congé"
            NS-->>AS: Notifications envoyées

            AC-->>E: 201 Demande d'absence enregistrée
        end
    end

    Note over M: Le gestionnaire consulte ses demandes

    M->>AC: GET /absences?status=pending
    AC-->>M: Liste des demandes en attente

    alt APPROBATION

        M->>AC: PUT /absences/{id}/approve<br/>{comment}
        AC->>AS: approveAbsence(id, comment)

        AS->>DB: UPDATE absences<br/>SET status = 'approved'
        DB-->>AS: OK

        AS->>DB: UPDATE employees<br/>SET leave_balance =<br/>leave_balance - :days_count
        DB-->>AS: Solde mis à jour

        AS->>LBL: INSERT leave_balance_logs<br/>(type='consumption', amount, employee_id)
        LBL-->>AS: Log créé

        AS->>NS: Notify employee<br/>Push + Email : "Absence approuvée ✅"
        NS-->>AS: Notification envoyée

        AC-->>M: 200 Absence approuvée

    else REFUS

        M->>AC: PUT /absences/{id}/reject<br/>{comment}
        AC->>AS: rejectAbsence(id, comment)

        AS->>DB: UPDATE absences<br/>SET status = 'rejected'

        Note over AS: AUCUNE modification du solde

        AS->>NS: Notify employee<br/>Push + Email : "Absence refusée"<br/>avec motif du refus
        NS-->>AS: Notification envoyée

        AC-->>M: 200 Absence refusée

    else ANNULATION (par l'employé)

        E->>AC: PUT /absences/{id}/cancel
        AC->>AS: cancelAbsence(id)

        AS->>DB: SELECT status FROM absences<br/>WHERE id = :id
        DB-->>AS: status = 'pending'

        AS->>DB: UPDATE absences<br/>SET status = 'cancelled'
        DB-->>AS: OK

        Note over AS: Recréditer le solde uniquement<br/>si un décompte avait été effectué

        AS->>DB: UPDATE employees<br/>SET leave_balance =<br/>leave_balance + :days_count
        DB-->>AS: Solde recrédité

        AC-->>E: 200 Absence annulée
    end
```

---

## Explication des interactions

| Étape | Interaction | Détail |
|--------|-------------|---------|
| 1-3 | **Soumission de la demande** | L'employé soumet une demande d'absence via l'application mobile avec le type, les dates et un commentaire optionnel. |
| 4-5 | **Vérification du type & calcul des jours** | Le service vérifie si le type d'absence est rémunéré et s'il déduit du solde de congé. Le nombre de jours ouvrables est calculé en excluant les weekends et jours fériés. |
| 6 | **Contrôle du solde** | Si l'absence est rémunérée et que le solde est insuffisant, la demande est rejetée avec une erreur 422. |
| 7-8 | **Détection de chevauchement** | Le service vérifie qu'il n'existe pas d'absence en chevauchement (statut différent de `cancelled`) pour les mêmes dates. |
| 9-10 | **Création & notification** | L'absence est créée avec le statut `pending`. Les managers concernés reçoivent une notification push et email. |
| 11-12 | **Consultation par le manager** | Le manager consulte les demandes en attente depuis l'application mobile ou web. |
| 13-15 | **Approbation** | Le manager approuve la demande. Le statut passe à `approved`, le solde de congé est décrémenté et un log de consommation est créé. L'employé est notifié. |
| 16-18 | **Refus** | Le manager refuse la demande avec un motif. Le solde n'est pas modifié. L'employé est notifié avec la raison du refus. |
| 19-21 | **Annulation par l'employé** | L'employé peut annuler une demande en attente (`pending`). Si un décompte avait été effectué, le solde est recrédité automatiquement. |
