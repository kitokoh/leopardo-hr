# Diagramme de séquence — Pointage Check-in / Check-out

```mermaid
sequenceDiagram
    autonumber

    participant E as Employé (Mobile)
    participant ATC as AttendanceController
    participant GPS as GpsValidationService
    participant AS as AttendanceService
    participant PG as PostgreSQL (tenant)
    participant HC as Holiday Calendar
    participant NS as NotificationService

    Note over E: === CHECK-IN ===

    E->>ATC: POST /attendance/check-in<br/>{gps_lat, gps_lng, method: 'mobile'}

    Note over ATC: Vérification ManagerMiddleware

    ATC->>GPS: validateLocation(employee_id, lat, lng)

    GPS->>PG: SELECT site_id, gps_lat, gps_lng,<br/>gps_radius_meters FROM employees<br/>JOIN work_sites WHERE id = :id
    PG-->>GPS: {site_lat, site_lng, radius}

    GPS->>GPS: Haversine distance(lat, lng,<br/>site_lat, site_lng)

    alt distance > gps_radius_meters
        GPS-->>ATC: validation failed
        ATC-->>E: 422 GPS_OUTSIDE_ZONE
    else Dans la zone géographique
        GPS-->>ATC: validation OK

        ATC->>AS: processCheckIn(employee_id, data)

        AS->>PG: SELECT * FROM schedules<br/>WHERE employee_id = :id<br/>AND day = TODAY()
        PG-->>AS: schedule record

        alt Planning introuvable
            AS-->>ATC: 422 MISSING_SCHEDULE
            ATC-->>E: 422 Planning manquant
        else Planning trouvé

            AS->>PG: SELECT * FROM attendance_logs<br/>WHERE date = TODAY()<br/>AND employee_id = :id<br/>AND session_number = 1
            PG-->>AS: existing records

            alt Record EXISTS (déjà pointé)
                AS-->>ATC: 409 ALREADY_CHECKED_IN
                ATC-->>E: 409 Déjà pointé
            else Aucun pointage

                AS->>HC: SELECT * FROM holidays<br/>WHERE date = TODAY()<br/>AND company_id = :id
                HC-->>AS: holiday record ou NULL

                alt Jour férié
                    AS-->>ATC: 200 {data: null, context: {is_holiday: true}}
                    ATC-->>E: 200 Jour férié (pas de pointage)
                else Jour ouvrable

                    AS->>PG: INSERT INTO attendance_logs<br/>(check_in = NOW() UTC,<br/>method = 'mobile',<br/>gps_lat, gps_lng,<br/>employee_id, date,<br/>session_number = 1)
                    PG-->>AS: attendance_log créé

                    AS->>AS: Déterminer le statut :<br/>check_in (convertis en TZ entreprise)<br/>vs schedule.start_time + late_tolerance

                    alt Retard détecté
                        AS->>PG: UPDATE attendance_logs<br/>SET status = 'late'
                        AS->>NS: Notify manager<br/>"Retard détecté"
                        NS-->>AS: Notification envoyée
                    else A l'heure
                        AS->>PG: UPDATE attendance_logs<br/>SET status = 'on_time'
                    end

                    AS-->>ATC: attendance_log data
                    ATC-->>E: 200 Pointage enregistré
                end
            end
        end
    end

    Note over E: === CHECK-OUT ===

    E->>ATC: POST /attendance/check-out

    ATC->>AS: processCheckOut(employee_id)

    AS->>PG: SELECT * FROM attendance_logs<br/>WHERE date = TODAY()<br/>AND employee_id = :id<br/>AND session_number = 1
    PG-->>AS: attendance_log

    alt Aucun check-in trouvé
        AS-->>ATC: 422 MISSING_CHECK_IN
        ATC-->>E: 422 Aucun check-in pour aujourd'hui
    else Check-in existe

        AS->>PG: UPDATE attendance_logs<br/>SET check_out = NOW() UTC<br/>WHERE id = :log_id
        PG-->>AS: check_out enregistré

        AS->>AS: heures_travaillées =<br/>(check_out - check_in)<br/>- pause_déjeuner_minutes

        alt heures_travaillées > seuil_heures_sup
            AS->>AS: Calcul heures supplémentaires<br/>= heures_travaillées - seuil
            AS->>PG: UPDATE attendance_logs<br/>SET overtime_minutes = :overtime,<br/>hours_worked = :total,<br/>status = 'overtime'
            PG-->>AS: Mise à jour OK
        else Heures normales
            AS->>PG: UPDATE attendance_logs<br/>SET hours_worked = :total,<br/>status = 'completed'
            PG-->>AS: Mise à jour OK
        end

        AS-->>ATC: updated attendance_log
        ATC-->>E: 200 Check-out enregistré<br/>{hours_worked, overtime, status}
    end
```

---

## Explication des interactions

| Étape | Interaction | Détail |
|--------|-------------|---------|
| 1-5 | **Validation géolocalisation** | L'employé envoie ses coordonnées GPS. Le service calcule la distance de Haversine entre sa position et le site de travail assigné. Si la distance dépasse le rayon autorisé, le pointage est rejeté (422). |
| 6-7 | **Vérification du planning** | Le service vérifie qu'un planning horaire existe pour le jour en cours. Sans planning, le pointage est impossible. |
| 8 | **Double pointage** | Une recherche dans `attendance_logs` vérifie qu'il n'existe pas déjà un check-in pour la journée et la session (session_number = 1). |
| 9-10 | **Jour férié** | Le calendrier des jours fériés de l'entreprise est consulté. Si c'est un jour férié, le pointage n'est pas enregistré et le contexte est retourné à l'application. |
| 11-13 | **Enregistrement du check-in** | Le pointage est inséré en base avec l'heure UTC. Le statut (`on_time` ou `late`) est déterminé en comparant l'heure convertie dans le fuseau de l'entreprise avec l'heure de début du planning + la tolérance de retard. |
| 14 | **Notification de retard** | Si un retard est détecté, le manager reçoit une notification push immédiatement. |
| 15-17 | **Recherche du check-in** | Au check-out, le service recherche le pointage d'entrée du jour. S'il n'existe pas, le check-out est rejeté (422). |
| 18-20 | **Calcul du temps travaillé** | Les heures travaillées sont calculées en soustrayant la pause déjeuner. Si le total dépasse le seuil d'heures supplémentaires, les heures sup sont calculées et stockées séparément. |
| 21-22 | **Mise à jour & réponse** | Le statut final est mis à jour (`completed` ou `overtime`) et l'employé reçoit le détail de son pointage. |
