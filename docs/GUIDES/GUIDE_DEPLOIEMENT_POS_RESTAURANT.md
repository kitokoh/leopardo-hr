# Déploiement — Caisse tablette Restaurant (`leopardo_pos_restaurant`)

Application Flutter **native offline-first** pour la prise de commande en salle
et l'encaissement au restaurant. Elle fonctionne sans réseau : le menu et le
plan de salle sont mis en cache, les commandes sont mises en file et rejouées
automatiquement.

- **Chemin** : `front/mobile_apps/leopardo_pos_restaurant`
- **Paquet Android** : `app.leopardo.pos_restaurant`
- **Bundle iOS** : `app.leopardo.posRestaurant`
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
cd front/mobile_apps/leopardo_pos_restaurant
flutter pub get
```

---

## 2. Configuration du backend

| Variable | Rôle | Où la définir |
|---|---|---|
| `API_BASE_URL` | Racine de l'API (`https://api.exemple.com`) | `--dart-define` |
| `BRANCH_ID` | Identifiant de la succursale de **cette** tablette | `--dart-define` |

```bash
flutter run \
  --dart-define=API_BASE_URL=https://api.staging.exemple.com \
  --dart-define=BRANCH_ID=7
```

> **Une tablette = une succursale.** `BRANCH_ID` détermine le menu, le plan de
> salle et la session de caisse. Plusieurs tablettes d'un même restaurant
> partagent le même `BRANCH_ID` (elles voient les mêmes tables).

---

## 3. Build Android

### Debug

```bash
flutter build apk --debug \
  --dart-define=API_BASE_URL=https://api.staging.exemple.com \
  --dart-define=BRANCH_ID=7

adb install -r build/app/outputs/flutter-apk/app-debug.apk
```

### Release (signé)

1. **Keystore** (une seule fois, à conserver hors du dépôt) :

```bash
keytool -genkey -v -keystore ~/leopardo-pos-restaurant.jks \
  -keyalg RSA -keysize 2048 -validity 10000 \
  -alias leopardo_pos_restaurant
```

2. **`android/key.properties`** (jamais committé) :

```properties
storePassword=<mot de passe du keystore>
keyPassword=<mot de passe de la clé>
keyAlias=leopardo_pos_restaurant
storeFile=/chemin/absolu/vers/leopardo-pos-restaurant.jks
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
  --dart-define=BRANCH_ID=7
```

### En CI

```bash
echo "$ANDROID_KEYSTORE_BASE64" | base64 -d > android/app/leopardo-pos-restaurant.jks
cat > android/key.properties <<EOF
storePassword=$ANDROID_STORE_PASSWORD
keyPassword=$ANDROID_KEY_PASSWORD
keyAlias=$ANDROID_KEY_ALIAS
storeFile=leopardo-pos-restaurant.jks
EOF
```

---

## 4. Build iOS

```bash
cd ios && pod install && cd ..
flutter build ipa --release \
  --dart-define=API_BASE_URL=https://api.exemple.com \
  --dart-define=BRANCH_ID=7
```

Signature : `ios/Runner.xcworkspace` → **Signing & Capabilities** → équipe et
profil de distribution. En CI, profil + certificat en secrets, puis
`xcodebuild -exportArchive` avec un `ExportOptions.plist`.

---

## 5. Configuration de la tablette

| Réglage | Valeur | Pourquoi |
|---|---|---|
| Orientation | **Paysage verrouillé** | La caisse est en 3 colonnes (menu / plats / commande) ; le portrait casse la mise en page. |
| Mode kiosque | Activé (Android : *Screen Pinning* ; iOS : *Guided Access*) | Empêche le serveur de sortir de l'app. |
| Veille | Désactivée ou 30 min | Une tablette qui s'éteint en plein service est bloquée. |
| Permission caméra | **Autorisée** | Requise pour scanner les boissons et produits emballés. Si refusée, l'app propose la saisie manuelle. |
| Wi-Fi | Réseau du restaurant | Le mode hors ligne fonctionne, mais la synchronisation a besoin du réseau. |

### Douchette matérielle (USB / Bluetooth)

Aucune configuration : une douchette qui se comporte comme un clavier est
détectée automatiquement (rafale de frappes terminée par Entrée). Utile pour
les boissons et les produits emballés.

---

## 6. Exploitation en service

### Ouverture de caisse

1. Lancer l'app → l'écran d'ouverture demande le **fond de caisse**.
2. Saisir le montant compté, valider.

