# Mobile Store Readiness

Date : 2026-05-26

## Applications

| App | Android applicationId | iOS bundle id | Nom visible |
|---|---|---|---|
| Employee | `com.leopardo.employee` | `com.leopardo.employee` | Leopardo Employee |
| Manager/RH | `com.leopardo.manager` | `com.leopardo.manager` | Leopardo Manager |

## Validation automatique

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-apps-split.ps1
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-release-readiness.ps1
```

Mode strict avant upload public :

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-release-readiness.ps1 -StrictStores
```

Le mode strict doit rester bloquant tant que les signatures release Android/iOS ne sont pas configurees.

## Checklist boutons et workflows

### Leopardo Employee

- Connexion : login demo, login manuel, mauvais mot de passe, logout.
- Accueil : actions rapides vers pointage, absences, avances, paie.
- Pointage : check-in, check-out, correction, menu profil, mois complet.
- Absences : demande, annulation si attente, refresh, message d'erreur API.
- Avances : demande, annulation si attente, refresh, message d'erreur API.
- Paie : liste bulletins, ouverture PDF si disponible.
- Notifications : liste, marquer lu, tout marquer lu, token push.
- Compte : changement langue, mot de passe, biometrie si disponible, logout visible.

### Leopardo Manager

- Connexion : login manager/RH, mauvais mot de passe, logout.
- Accueil : actions rapides et modules manager.
- Equipe : liste, ajout employe, invitation, refresh.
- Pointage : check-in/check-out personnel, correction, mois complet.
- Absences : demande personnelle, annulation, approbation/refus equipe.
- Avances : demande personnelle, annulation, approbation/refus equipe.
- Approbations : liste pending, approuver, refuser.
- Notifications : liste, lecture, read-all, push token.
- Routes manager preparees : dashboard, presences, anomalies, corrections.

## Criteres no-go

- Deux apps ne peuvent pas coexister sur le meme appareil.
- Un bouton critique ne fait rien.
- Un bouton critique affiche seulement un toast decoratif alors qu'une API existe.
- Un spinner reste actif apres timeout reseau.
- Une action manager apparait dans `Leopardo Employee`.
- Une route employee ouvre une page inexistante.
- Une action store utilise une signature debug en release.
