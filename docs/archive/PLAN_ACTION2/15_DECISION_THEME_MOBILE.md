# Decision produit : politique de theme clair/sombre mobile (PA2-MOB-012)

**Ticket:** PA2-MOB-012 (voir `02_BACKLOG_ATOMIQUE.md`, `03_GITHUB_PROJECT_IMPORT.csv`)
**Statut:** Decide et documente le 2026-07-22.
**Origine:** `12_AUDIT_MOBILE_DESIGN_UX.md` section 4, ligne PA2-MOB-012 — contradiction constatee entre le commentaire de `AppTheme` (`leopardo_core/lib/core/theme/app_theme.dart`), qui affirmait "le mode clair est la presentation par defaut", et le comportement reel des 4 apps mobiles, qui forcent toutes `ThemeMode.dark` sans aucun reglage utilisateur.

## Constat (code reel, verifie ligne par ligne)

| App | Fichier | Ligne | Valeur |
|---|---|---|---|
| `leopardo_employee` | `lib/app.dart` | 267 | `themeMode: ThemeMode.dark` |
| `leopardo_hr` | `lib/app.dart` | 319 | `themeMode: ThemeMode.dark` |
| `leopardo_manager` | `lib/app.dart` | 321 | `themeMode: ThemeMode.dark` |
| `leopardo_platform_admin` | `lib/src/platform_admin_app.dart` | 87 | `themeMode: ThemeMode.dark` |

Aucun ecran de reglages (`features/settings/screens/settings_screen.dart` et equivalents) n'expose de bascule clair/sombre, et aucune preference persistee (`SharedPreferences`/provider) ne pilote `themeMode` dans les 4 apps — la valeur est un litteral fixe a chaque appel de `MaterialApp`.

`AppTheme.lightTheme` et `AppTheme.darkTheme` existent tous les deux et sont tous les deux maintenus (memes tokens `AppColors`/`AppTypography`, memes composants), mais seul `darkTheme` est jamais rendu a un utilisateur final aujourd'hui.

## Options considerees

1. **Corriger le commentaire pour refleter le sombre comme experience principale reelle**, sans changement de comportement. Cout minimal, aligne la documentation sur le code existant, ne change rien pour l'utilisateur (le produit est deja livre et teste en sombre depuis le debut).
2. **Passer a `ThemeMode.system` + reglage utilisateur explicite dans le compte**, sur les 4 apps. Implique : un provider de preference de theme partage (`leopardo_core`), une bascule dans chaque ecran de reglages, une persistance locale, et — plus important — une repasse de QA visuelle complete en mode clair sur les 4 apps (le contraste WCAG AA n'a ete verifie qu'en sombre par `12_AUDIT_MOBILE_DESIGN_UX.md` section 5). Cout significatif, aucune demande produit ou retour utilisateur documente ne motive ce changement a ce jour.

## Decision

**Option 1 retenue : le mode sombre est confirme comme l'experience principale reelle du produit mobile.** Aucune demande utilisateur ni contrainte produit documentee ne justifie aujourd'hui le cout d'un reglage clair/sombre complet (option 2). Le commentaire de `AppTheme` dans `leopardo_core/lib/core/theme/app_theme.dart` est corrige pour refleter cet etat de fait au lieu de l'affirmation inverse et non appliquee qu'il portait.

`AppTheme.lightTheme` n'est pas supprime : il reste disponible comme base si un reglage utilisateur est demande a l'avenir, et peut deja etre utilise localement pour des previews/captures d'ecran nécessitant un fond clair.

## Consequences

- Les 4 apps restent inchangees (`ThemeMode.dark` partout) — **aucun changement de comportement utilisateur**.
- `leopardo_core/lib/core/theme/app_theme.dart` : commentaire de classe corrige (voir diff associe a ce document).
- Si un reglage clair/sombre utilisateur est demande a l'avenir, ce document doit etre mis a jour (ou remplace) et un nouveau ticket cree pour l'implementation complete (provider de preference partage `leopardo_core`, bascule dans les 4 ecrans de reglages, persistance, QA visuelle complete en clair) — hors perimetre de PA2-MOB-012, qui ne demandait qu'une decision ecrite.

## Reference croisee

- `12_AUDIT_MOBILE_DESIGN_UX.md` section 4 (ligne PA2-MOB-012) et section 5 (recapitulatif executif, ligne "Politique de theme clair/sombre").
- `CHANGELOG.md` : entree correspondante sous `### Fixed`.
