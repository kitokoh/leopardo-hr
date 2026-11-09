# Déploiement — Caisse tablette Retail (`leopardo_pos_retail`)

Application Flutter **native offline-first** pour la caisse d'une boutique
(retail). Elle fonctionne sans réseau : le catalogue est mis en cache, les
ventes sont mises en file et rejouées automatiquement.

- **Chemin** : `front/mobile_apps/leopardo_pos_retail`
- **Paquet Android** : `app.leopardo.pos_retail`
- **Bundle iOS** : `app.leopardo.posRetail`
- **Dépend de** : `leopardo_core` (chemin relatif `../leopardo_core`)

---

## 1. Prérequis

| Outil | Version |
|---|---|
| Flutter | 3.47.x (canal stable) |
| Dart | 3.9.x |
| Android SDK | API 34+ (compileSdk 34) |
| Xcode (iOS) | 15+ |

```bash
cd front/mobile_apps/leopardo_pos_retail
flutter pub get
```

---

## 2. Configuration du backend

L'application lit deux valeurs au démarrage :

| Variable | Rôle | Où la définir |
|---|---|---|
| `API_BASE_URL` | Racine de l'API (`https://api.exemple.com`) | `--dart-define` |
| `LOCATION_ID` | Identifiant de l'emplacement (boutique) de **cette** tablette | `--dart-define` |

```bash
flutter run \
  --dart-define=API_BASE_URL=https://api.staging.exemple.com \
  --dart-define=LOCATION_ID=42
```

> **Une tablette = un emplacement.** `LOCATION_ID` détermine le catalogue
> chargé et la session de caisse ouverte. Deux tablettes de la même boutique
> partagent le même `LOCATION_ID` ; deux boutiques ne le partagent jamais.

Pour un build de production, les valeurs sont figées dans le binaire : elles
ne peuvent plus être changées sans reconstruire. Si une tablette doit changer
de boutique, prévoyez un écran de configuration ou un rebuild.

---

## 3. Build Android

### Debug (test sur tablette)

```bash
flutter build apk --debug \
  --dart-define=API_BASE_URL=https://api.staging.exemple.com \
  --dart-define=LOCATION_ID=42

adb install -r build/app/outputs/flutter-apk/app-debug.apk
```

### Release (signé)

1. **Créer un keystore** (une seule fois, à conserver hors du dépôt) :

```bash
keytool -genkey -v -keystore ~/leopardo-pos.jks \
  -keyalg RSA -keysize 2048 -validity 10000 \
  -alias leopardo_pos
```

2. **`android/key.properties`** (jamais committé) :

```properties
storePassword=<mot de passe du keystore>
keyPassword=<mot de passe de la clé>
keyAlias=leopardo_pos
storeFile=/chemin/absolu/vers/leopardo-pos.jks
```

3. **`android/app/build.gradle`** — brancher la signature :

```gradle
def keystoreProperties = new Properties()
def keystorePropertiesFile = rootProject.file('key.properties')
if (keystorePropertiesFile.exists()) {
    keystoreProperties.load(new FileInputStream(keystorePropertiesFile))
}

android {
    signingConfigs {
        release {
            keyAlias keystoreProperties['keyAlias']
            keyPassword keystoreProperties['keyPassword']
            storeFile file(keystoreProperties['storeFile'])
            storePassword keystoreProperties['storePassword']
        }
    }
    buildTypes {
        release { signingConfig signingConfigs.release }
    }
}
```

4. **Construire** :

```bash
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://api.exemple.com \
  --dart-define=LOCATION_ID=42
```

Sortie : `build/app/outputs/bundle/release/app-release.aab`.

### En CI

Les secrets ne doivent **jamais** être dans le dépôt. Injectez-les :

```bash
echo "$ANDROID_KEYSTORE_BASE64" | base64 -d > android/app/leopardo-pos.jks
cat > android/key.properties <<EOF
storePassword=$ANDROID_STORE_PASSWORD
keyPassword=$ANDROID_KEY_PASSWORD
keyAlias=$ANDROID_KEY_ALIAS
storeFile=leopardo-pos.jks
EOF
```

---

## 4. Build iOS

```bash
cd ios && pod install && cd ..
flutter build ipa --release \
  --dart-define=API_BASE_URL=https://api.exemple.com \
  --dart-define=LOCATION_ID=42
```

Signature : ouvrez `ios/Runner.xcworkspace` dans Xcode, onglet
**Signing & Capabilities**, sélectionnez votre équipe et un profil de
distribution. Pour la CI, utilisez un profil et un certificat stockés en
secrets, puis `xcodebuild -exportArchive` avec un `ExportOptions.plist`.

---

## 5. Configuration de la tablette

