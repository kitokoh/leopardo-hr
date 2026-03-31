# Diagramme de se9quence — Pointage Check-in / Check-out

```mermaid
sequenceDiagram
    autonumber

    participant E as Employe9 (Mobile)
    participant ATC as AttendanceController
    participant GPS as GpsValidationService
    participant AS as AttendanceService
    participant PG as PostgreSQL (tenant)
    participant HC as Holiday Calendar
    participant NS as NotificationService

    Note over E: === CHECK-IN ===

    E->>ATC: POST /attendance/check-in<br/>{gps_lat, gps_lng, method: 'mobile'}

    Note over ATC: Ve9rification ManagerMiddleware

    ATC->>GPS: validateLocation(employee_id, lat, lng)

    GPS->>PG: SELECT site_id, gps_lat, gps_lng,<br/>gps_radius_meters FROM employees<br/>JOIN work_sites WHERE id = :id
    PG-->>GPS: {site_lat, site_lng, radius}

    GPS->>GPS: Haversine distance(lat, lng,<br/>site_lat, site_lng)

    alt distance > gps_radius_meters
        GPS-->>ATC: validation failed
        ATC-->>E: 422 GPS_OUTSIDE_ZONE
    else Dans la zone ge9ographique
        GPS-->>ATC: validation OK

        ATC->>AS: processCheckIn(employee_id, data)

        AS->>PG: SELECT * FROM schedules<br/>WHERE employee_id = :id<br/>AND day = TODAY()
        PG-->>AS: schedule record

        alt Planning introuvable
            AS-->>ATC: 422 MISSING_SCHEDULE
            ATC-->>E: 422 Planning manquant
        else Planning trouve9

            AS->>PG: SELECT * FROM attendance_logs<br/>WHERE date = TODAY()<br/>AND employee_id = :id<br/>AND session_number = 1
            PG-->>AS: existing records

            alt Record EXISTS (de9ja9 pointe9)
                AS-->>ATC: 409 ALREADY_CHECKED_IN
                ATC-->>E: 409 De9ja9 pointe9
            else Aucun pointage

                AS->>HC: SELECT * FROM holidays<br/>WHERE date = TODAY()<br/>AND company_id = :id
                HC-->>AS: holiday record ou NULL

                alt Jour fe9rie9
                    AS-->>ATC: 200 {data: null, context: {is_holiday: true}}
                    ATC-->>E: 200 Jour fe9rie9 (pas de pointage)
                else Jour ouvrable

                    AS->>PG: INSERT INTO attendance_logs<br/>(check_in = NOW() UTC,<br/>method = 'mobile',<br/>gps_lat, gps_lng,<br/>employee_id, date,<br/>session_number = 1)
                    PG-->>AS: attendance_log cre9e9

                    AS->>AS: De9terminer le statut :<br/>check_in (convertis en TZ entreprise)<br/>vs schedule.start_time + late_tolerance

                    alt Retard de9tecte9
                        AS->>PG: UPDATE attendance_logs<br/>SET status = 'late'
                        AS->>NS: Notify manager<br/>"Retard de9tecte9"
                        NS-->>AS: Notification envoye9e
                    else A l'heure
                        AS->>PG: UPDATE attendance_logs<br/>SET status = 'on_time'
                    end

                    AS-->>ATC: attendance_log data
                    ATC-->>E: 200 Pointage enregistre9
                end
            end
        end
    end

    Note over E: === CHECK-OUT ===

    E->>ATC: POST /attendance/check-out

    ATC->>AS: processCheckOut(employee_id)

    AS->>PG: SELECT * FROM attendance_logs<br/>WHERE date = TODAY()<br/>AND employee_id = :id<br/>AND session_number = 1
    PG-->>AS: attendance_log

    alt Aucun check-in trouve9
        AS-->>ATC: 422 MISSING_CHECK_IN
        ATC-->>E: 422 Aucun check-in pour aujourd'hui
    else Check-in existe

        AS->>PG: UPDATE attendance_logs<br/>SET check_out = NOW() UTC<br/>WHERE id = :log_id
        PG-->>AS: check_out enregistre9

        AS->>AS: heures_travaille9es =<br/>(check_out - check_in)<br/>- pause_de9jeuner_minutes

        alt heures_travaille9es > seuil_heures_sup
            AS->>AS: Calcul heures supple9mentaires<br/>= heures_travaille9es - seuil
            AS->>PG: UPDATE attendance_logs<br/>SET overtime_minutes = :overtime,<br/>hours_worked = :total,<br/>status = 'overtime'
            PG-->>AS: Mise e0 jour OK
        else Heures normales
            AS->>PG: UPDATE attendance_logs<br/>SET hours_worked = :total,<br/>status = 'completed'
            PG-->>AS: Mise e0 jour OK
        end

        AS-->>ATC: updated attendance_log
        ATC-->>E: 200 Check-out enregistre9<br/>{hours_worked, overtime, status}
    end
```

---

## Explication des interactions

| E9tape | Interaction | De9tail |
|--------|-------------|---------|
| 1-5 | **Validation ge9olocalisation** | L'employe9 envoie ses coordonne9es GPS. Le service calcule la distance de Haversine entre sa position et le site de travail assigne9. Si la distance de9passe le rayon autorise9, le pointage est rejete9 (422). |
| 6-7 | **Ve9rification du planning** | Le service ve9rifie qu'un planning horaire existe pour le jour en cours. Sans planning, le pointage est impossible. |
| 8 | **Double pointage** | Une recherche dans `attendance_logs` ve9rifie qu'il n'existe pas de9ja un check-in pour la journe9e et la session (session_number = 1). |
| 9-10 | **Jour fe9rie9** | Le calendrier des jours fe9rie9s de l'entreprise est consulte9. Si c'est un jour fe9rie9, le pointage n'est pas enregistre9 et le contexte est retourne9 e0 l'application. |
| 11-13 | **Enregistrement du check-in** | Le pointage est insere9 en base avec l'heure UTC. Le statut (`on_time` ou `late`) est de9termine9 en comparant l'heure convertie dans le fuseau de l'entreprise avec l'heure de de9but du planning + la tole9rance de retard. |
| 14 | **Notification de retard** | Si un retard est de9tecte9, le manage9r rec00oit une notification push imme9diatement. |
| 15-17 | **Recherche du check-in** | Au check-out, le service recherche le pointage d'entre9e du jour. S'il n'existe pas, le check-out est rejete9 (422). |
| 18-20 | **Calcul du temps travaille9** | Les heures travaille9es sont calcule9es en soustrayant la pause de9jeuner. Si le total de9passe le seuil d'heures supple9mentaires, les heures sup sont calcule9es et stocke9es se9pare9ment. |
| 21-22 | **Mise e0 jour & re9ponse** | Le statut final est mis e0 jour (`completed` ou `overtime`) et l'employe9 rec00oit le de9tail de son pointage. |
