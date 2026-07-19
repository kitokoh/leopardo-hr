# Plan 64 - Cloture automatique, fuseaux horaires et GPS pointage

## Source

Points utilisateur 38, 39 et 40.

## Objectif

Rendre le pointage intelligent : cloture automatique des journees oubliees, stockage UTC avec affichage timezone locale, controle GPS doux et configurable.

## Lots d'execution

### Lot 64.1 - Timezone solide

- [x] Stockage UTC obligatoire.
- [x] Company timezone et employee/mobile timezone.
- [x] Les payloads API retournent UTC + timezone + formatted local si utile.
- [x] Mobile envoie timezone device sur attendance.

### Lot 64.2 - Cloture automatique journee

- [x] Settings entreprise : seuil, duree journee, marge overtime, auto-close active via `company.metadata.attendance_auto_close`.
- [x] Command/job planifie `attendance:auto-close`.
- [x] Si absence de checkout : creer checkout systeme, garder trace auditable et ouvrir fenetre correction via `punch_meta.auto_close.correction_window`.

### Lot 64.3 - GPS entreprise

- [x] Settings sites : latitude, longitude, rayon autorise.
- [x] Mobile check-in/out accepte position optionnelle dans le repository.
- [x] Backend calcule distance et expose `geofence.inside`, sans bloquer brutalement par defaut.

### Lot 64.4 - UX mobile

- [x] UX douce cote API : hors zone ne bloque pas le pointage.
- [x] Correction possible via le workflow existant.
- [ ] Permission GPS native et message manager automatique a livrer avec le lot mobile UX dedie, sans ajouter de plugin fragile dans ce lot backend/API.

## Tests

- [x] Unit distance geofence.
- [x] Feature timezone payloads.
- [x] Job auto-close.
- [x] Mobile repository route/payload.

## Criteres d'acceptation

- Les heures ne changent pas selon pays de maniere incoherente.
- Une journee oubliee est cloturee et auditable.
- GPS aide le controle sans casser l'UX.