> L'ouverture exige le réseau : une session ne peut pas être ouverte hors
> ligne (le serveur doit l'enregistrer).

### Prise de commande

1. **Type de commande** : sur place, à emporter, livraison.
2. **Sur place** → choisir la **table** sur le plan de salle et le nombre de
   **couverts**. Le plan signale une table trop petite pour le groupe.
3. Ajouter les plats : **appui** sur une tuile, **scan** (`F2`) pour les
   boissons, ou **recherche** par nom / code.
4. **Appui long** sur un plat → l'épingler en favori.
5. **Balayage** d'une ligne → la retirer ; **appui long** → menu (quantité au
   pavé numérique, retirer) ; **bouton bloc** → annulation **motivée**.

### Remise, pourboire, paiement partagé

- **Remise** (`F9`) : bornée au sous-total. Une remise supérieure bloque
  l'encaissement (message explicite).
- **Pourboire** (`F10`) : **s'ajoute** au total, ce n'est pas une remise.
- **Paiement partagé** : plusieurs moyens pour une même addition. La
  validation n'est acceptée que si les parts couvrent **exactement** le total.
  La répartition égale ne perd aucun centime (`100/3` → `34, 33, 33`).

### Mise en attente et rappel

- **`F6`** → mettre la commande de côté avec un libellé (« Table 4 »,
  « Client au bar »). Le panier se vide, le service continue.
- **`F7`** → rappeler une commande. Tout est restauré : lignes, type de
  commande, table, couverts, remise, pourboire.
- Une commande en attente depuis **plus de 2 h** est signalée comme oubliée.

### Raccourcis clavier

| Touche | Action |
|---|---|
| `F2` | Scanner un article |
| `F4` | Encaisser (espèces) |
| `F6` | Mettre la commande en attente |
| `F7` | Rappeler une commande |
| `F8` | Aperçu du ticket |
| `F9` | Appliquer une remise |
| `F10` | Ajouter un pourboire |
| `Échap` | Fermer / effacer la recherche |
| `Suppr` | Retirer la dernière ligne |
| `+` / `-` | Augmenter / diminuer la dernière ligne |

### Hors ligne

Le bandeau de synchronisation indique en permanence :

- **« N opération(s) en attente de réseau »** — les commandes sont en file,
  rien n'est perdu.
- **« Synchronisation N/total… »** — rejeu en cours.
- **« N opération(s) refusée(s) par le serveur »** — un rejet métier (4xx).
  Ces opérations **ne sont plus rejouées** : l'opérateur doit agir. C'est
  volontaire : rejouer un encaissement refusé ne ferait que le refuser encore,
  en masquant le vrai problème.

Le bouton **« Synchroniser »** force un rejeu immédiat.

---

## 7. Mise à jour

```bash
git pull
cd front/mobile_apps/leopardo_pos_restaurant
flutter pub get
flutter build apk --release --dart-define=... --dart-define=...
adb install -r build/app/outputs/flutter-apk/app-release.apk
```

> **Avant de mettre à jour une tablette en service**, vérifiez que la file
> hors ligne est vide (bandeau « Tout est synchronisé »).

---

## 8. Dépannage

| Symptôme | Cause probable | Action |
|---|---|---|
| Écran noir au scan | Permission caméra refusée | Réglages tablette → Autorisations → Caméra. L'app propose la saisie manuelle. |
| « Sélectionnez une table pour une commande sur place » | Type = sur place sans table | Choisir une table sur le plan de salle. |
| « La remise dépasse le sous-total » | Remise > sous-total | Réduire la remise. |
| Le paiement partagé est refusé | Les parts ne couvrent pas exactement le total | Ajuster les parts ; l'écart est affiché. |
| Bandeau « refusée(s) par le serveur » | Rejet métier (session fermée, doublon) | Lire le motif, corriger, re-saisir. |
| La file ne se vide pas | Réseau absent ou API injoignable | Vérifier `API_BASE_URL` et le Wi-Fi. Le rejeu reprend automatiquement. |
| Menu vide | Premier lancement sans réseau | Connecter la tablette une fois pour amorcer le cache. |

---

## 9. Vérifications avant mise en service

- [ ] `flutter analyze` → 0 issue
- [ ] `flutter test` → tous verts
- [ ] `API_BASE_URL` pointe sur le bon environnement
- [ ] `BRANCH_ID` correspond à la succursale de la tablette
- [ ] Permission caméra accordée, scan testé sur une boisson réelle
- [ ] Plan de salle affiché, sélection de table et couverts testées
- [ ] Remise, pourboire et paiement partagé testés
- [ ] Mise en attente puis rappel testés (vérifier la restauration complète)
- [ ] Mode avion activé → une commande → bandeau hors ligne visible → réseau
      rétabli → synchronisation automatique vérifiée
