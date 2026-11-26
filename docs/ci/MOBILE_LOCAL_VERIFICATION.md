# Vérifier la chaîne mobile en local (sans attendre la CI)

> Issue #9950. La CI mobile ne se déclenche que si des chemins mobiles changent,
> et un run complet coûte plusieurs minutes de runners. Pour un simple
> `analyze` ou un test unitaire, la boucle locale est plus rapide — et elle
> n'occupe pas l'environnement partagé.

## Prérequis

- Flutter **3.50.0** (version épinglée dans
  `.github/actions/setup-flutter-android/action.yml`). Une autre version peut
  produire des erreurs d'analyse qui n'existent pas en CI.
- Java 17 (Temurin) pour les builds Android.

```bash
flutter --version   # doit afficher 3.50.0
java -version       # doit afficher 17.x
```

## Boucle courte : une seule app

```bash
cd front/mobile_apps/leopardo_public
flutter pub get
flutter analyze
flutter test
```

`leopardo_core` est une dépendance `path` : une modification de `core` est vue
immédiatement par l'app, sans republication.

## Boucle ciblée : un seul fichier de test

```bash
cd front/mobile_apps/leopardo_public
flutter test test/vertical_parity_test.dart
flutter test test/travel_journey_test.dart
```

## Vérifier les pins de dépendances (avant de toucher un `pubspec`)

Les deux pins sensibles du dépôt sont documentés et **ne doivent pas être
bumpés sans décision explicite** :

| Paquet | Version attendue | Pourquoi |
|---|---|---|
| `mobile_scanner` | `7.4.4` | 7.5.0 casse le build Kotlin Android — voir `docs/ci/SMOKE_MOBILE_SCANNER_750.md` |
| `flutter_blue_plus` | `1.36.8` | 2.x a changé de licence (BSD-3 → propriétaire) et exige `license:` sur `connect()` — voir #9432 |

Contrôle rapide — les 12 apps doivent résoudre les mêmes versions :

```bash
for f in front/mobile_apps/*/pubspec.lock; do
  ms=$(awk '/^  mobile_scanner:$/{f=1} f&&/^    version:/{print $2; exit}' "$f" | tr -d '"')
  fb=$(awk '/^  flutter_blue_plus:$/{f=1} f&&/^    version:/{print $2; exit}' "$f" | tr -d '"')
  printf "%-26s ms=%-8s fbp=%s\n" "$(basename "$(dirname "$f")")" "${ms:-none}" "${fb:-none}"
done
```

Attendu : `ms=7.4.4` et `fbp=1.36.8` partout.

## Gardes du dépôt (rapides, sans Flutter)

Ces scripts ne demandent pas le SDK et attrapent la majorité des régressions
structurelles :

```bash
bash dev-hub/tools/check-mobile-app-identities.sh
bash dev-hub/tools/check-mobile-layer-guard.sh
bash dev-hub/tools/check-mobile-manifest-routes.sh
bash dev-hub/tools/check-mobile-riverpod-legacy-imports.sh
bash dev-hub/tools/check-mobile-feature-duplication.sh
python3 dev-hub/tools/check-mobile-core-duplication.py
python3 dev-hub/tools/check-i18n-locale-parity.py
bash dev-hub/tools/check-i18n-dart-placeholders.sh
```

## Formatage

```bash
dart format --set-exit-if-changed front/mobile_apps/leopardo_public/lib
```

## Ce que la CI ajoute par rapport au local

- `flutter analyze` sur **les 12 apps** (une app peut casser sur une API de
  `core` sans que l'app touchée soit celle qu'on a testée).
- Les builds Android de distribution (Firebase App Distribution).
- Les validateurs PowerShell (`validate-mobile-*.ps1`), qui ne tournent pas
  sous Linux.

## Cache

Depuis #9950, l'action composite met en cache `~/.pub-cache` en plus du SDK
Flutter. La clé inclut la version Flutter et le hash des `pubspec.lock` : un
changement de pin invalide le cache, donc un cache ne peut pas masquer un écart
de version.