| Réglage | Valeur | Pourquoi |
|---|---|---|
| Orientation | **Paysage verrouillé** | La caisse est en 3 colonnes (rayons / articles / panier) ; le portrait casse la mise en page. |
| Mode kiosque | Activé (Android : *Screen Pinning* ; iOS : *Guided Access*) | Empêche le vendeur de sortir de l'app. |
| Veille | Désactivée ou 30 min | Une caisse qui s'éteint en pleine vente est une caisse bloquée. |
| Permission caméra | **Autorisée** | Requise pour le scan code-barres. Si refusée, l'app propose la saisie manuelle. |
| Wi-Fi | Réseau de la boutique | Le mode hors ligne fonctionne, mais la synchronisation a besoin du réseau. |

### Douchette matérielle (USB / Bluetooth)

Aucune configuration : une douchette qui se comporte comme un clavier est
détectée automatiquement (rafale de frappes terminée par Entrée). Branchez-la
en USB ou appairez-la en Bluetooth ; le code scanné est ajouté au panier.

---

## 6. Exploitation quotidienne

### Ouverture de caisse

1. Lancer l'app → l'écran d'ouverture demande le **fond de caisse**.
2. Saisir le montant compté, valider. La session est ouverte côté serveur.

> L'ouverture exige le réseau : une session ne peut pas être ouverte hors
> ligne (le serveur doit l'enregistrer).

### Vente

- **Scanner** (bouton ou `F2`) ou taper dans la recherche.
- **Appui long** sur un article → l'épingler en favori.
- **Balayage** d'une ligne du panier → la retirer.
- **Appui long** sur une ligne → menu (quantité, retirer).
- **Bouton bloc** sur une ligne → annulation **motivée** (le motif est
  obligatoire ; « Autre » exige une précision).
- **`F6`** → mettre la commande en attente (client qui n'a pas fini).
- **`F7`** → rappeler une commande en attente.
- **`F8`** → aperçu du ticket avant encaissement.

### Raccourcis clavier

| Touche | Action |
|---|---|
| `F2` | Scanner un article |
| `F4` | Encaisser (espèces) |
| `F6` | Mettre la commande en attente |
| `F7` | Rappeler une commande |
| `F8` | Aperçu du ticket |
| `F9` | Appliquer une remise |
| `Échap` | Fermer / effacer la recherche |
| `Suppr` | Retirer la dernière ligne |
| `+` / `-` | Augmenter / diminuer la dernière ligne |

### Hors ligne

Le bandeau de synchronisation indique en permanence :

- **« N opération(s) en attente de réseau »** — les ventes sont en file, rien
  n'est perdu.
- **« Synchronisation N/total… »** — rejeu en cours.
- **« N opération(s) refusée(s) par le serveur »** — un rejet métier (4xx).
  Ces opérations **ne sont plus rejouées** : l'opérateur doit agir (vérifier
  la vente, corriger, re-saisir). C'est volontaire : rejouer un remboursement
  refusé ne ferait que le refuser encore, en masquant le vrai problème.

Le bouton **« Synchroniser »** force un rejeu immédiat.

### Clôture de caisse

1. Bouton **cadenas** dans la barre d'actions.
2. Saisir le **comptant compté**.
3. Si l'écart est non nul, un **motif** est exigé.

---

## 7. Mise à jour

```bash
git pull
cd front/mobile_apps/leopardo_pos_retail
flutter pub get
flutter build apk --release --dart-define=... --dart-define=...
adb install -r build/app/outputs/flutter-apk/app-release.apk
```

> **Avant de mettre à jour une tablette en service**, vérifiez que la file
> hors ligne est vide (bandeau « Tout est synchronisé »). Une mise à jour
> n'efface pas la file, mais une tablette propre évite d'avoir à démêler deux
> versions de schéma.

---

## 8. Dépannage

| Symptôme | Cause probable | Action |
|---|---|---|
| Écran noir au scan | Permission caméra refusée | Réglages tablette → Autorisations → Caméra. L'app propose la saisie manuelle en attendant. |
| Le code scanné s'affiche dans la recherche | Douchette trop lente (frappes espacées > 50 ms) | Vérifier le mode de la douchette (certaines ont un mode « lent »). |
| « Aucun article pour le code X » | Code absent du catalogue local | Vérifier que l'article existe et que la tablette a bien le bon `LOCATION_ID`. |
| Bandeau « refusée(s) par le serveur » | Rejet métier (stock, session fermée, doublon) | Lire le motif, corriger, re-saisir la vente. |
| La file ne se vide pas | Réseau absent ou API injoignable | Vérifier `API_BASE_URL` et le Wi-Fi. Le rejeu reprend automatiquement. |
| Catalogue vide | Premier lancement sans réseau | Connecter la tablette une fois pour amorcer le cache. |

---

## 9. Vérifications avant mise en service

- [ ] `flutter analyze` → 0 issue
- [ ] `flutter test` → tous verts
- [ ] `API_BASE_URL` pointe sur le bon environnement
- [ ] `LOCATION_ID` correspond à la boutique de la tablette
- [ ] Permission caméra accordée, scan testé sur un article réel
- [ ] Douchette testée (si utilisée)
- [ ] Ouverture de caisse testée, puis clôture avec écart
- [ ] Mode avion activé → une vente → bandeau hors ligne visible → réseau
      rétabli → synchronisation automatique vérifiée
