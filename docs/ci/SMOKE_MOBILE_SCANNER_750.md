# Smoke build leopardo_employee — mobile_scanner 7.5.0 casse la chaîne Kotlin

## Constat

Le check CI `Smoke build leopardo_employee (debug APK)` (workflow
`.github/workflows/mobile-smoke.yml`, **non requis** par la protection de
`main`) est passé au rouge le 2026-11-09 sur toutes les PR ouvertes après
l'ajout de `mobile_scanner: ^7.5.0` à `leopardo_core` (commit `e4efa4f41`,
« feat(pos-core): logique partagée des deux caisses ») et à
`leopardo_pos_retail` (commit `15027f349`).

Le build Flutter `leopardo_employee` échoue avec :

```
e: file:///.../mobile_scanner-7.5.0/android/src/main/kotlin/dev/steenbakker/mobile_scanner/MobileScanner.kt:167:39
  Type mismatch: inferred type is Bitmap? but Bitmap was expected
e: file:///.../mobile_scanner-7.5.0/android/src/main/kotlin/dev/steenbakker/mobile_scanner/MobileScanner.kt:243:57
  Type mismatch: inferred type is Bitmap? but Bitmap was expected
FAILURE: Build failed with an exception.
> Task :mobile_scanner:compileDebugKotlin
```

## Diagnostic

- Ce n'est **pas** un défaut du code Leopardo : l'erreur est dans le plugin
  tiers `dev.steenbakker.mobile_scanner` (version 7.5.0, publiée le
  2026-11-01), pas dans un fichier `.dart` du dépôt.
- `mobile_scanner` est une **dépendance transitive** de `leopardo_employee` via
  `leopardo_core` : toute app qui dépend de `leopardo_core` compile le plugin.
- 7.5.0 est la dernière version publiée sur pub.dev au 2026-11-09 ; aucune
  version corrigée n'existe encore.
- La version précédente **7.4.4** (2026-11-03) est la dernière stable connue
  qui compile avec le toolchain actuel (Flutter stable, `compileSdk 36`,
  Kotlin du plugin `mobile_scanner` 7.x).

## Correctif appliqué (cette branche)

Épingler `mobile_scanner` à `7.4.4` (sans `^`) dans les deux pubspec qui le
déclarent en dépendance directe :

- `front/mobile_apps/leopardo_core/pubspec.yaml`
- `front/mobile_apps/leopardo_pos_retail/pubspec.yaml`

Les `pubspec.lock` correspondants sont alignés sur `7.4.4`.

## À faire quand le plugin est corrigé

1. Surveiller une release `mobile_scanner` > 7.5.0 qui corrige le type
   `Bitmap?` dans `MobileScanner.kt` (issue upstream à suivre sur
   https://github.com/juliansteenbakker/mobile_scanner).
2. Repasser la contrainte à `^7.x.y` (ou la version corrigée) dans les deux
   pubspec.
3. Relancer `flutter pub get` dans les apps concernées et vérifier que le
   check `Smoke build leopardo_employee (debug APK)` redevient vert.
4. Supprimer ce document d'audit.
