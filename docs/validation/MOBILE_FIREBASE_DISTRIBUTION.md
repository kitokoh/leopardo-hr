# Mobile Firebase Distribution

Date : 2026-05-26

## Decision

Les apps mobiles sont maintenant separees comme des produits distincts :

| App | Android package | iOS bundle | Firebase Android secret attendu |
|---|---|---|---|
| Employee | `com.leopardo.employee` | `com.leopardo.employee` | `FIREBASE_EMPLOYEE_ANDROID_APP_ID` |
| Manager | `com.leopardo.manager` | `com.leopardo.manager` | `FIREBASE_MANAGER_ANDROID_APP_ID` |
| Platform Admin | `com.leopardo.platformadmin` | `com.leopardo.platformadmin` | `FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID` |

Le secret commun `FIREBASE_TOKEN` reste requis pour l'upload Firebase App Distribution. Le secret `FIREBASE_SERVICE_ACCOUNT_JSON` est recommande pour rendre le readback strict via service account.

## Fichiers Firebase attendus

Les fichiers natifs doivent correspondre exactement aux IDs ci-dessus :

- `front/mobile_apps/leopardo_employee/android/app/google-services.json`
- `front/mobile_apps/leopardo_employee/ios/Runner/GoogleService-Info.plist`
- `front/mobile_apps/leopardo_manager/android/app/google-services.json`
- `front/mobile_apps/leopardo_manager/ios/Runner/GoogleService-Info.plist`
- `front/mobile_apps/leopardo_platform_admin/android/app/google-services.json`
- `front/mobile_apps/leopardo_platform_admin/ios/Runner/GoogleService-Info.plist`

