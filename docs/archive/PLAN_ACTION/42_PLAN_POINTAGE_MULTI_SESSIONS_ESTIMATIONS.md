# Plan 42 - Estimations multi-sessions pointage

## Objectif

Fermer le dernier ecart fonctionnel du pointage multi-evenements : les API et dashboards ne doivent plus calculer les gains, heures et resumes sur la seule premiere session de la journee.

## Probleme corrige

Le pointage multi-session etait supporte par `AttendanceService`, mais plusieurs resumes utilisaient encore `session_number = 1`. Cela pouvait produire :

- une estimation journaliere incomplete ;
- un mois complet sous-estime ;
- un dashboard manager qui ignore les heures supplementaires, missions ou reprises ;
- un historique web employe qui masque les sessions 2+.

## Livrables

- `EstimationService::dailySummary()` agrege toutes les sessions du jour.
- `EstimationService::quickEstimate()` groupe les pointages par date et calcule une journee complete.
- `AttendanceTodayResource` expose `sessions_count`, heures, heures supp et gains agregees.
- `/api/v1/me/daily-summary` selectionne la session ouverte ou la plus recente, sans filtre dur sur `session_number = 1`.
- Dashboards web manager/employe agregent les sessions par jour.
- Test de regression sur pointage normal + heure supplementaire + resume mensuel.

## Regle

Ne pas remettre de filtre dur `session_number = 1` dans les services ou endpoints de resume. Les ecrans peuvent afficher la session courante, mais les totaux doivent toujours representer la journee complete.
