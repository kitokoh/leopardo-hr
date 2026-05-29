# Plan 64 - Cloture automatique, fuseaux horaires et GPS pointage

## Source

Points utilisateur 38, 39 et 40.

## Objectif

Rendre le pointage intelligent : cloture automatique des journees oubliees, stockage UTC avec affichage timezone locale, controle GPS doux et configurable.

## Lots d'execution

### Lot 64.1 - Timezone solide

- Stockage UTC obligatoire.
- Company timezone et employee/mobile timezone.
- Les payloads API retournent UTC + timezone + formatted local si utile.
- Mobile envoie timezone device sur auth/me ou attendance.

### Lot 64.2 - Cloture automatique journee

- Settings entreprise : heure fin journee, marge overtime, auto-close active.
- Command/job planifie `attendance:auto-close`.
- Si absence de checkout : creer checkout systeme, notifier employe, ouvrir fenetre correction.

### Lot 64.3 - GPS entreprise

- Settings sites : latitude, longitude, rayon autorise.
- Mobile check-in/out envoie position si permission accordee.
- Backend calcule distance et marque `inside_geofence`, sans bloquer brutalement par defaut.

### Lot 64.4 - UX mobile

- Permission GPS expliquee simplement.
- Si hors zone : message bienveillant et notification manager si politique active.
- Correction possible.

## Tests

- Unit distance geofence.
- Feature timezone payloads.
- Job auto-close.
- Mobile repository route/payload.

## Criteres d'acceptation

- Les heures ne changent pas selon pays de maniere incoherente.
- Une journee oubliee est cloturee et auditable.
- GPS aide le controle sans casser l'UX.
