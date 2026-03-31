# Diagramme de se9quence — Demande et Approbation d'Absence

```mermaid
sequenceDiagram
    autonumber

    participant E as Employe9 (Mobile)
    participant M as Manager (Mobile/Web)
    participant AC as AbsenceController
    participant AS as AbsenceService
    participant DB as Employee DB
    participant LBL as leave_balance_logs
    participant NS as NotificationService

    E->>AC: POST /absences<br/>{absence_type_id, start_date, end_date, comment}

    Note over AC: Ve9rification RBAC

    AC->>AS: processAbsenceRequest(data)

    AS->>DB: SELECT * FROM absence_types<br/>WHERE id = :type_id
    DB-->>AS: {is_paid, deducts_leave}

    AS->>DB: SELECT * FROM holidays<br/>WHERE date BETWEEN :start AND :end
    AS->>AS: Calcul jours ouvrables<br/>(exclure weekends + jours fe9rie9s)
    DB-->>AS: days_count

    alt is_paid AND leave_balance < days_count
        AS-->>AC: 422 INSUFFICIENT_LEAVE_BALANCE
        AC-->>E: 422 Solde de conge9 insuffisant
    else Solde suffisant

        AS->>DB: SELECT * FROM absences<br/>WHERE employee_id = :id<br/>AND status != 'cancelled'<br/>AND dates OVERLAP
        DB-->>AS: existing absences

        alt Chevauchement detecte9
            AS-->>AC: 422 OVERLAP_WITH_EXISTING
            AC-->>E: 422 Chevauchement avec une absence existante
        else Pas de chevauchement

            AS->>DB: INSERT absences (status='pending')
            DB-->>AS: absence cre9e9e

            AS-->>AC: 201 absence record

            AS->>NS: Notify managers<br/>Push + Email : "Nouvelle demande de conge9"
            NS-->>AS: Notifications envoye9es

            AC-->>E: 201 Demande d'absence enregistre9e
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
        DB-->>AS: Solde mis e0 jour

        AS->>LBL: INSERT leave_balance_logs<br/>(type='consumption', amount, employee_id)
        LBL-->>AS: Log cre9e9

        AS->>NS: Notify employee<br/>Push + Email : "Absence approuve9e ✅"
        NS-->>AS: Notification envoye9e

        AC-->>M: 200 Absence approuve9e

    else REFUS

        M->>AC: PUT /absences/{id}/reject<br/>{comment}
        AC->>AS: rejectAbsence(id, comment)

        AS->>DB: UPDATE absences<br/>SET status = 'rejected'

        Note over AS: AUCUNE modification du solde

        AS->>NS: Notify employee<br/>Push + Email : "Absence refuse9e"<br/>avec motif du refus
        NS-->>AS: Notification envoye9e

        AC-->>M: 200 Absence refuse9e

    else ANNULATION (par l'employe9)

        E->>AC: PUT /absences/{id}/cancel
        AC->>AS: cancelAbsence(id)

        AS->>DB: SELECT status FROM absences<br/>WHERE id = :id
        DB-->>AS: status = 'pending'

        AS->>DB: UPDATE absences<br/>SET status = 'cancelled'
        DB-->>AS: OK

        Note over AS: Recre9diter le solde uniquement<br/>si un de9compte avait e9te9 effectue9

        AS->>DB: UPDATE employees<br/>SET leave_balance =<br/>leave_balance + :days_count
        DB-->>AS: Solde recr e9dite9

        AC-->>E: 200 Absence annule9e
    end
```

---

## Explication des interactions

| E9tape | Interaction | De9tail |
|--------|-------------|---------|
| 1-3 | **Soumission de la demande** | L'employe9 soumet une demande d'absence via l'application mobile avec le type, les dates et un commentaire optionnel. |
| 4-5 | **Ve9rification du type & calcul des jours** | Le service ve9rifie si le type d'absence est re9munre9 et s'il de9duit du solde de conge9. Le nombre de jours ouvrables est calcul en excluant les weekends et jours fe9rie9s. |
| 6 | **Contre9le du solde** | Si l'absence est re9munre9e et que le solde est insuffisant, la demande est rejete9e avec une erreur 422. |
| 7-8 | **De9tection de chevauchement** | Le service ve9rifie qu'il n'existe pas d'absence en chevauchement (statut diffe9rent de `cancelled`) pour les meames dates. |
| 9-10 | **Cre9ation & notification** | L'absence est cre9e9e avec le statut `pending`. Les manage9rs concern re9coivent une notification push et email. |
| 11-12 | **Consultation par le manage9r** | Le manage9r consulte les demandes en attente depuis l'application mobile ou web. |
| 13-15 | **Approbation** | Le manage9r approuve la demande. Le statut passe e0 `approved`, le solde de conge9 est de9cr 9ment e0 jour et un log de consommation est cre9e9. L'employe9 est notifie9. |
| 16-18 | **Refus** | Le manage9r refuse la demande avec un motif. Le solde n'est pas modifie9. L'employe9 est notifie9 avec la raison du refus. |
| 19-21 | **Annulation par l'employe9** | L'employe9 peut annuler une demande en attente (`pending`). Si un de9compte avait e9t effectu9, le solde est recr 9dit 9 automatiquement. |