Le script d'installation refuse les fichiers qui ne correspondent pas :

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\install-mobile-firebase-configs.ps1
```

## Etat des fichiers recus le 2026-05-26

Premier lot refuse :

- Android : `com.leopardo.emplyer` detecte, attendu `com.leopardo.employee` ou `com.leopardo.manager`.
- iOS employee : `com.leopardo.employer` detecte, attendu `com.leopardo.employee`.
- iOS manager : `com.leopardo.manage` detecte, attendu `com.leopardo.manager`.

Second lot installe :

- Employee Android : `google-services (3).json`, package `com.leopardo.employee` detecte.
- Manager Android : `google-services (4).json`, package `com.leopardo.manager` detecte.
- Employee iOS : `GoogleService-Info (3).plist`, bundle `com.leopardo.employee` detecte.
- Manager iOS : `GoogleService-Info (2).plist`, bundle `com.leopardo.manager` detecte.

Troisieme lot installe :

- Platform Admin Android : `google-services (5).json`, package `com.leopardo.platformadmin` detecte.
- Platform Admin iOS : `GoogleService-Info (4).plist`, bundle `com.leopardo.platformadmin` detecte.

Note Android : les exports Firebase peuvent contenir plusieurs clients dans un meme `google-services.json`. Le script choisit le fichier le plus specifique disponible pour chaque app, mais Gradle selectionne le client correspondant a `applicationId`. Toute cle API associee a un client historique doit rester restreinte cote Google Cloud/Firebase.

## Distribution CI

`Deploy - Leopardo RH` distribue maintenant les trois APK staging :

- `leopardo_employee` vers `FIREBASE_EMPLOYEE_ANDROID_APP_ID`
- `leopardo_manager` vers `FIREBASE_MANAGER_ANDROID_APP_ID`
- `leopardo_platform_admin` vers `FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID`

Le workflow `Mobile - Build and Firebase Distribution` est declenche automatiquement sur `main` quand `front/mobile_apps/**` change. Il peut aussi etre lance manuellement pour `employee`, `manager`, `platform_admin` ou `both`.

Sur le deploy `main`, la distribution d'une app est sautee proprement tant que son secret ou son `google-services.json` manque. Cela evite de bloquer le deploy API/web pendant la preparation Firebase.

Le workflow manuel `Mobile - Build and Firebase Distribution` est plus strict : il echoue si l'app demandee n'a pas son secret Firebase ou son fichier Android natif. Cette difference est volontaire pour eviter les faux verts lors d'une release mobile explicite.

Depuis v4.16.155, tous les inputs `workflow_dispatch` ont un type explicite. GitHub Actions renvoyait une erreur de schema lors du dispatch du workflow multi-app quand `release_notes` n'avait pas de type declare.

Depuis v4.16.149, les deux workflows relisent Firebase apres l'upload avec :

```bash
firebase appdistribution:releases:list --app <firebase-app-id> --limit 10 --json
```

Le job echoue si le `buildVersion` du build courant n'apparait pas dans App Distribution. Cela evite les faux positifs ou un upload semble vert cote GitHub mais reste invisible cote Firebase.

Derniere verification connue :

- Employee Android : release `main-1568 (1568)` visible dans `leopardo-rh` sous `android:com.leopardo.employee`.
- Manager Android : release `main-1568 (1568)` visible dans `leopardo-rh` sous `android:com.leopardo.manager`.

Important : Firebase App Distribution affiche les releases par app. Dans la console, selectionner le projet `leopardo-rh`, puis App Distribution, puis l'app Android `com.leopardo.employee`, `com.leopardo.manager` ou `com.leopardo.platformadmin`. Les fichiers iOS sont installes dans le depot, mais la distribution iOS necessitera un workflow macOS signe produisant un `.ipa`.

Les apps iOS ne peuvent pas recevoir de release App Distribution tant qu'un `.ipa` signe n'est pas produit. Il faudra ajouter les secrets Apple (`APP_STORE_CONNECT_API_KEY`, `APP_STORE_CONNECT_KEY_ID`, `APP_STORE_CONNECT_ISSUER_ID`, certificat/profil ou match equivalent) avant de rendre la distribution iOS automatique.

## Secret `FIREBASE_SERVICE_ACCOUNT_JSON`

`FIREBASE_SERVICE_ACCOUNT_JSON` est un secret GitHub optionnel qui contient le JSON complet d'une cle de compte de service Google/Firebase. Il ne remplace pas encore `FIREBASE_TOKEN` pour l'upload, mais il permet d'executer la verification readback avec une authentification service account apres l'upload.

Configuration recommandee :

1. Ouvrir Google Cloud Console pour le projet Firebase `leopardo-rh`.
2. Aller dans IAM & Admin > Service Accounts.
3. Creer un compte de service dedie, par exemple `github-mobile-appdistribution-readback`.
4. Lui attribuer le role minimal disponible pour App Distribution. En pratique, utiliser `Firebase App Distribution Admin` si le role viewer/listing seul ne suffit pas dans la console.
5. Creer une cle JSON pour ce compte de service.
6. Copier tout le contenu du fichier JSON.
7. Dans GitHub : repository `kitokoh/gestionemployerBackend` > Settings > Secrets and variables > Actions > New repository secret.
8. Nom du secret : `FIREBASE_SERVICE_ACCOUNT_JSON`.
9. Valeur : coller le JSON complet sur une seule valeur de secret, sans le transformer.

Quand ce secret existe, les workflows ecrivent temporairement ce JSON dans `RUNNER_TEMP`, exportent `GOOGLE_APPLICATION_CREDENTIALS`, puis executent `firebase appdistribution:releases:list`. Par defaut, si l'upload Firebase App Distribution a deja reussi mais que la lecture echoue, le workflow reste vert avec un warning : l'upload est la source de verite operationnelle pour ne pas bloquer les testeurs a cause d'un compte de service mal permissionne.

Pour rendre cette verification strictement bloquante, ajouter aussi le secret GitHub `FIREBASE_READBACK_REQUIRED` avec la valeur `true`. Ne l'activer qu'apres avoir confirme que le compte de service a ete regenere, non expose publiquement et autorise a lister les releases App Distribution du projet `leopardo-rh`.

Important : toute cle `FIREBASE_SERVICE_ACCOUNT_JSON` exposee dans un chat, ticket, log ou document public doit etre consideree compromise. La revoquer dans Google Cloud, creer une nouvelle cle JSON, puis remplacer le secret GitHub.

## Securite

Les fichiers Firebase mobile ne sont pas des secrets forts, mais leurs API keys doivent etre restreintes dans Google Cloud/Firebase :

- restriction par package Android + SHA-1/SHA-256 ;
- restriction par bundle iOS ;
- App Check a activer avant lancement public ;
- groupes de testeurs limites dans Firebase App Distribution.
