# 📱 Leopardo RH — Application Mobile

Application Flutter de gestion RH pour les employés et managers.

## 🌐 Environnements

| Environnement | URL API | Usage |
| :--- | :--- | :--- |
| **Local** | `http://10.0.2.2:8000/api/v1` | Développement (émulateur) |
| **Staging** | `https://gestionemployerbackend.onrender.com/api/v1` | Tests internes |
| **Production** | `https://api.leopardo-rh.com/api/v1` | Play Store |

## 🚀 Démarrage rapide

### 1. Prérequis
- [Flutter SDK](https://flutter.dev/docs/get-started/install) ≥ 3.3.0
- Android Studio ou VS Code avec extension Flutter
- Un émulateur Android ou un téléphone en mode développeur

### 2. Installation
```bash
cd mobile
flutter pub get
```

### 3. Lancer en développement
```bash
# Option A — Make (recommandé)
make run-dev

# Option B — Commande directe
flutter run --dart-define-from-file=.env.local
```

## 📦 Compiler l'APK (pour testeurs)

```bash
# Option A — Make (recommandé)
make build-staging

# Option B — Commande directe
flutter build apk --release \
  --dart-define-from-file=.env.staging \
  --build-name=1.0.0 --build-number=1
```

L'APK se trouve dans : `build/app/outputs/flutter-apk/app-release.apk`

## 🛒 Compiler pour le Play Store

```bash
# Option A — Make (recommandé)
make build-prod

# Option B — Commande directe
flutter build appbundle --release \
  --dart-define-from-file=.env.production \
  --build-name=1.0.0 --build-number=1
```

Le fichier `.aab` se trouve dans : `build/app/outputs/bundle/release/app-release.aab`

## 🔑 Comptes de test (Staging)

> Ces comptes nécessitent que le DemoCompanySeeder ait été exécuté.

| Rôle | Email | Mot de passe |
| :--- | :--- | :--- |
| Manager principal | `ahmed.benali@techcorp-algerie.dz` | `password123` |
| Manager RH | `fatima.meziane@techcorp-algerie.dz` | `password123` |
| Employé | `karim.aouad@techcorp-algerie.dz` | `password123` |

## 📋 ID Application
- **Package Android** : `com.leopardo.rh`
- **Nom affiché** : Leopardo RH
- **Version** : 1.0.0

## 🧪 Mocking & Développement Offline

Pour travailler sans API fonctionnelle, l'application utilise des mocks situés dans `assets/mock/`.

**Important :** La source de vérité pour les structures de données API se trouve dans le dossier racine `/docs/api-mock-data/`. Les fichiers JSON dans `assets/mock/` doivent être maintenus en synchronisation avec ces schémas centraux.

Pour mettre à jour les mocks :
1. Consulter `/docs/api-mock-data/` pour les derniers schémas.
2. Utiliser le script `/tools/generate_api_examples.py` pour régénérer les exemples si nécessaire.
3. Copier les fichiers pertinents dans `mobile/assets/mock/` en suivant la convention `mock_{nom}.json`.

## ⚠️ Note Cold Start (Staging)
Le premier appel vers le serveur Render peut prendre **30 à 60 secondes**
car le serveur se réveille depuis sa mise en veille automatique (plan gratuit).
Les appels suivants sont normaux (< 300ms).
