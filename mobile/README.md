# Leopardo RH - Mobile (Flutter)

## Stack
- Flutter 3.x
- State management : Riverpod
- HTTP : Dio
- Local storage : Hive

## Setup local

```bash
flutter pub get
flutter run
```

## Mode mock

Les fichiers JSON de simulation sont dans :
`../docs/dossierdeConception/17_MOCK_JSON/`

## Commandes API

Base URL configurable a la compilation.

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.15:8000/api/v1
flutter run --release --dart-define=API_BASE_URL=https://api.leopardo-rh.com/api/v1
```

## References
- Modeles Dart : `../docs/dossierdeConception/16_MODELES_DART/20_MODELES_DART_COMPLET.md`
- Contrats API : `../docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md`
- Prompt actif : `../docs/PROMPTS_EXECUTION/v3/MVP-06_FLUTTER_APP.md`
