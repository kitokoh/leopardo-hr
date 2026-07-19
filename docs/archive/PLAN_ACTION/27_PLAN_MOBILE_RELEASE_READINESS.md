# Plan 27 - Mobile release readiness App Store / Play Store

Date : 2026-05-26
Statut : en execution iterative

## Objectif

Rendre les applications mobiles `leopardo_employee` et `leopardo_manager` pretes pour une publication App Store / Play Store, avec une verification explicite que les boutons et workflows prevus ne sont pas decoratifs.

Le Plan 27 ne remplace pas les tests humains sur appareil. Il met en place les controles techniques et la matrice QA qui permettent de verifier systematiquement :

- identites natives distinctes pour les stores ;
- routes critiques presentes ;
- boutons critiques relies a une navigation, une API ou un feedback clair ;
- endpoints backend reels appeles pour les workflows metier ;
- builds debug CI verts avant de passer aux builds release signes ;
- checklist store claire avant soumission.

## Diagnostic initial

La separation multi-app est saine, mais les apps avaient encore une identite native heritee :

- meme `applicationId` Android ;
- meme bundle identifier iOS ;
- meme label visible ;
- absence de garde release dedie.

Sans correction, les deux apps ne peuvent pas etre publiees proprement comme deux produits mobiles distincts.

## Lot 27.1 - Identites store et garde release

Livrables :

- `leopardo_employee` :
  - Android namespace/applicationId : `com.leopardo.employee` ;
  - Android label : `Leopardo Employee` ;
  - iOS bundle identifier : `com.leopardo.employee` ;
  - iOS display name : `Leopardo Employee`.
- `leopardo_manager` :
  - Android namespace/applicationId : `com.leopardo.manager` ;
  - Android label : `Leopardo Manager` ;
  - iOS bundle identifier : `com.leopardo.manager` ;
  - iOS display name : `Leopardo Manager`.
- Script `dev-hub/tools/validate-mobile-release-readiness.ps1`.
- Execution du garde dans `mobile-apps-ci.yml`.

## Lot 27.2 - Matrice boutons et workflows

Chaque workflow visible doit etre classe :

| App | Workflow | Boutons/actions attendus | Backend attendu |
|---|---|---|---|
| Employee | Connexion | demo user, login, logout | `/auth/login`, `/auth/me`, `/auth/logout` |
| Employee | Pointage | check-in, check-out, correction | `/attendance/check-in`, `/attendance/check-out`, `/attendance/corrections` |
| Employee | Absences | demander, annuler attente, rafraichir | `/absences`, `/me/leave-balances` |
| Employee | Avances | demander, annuler attente, rafraichir | `/salary-advances` |
| Employee | Paie | lister bulletins, ouvrir PDF | `/me/pay-slips` |
| Employee | Notifications | liste, read, read-all, push token | `/notifications`, `/device-tokens` |
| Manager | Equipe | liste, ajout, invitation | `/employees` |
| Manager | Decisions | approuver/refuser absences et avances | `/absences/*/approve|reject`, `/salary-advances/*/approve|reject` |
| Manager | Approbations | liste pending, decision | `/approvals/pending`, `/approvals/*` |
| Manager | Dashboard manager | routes preparees | `/manager/dashboard`, `/manager/attendance`, `/manager/anomalies`, `/manager/corrections` |

Livrables techniques ajoutes :

- contrat canonique `dev-hub/tools/mobile-workflow-contracts.json` ;
- garde `dev-hub/tools/validate-mobile-workflow-contracts.ps1` ;
- execution du garde dans `Mobile Apps CI - Flutter` ;
- verification que les routes servies par `MobileExperienceService` existent dans chaque app ;
- verification des navigations statiques `context.push/go(...)` vers des routes declarees ;
- correction du lien espace personnel vers la route reelle `/company-request`.

## Lot 27.3 - QA appareil avant stores

Avant soumission :

1. Installer les deux APK sur le meme appareil : elles doivent coexister.
2. Login employe demo dans `Leopardo Employee`.
3. Login manager/RH demo dans `Leopardo Manager`.
4. Tester chaque bouton de la matrice avec API Render.
5. Verifier absence de spinner infini.
6. Verifier messages d'erreur reseau clairs.
7. Verifier logout et relogin.
8. Verifier push token si Firebase est configure.
9. Verifier affichage petit ecran et mode sombre.
10. Produire captures store et video courte demo.

## Lot 27.4 - Builds release signes

Les builds debug CI prouvent la compilabilite. Pour upload store, il reste a fournir les secrets :

- Android keystore release par app ;
- `key.properties` ou secrets CI equivalents ;
- Apple Team ID, bundle IDs crees dans App Store Connect ;
- provisioning profiles et certificats ;
- Firebase / Google services par app si push active.

La commande stricte :

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-release-readiness.ps1 -StrictStores
```

doit passer avant un upload public store. Tant que la signature release n'est pas configuree, ce mode strict doit rester rouge.

## Lot 27.5 - Firebase App Distribution multi-app

La distribution Firebase historique ciblait l'ancienne app mobile unique. Elle doit maintenant cibler les deux apps separees :

- `leopardo_employee` distribuee vers `FIREBASE_EMPLOYEE_ANDROID_APP_ID` ;
- `leopardo_manager` distribuee vers `FIREBASE_MANAGER_ANDROID_APP_ID` ;
- `FIREBASE_TOKEN` reste le token commun de distribution ;
- `Deploy - Leopardo RH` saute proprement la distribution d'une app tant que son secret ou son `google-services.json` est absent, afin de ne pas bloquer le deploy API/web ;
- `Mobile - Build and Firebase Distribution` reste strict en manuel et echoue si les secrets/configs de l'app demandee manquent.

Le script `dev-hub/tools/install-mobile-firebase-configs.ps1` installe les fichiers telecharges uniquement si leurs IDs correspondent exactement a `com.leopardo.employee` et `com.leopardo.manager`.

Les fichiers recus le 2026-05-26 ne correspondent pas encore aux IDs stabilises :

- Android : `com.leopardo.emplyer` ;
- iOS : `com.leopardo.employer` et `com.leopardo.manage`.

Ils doivent etre recrees dans Firebase avant d'etre poses dans les apps.

## Definition of done

- Garde release passe en mode non strict.
- Garde workflow mobile passe en CI.
- Distribution Firebase employee/manager configuree avec secrets separes.
- CI Mobile Apps verte.
- Deux identites stores distinctes.
- Matrice workflows documentee.
- Aucun handler vide sur les apps mobiles.
- Plan de QA appareil disponible.
- Les builds debug employee/manager restent verts.
