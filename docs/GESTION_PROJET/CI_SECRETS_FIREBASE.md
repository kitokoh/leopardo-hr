# Secrets CI — distribution Firebase (#8774 / chantier D-1)

Ce document liste les secrets **exigés** par les workflows de distribution Firebase,
leur rôle et le format attendu. Aucune valeur n'est stockée ici : les secrets vivent
uniquement dans **GitHub → Settings → Secrets and variables → Actions**.

## État constaté (2026-10-20, avant D-1)

Les quatre jobs `Distribute * to Firebase` échouaient avec :

```
Missing GitHub secret: FIREBASE_TOKEN
Missing GitHub secret: FIREBASE_APP_ID_SECRET_NAME
```

Ces échecs ne relevaient pas du code applicatif : les secrets ne sont pas
provisionnés sur ce dépôt. Ils sont **hors périmètre** du lot et ne peuvent pas
être créés par un agent (aucun accès aux secrets d'organisation).

## Comportement après D-1

`mobile-distribute.yml` **dégrade** au lieu d'échouer :

- si `FIREBASE_TOKEN` ou l'app id correspondant à l'app est absent → les étapes
  `Validate app Firebase config presence`, `Firebase App Distribution` et
  `Verify Firebase App Distribution release is visible` sont **sautées**
  (`FIREBASE_READY=false`) et un `::warning::` explicite est émis ;
- si les secrets **sont** présents, les contrôles restent **fail-closed** : l'app id
  doit correspondre au `mobilesdk_app_id` du `google-services.json` de l'app, sinon
  le job échoue (on ne publie jamais vers la mauvaise app Firebase).

`mobile-distribute-main.yml` appliquait déjà cette dégradation.

## Secrets à provisionner

| Nom | Rôle | Format attendu |
|---|---|---|
| `FIREBASE_TOKEN` | Jeton CLI Firebase utilisé par `wzieba/Firebase-Distribution-Github-Action` | Chaîne opaque produite par `firebase login:ci` |
| `FIREBASE_EMPLOYEE_ANDROID_APP_ID` | App id Firebase de `leopardo_employee` | `1:NNNNNNNNNNNN:android:XXXXXXXXXXXXXXXXXXXXXX` |
| `FIREBASE_MANAGER_ANDROID_APP_ID` | App id Firebase de `leopardo_manager` | idem |
| `FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID` | App id Firebase de `leopardo_platform_admin` | idem |
| `FIREBASE_APP_ID_HR` | App id Firebase de `leopardo_hr` | idem |
| `FIREBASE_APP_ID` | App id de repli si aucun des quatre ci-dessus | idem |
| `GOOGLE_SERVICES_JSON` | Contenu complet du fichier `google-services.json` (non committé, #1467) | JSON, une seule ligne |
| `FIREBASE_SERVICE_ACCOUNT_JSON` | Compte de service pour la relecture de release | JSON (optionnel) |
| `FIREBASE_READBACK_REQUIRED` | `true` pour rendre la relecture bloquante | `true` / `false` (optionnel) |

## Comment vérifier localement qu'une app id correspond

```bash
python3 - <<'EOF'
import json, os
cfg = json.load(open('<app>/android/app/google-services.json'))
pkg = '<com.leopardo.xxx>'
for c in cfg.get('client', []):
    info = c.get('client_info', {})
    if info.get('android_client_info', {}).get('package_name') == pkg:
        print(info.get('mobilesdk_app_id'))
EOF
```

Comparer la sortie au secret. Toute divergence doit bloquer la publication.

## Procédure de provisionnement (accès administrateur requis)

1. `firebase login:ci` → coller le jeton dans le secret `FIREBASE_TOKEN`.
2. Pour chaque app, récupérer l'`appId` Android depuis la console Firebase
   (Paramètres du projet → Vos applications) et le stocker dans le secret nommé
   ci-dessus.
3. Renseigner `GOOGLE_SERVICES_JSON` avec le contenu du fichier réel.
4. Relancer `Mobile Distribute` en `workflow_dispatch` sur l'app concernée et
   vérifier que l'étape `Firebase App Distribution (staging)` s'exécute et que
   `Verify Firebase App Distribution release is visible` passe.

## Ce qui reste bloqué

Tant que les secrets ne sont pas provisionnés, **aucune distribution Firebase ne
peut être validée de bout en bout**. Le build, l'analyse Flutter, les tests et la
production d'APK restent, eux, couverts et bloquants.
