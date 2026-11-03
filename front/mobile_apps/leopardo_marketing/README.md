# 📣 Leopardo Marketing — Application Mobile

Application Flutter **vitrine marketing et communication** de la famille Leopardo RH.

> ⚠️ Ce README a été réécrit en M1-quater (#9112). Il se présentait encore comme
> `leopardo_accounting` / « A new Flutter project. » — reliquat du scaffold par
> copie. L'app n'a jamais été l'app comptabilité.

## Périmètre

- Vitrine mobile : présentation de l'offre, actualités, supports de communication.
- **Aucun workflow RH** : pas de pointage, pas d'absences, pas de validations, pas de paie.
- **Aucun workflow comptable** : la facturation et le suivi des impayés appartiennent à
  `leopardo_accounting`.

## Architecture

- Framework : Flutter 3.x
- State management : `flutter_riverpod` 3.3
- Package partagé : `../leopardo_core`
- Architecture active : providers Riverpod + services API, pas Bloc

## État connu

L'écran de statistiques est encore un **mock** — chantier ouvert, documenté dans le
suivi QA du 2026-08-15 (#2661).

## Démarrage rapide

```bash
cd front/mobile_apps/leopardo_marketing
flutter pub get
flutter run
```

## Identité produit

| Champ | Valeur |
| :--- | :--- |
| Nom affiché | Leopardo Marketing |
| `CFBundleName` (iOS) | `leopardo_marketing` |
| `android:label` | Leopardo Marketing |
| Manifeste PWA | `name` = Leopardo Marketing · `short_name` = Leopardo Mkt |

Cette identité est vérifiée par `dev-hub/tools/check-mobile-app-identity.sh` : une app
doit se présenter comme **elle-même**, jamais sous le nom d'une autre.
