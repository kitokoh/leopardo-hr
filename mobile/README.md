# Leopardo RH — Mobile (Flutter)

## Stack
- Flutter 3.x (Android + iOS)
- State management : Riverpod
- HTTP : Dio + Retrofit
- Local storage : Hive (cache offline)

## Setup local

```bash
flutter pub get
flutter run
```

## Développement sans API (mocks)
Tous les fichiers JSON de simulation sont dans :
`../docs/dossierdeConception/17_MOCK_JSON/`

Voir `../docs/dossierdeConception/17_MOCK_JSON/README_INTEGRATION_FLUTTER.md`
pour activer le mode mock.

## Commandes de lancement (Environnements API)

La base URL est configurable à la compilation (fallback par défaut : `http://localhost:8000/api/v1`).

**Dev (Tunnel local / IP réseau) :**
```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.15:8000/api/v1
```

**Prod/Staging :**
```bash
flutter run --release --dart-define=API_BASE_URL=https://api.leopardo-rh.com/api/v1
```

## Références
- Modèles Dart : `../docs/dossierdeConception/16_MODELES_DART/20_MODELES_DART_COMPLET.md`
- Contrats API : `../docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`
- Prompt démarrage : `../docs/PROMPTS_EXECUTION/v2/mobile/JU-01_A_JU-04_FLUTTER.md`
