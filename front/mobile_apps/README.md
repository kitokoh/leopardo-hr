# Leopardo Mobile Apps

Ce dossier prepare la separation mobile en deux applications sans casser `front/mobile/`.

## Structure

- `leopardo_mobile_legacy/` : archive exacte du mobile historique. Ne pas modifier apres creation.
- `leopardo_core/` : package Flutter partage. Il contient uniquement les briques communes : API client, stockage, i18n, theme, couleurs, typographie, widgets de base, modeles et providers core.
- `leopardo_employee/` : app mobile employe. Elle expose les parcours personnels : connexion, accueil employe, pointage, absences, avances, paie, notifications, documents et compte.
- `leopardo_manager/` : app mobile manager/RH. Elle conserve le perimetre complet du mobile actuel et prepare les routes des futurs ecrans manager.

## Regles de contribution

- Toute modification partagee va dans `leopardo_core`.
- Toute modification d'ecran specifique va dans l'app concernee.
- L'app employe ne doit pas contenir de gestion d'equipe, validations manager, organigramme, approvals ou dashboard manager.
- L'app manager/RH conserve les ecrans complets et gere les differences internes via `employee.managerRole`.
- La differenciation par sous-role manager se fait dans les ecrans concernes, pas dans le router.
- `front/mobile/` reste le mobile historique fonctionnel tant que la bascule produit n'est pas terminee.
- `leopardo_mobile_legacy/` est un filet de securite : ne pas le modifier.

## Validation attendue

Depuis un SDK Flutter compatible Dart 3.8+ :

```bash
cd front/mobile_apps/leopardo_core
flutter pub get
flutter analyze

cd ../leopardo_employee
flutter pub get
flutter analyze
flutter build apk --debug --dart-define=API_BASE_URL=https://gestionemployerbackend.onrender.com/api/v1

cd ../leopardo_manager
flutter pub get
flutter analyze
flutter build apk --debug --dart-define=API_BASE_URL=https://gestionemployerbackend.onrender.com/api/v1
```
