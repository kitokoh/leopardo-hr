# Audit mobile — design system, UX et taches non realisees — 2026-07-20

Statut: complete pour publication
Auteur: audit interne KiloClaw (agent), a la demande de kitokoh
Perimetre: `front/mobile_apps/{leopardo_core,leopardo_employee,leopardo_manager,leopardo_hr,leopardo_platform_admin}`, `docs/mobile/README.md`, `docs/REFERENTIEL_PRODUIT/APV.md`, `docs/vision/02_design_system/`, tickets `PA2-MOB-*` de `02_BACKLOG_ATOMIQUE.md`.

Ce document complete `08_AUDIT_ARCHITECTURE_TECH.md`, `09_AUDIT_MODULES_API_STRUCTURE.md`, `10_AUDIT_I18N_MULTILINGUE.md` et `11_AUDIT_VITRINE_ACQUISITION.md` avec un audit dedie au design/UX des 4 apps mobiles Flutter et a l'etat reel des tickets `PA2-MOB-*`. Les tickets d'action issus de cet audit sont listes en fin de fichier et repris dans `02_BACKLOG_ATOMIQUE.md`/`03_GITHUB_PROJECT_IMPORT.csv` sous le prefixe `PA2-MOB-*` (suite de `PA2-MOB-001` a `010` deja existants).

Methode : lecture directe du code (`front/mobile_apps/**/lib`), comptage grep cible (litteraux `Color(0x...)`, usages `context.l10n`/`Text('...')`, usages des widgets partages `leopardo_core`), calcul reel du ratio de contraste WCAG (formule de luminance relative sRGB) sur les paires de couleurs texte/fond declarees dans `app_theme.dart`/`app_colors.dart`, recherche exhaustive de `PA2-MOB-00[6-9]`/`PA2-MOB-010` dans `CHANGELOG.md` pour verifier une preuve de livraison. Aucun environnement Flutter/Dart disponible dans le sandbox d'audit (`flutter`/`dart` absents) : aucune verification `flutter analyze`/build n'a pu etre executee, seule une revue statique du code source.

---

## 1. Ce qui fonctionne deja bien (a ne pas casser)

- **Un vrai design token system existe et est documente** : `leopardo_core/lib/core/theme/{app_colors.dart,app_theme.dart,app_typography.dart,app_spacing.dart}` porte la Loi L.05/L.07 de l'APV (couleur = domaine, grille partagee). `AppColors` documente explicitement l'intention ("Jamais de valeur hex hardcodee dans les ecrans : toujours passer par `AppColors.*`") et reste la source unique cote mobile, cense rester synchrone avec les tokens web et `docs/REFERENTIEL_PRODUIT/COULEURS.md`.
- **Vocabulaire de composants partages reel** : `leopardo_core/lib/core/widgets/` fournit `PulseButton`, `LeopardoBadge`, `EmptyState`, `ErrorState`, `ShimmerLoading`, `MobileSurface`, `LeopardoQrCard`, `StartupGate`, `AlertBanner`, `DemoUserBottomSheet` — reellement consommes par `leopardo_employee`, `leopardo_manager` et `leopardo_hr` (`EmptyState` : 12/20/20 fichiers, `MobileSurface` : 8/12/12 fichiers).
- **Contraste texte/fond du theme sombre reellement conforme WCAG AA**, verifie par calcul (pas par impression) sur les paires cles de `AppTheme._buildTheme()` :
  - `mobileDarkText` (#E2EAF6) sur `mobileDarkSurface` (#111B2E) : **14.2:1**
  - `mobileDarkMuted` (#7A9CC0) sur `mobileDarkBackground` (#0B1120) : **6.6:1**
  - `textMutedDark` (#94A3B8) sur `bgDark`/`cardDark` : **6.96:1** / **5.71:1**
  - `textMuted` (#64748B) sur `bgLight` (#FFFFFF) : **4.76:1**
  Tous depassent le seuil AA texte normal (4.5:1). C'est une bonne base objective pour `PA2-QA-009` (accessibilite mobile) — le probleme de lisibilite documente plus bas n'est pas dans les tokens eux-memes mais dans leur contournement (section 2).
- **`leopardo_platform_admin` est la seule app 100% propre sur les couleurs en dur** hors 3 exceptions ponctuelles (voir section 2.3) — coherent avec son perimetre volontairement plus restreint (6 ecrans contre 31-39 dans les 3 autres apps).

## 2. Vrais problemes de design/UX identifies (preuve chiffree)

### 2.1 Duplication de la palette dark en litteraux hex — contournement des tokens qu'ils sont censes remplacer

`AppTheme._buildTheme()` declare 6 constantes locales `const Color` pour le theme sombre (`mobileDarkBackground #0B1120`, `mobileDarkSurface #111B2E`, `mobileDarkField #0C1525`, `mobileDarkBorder #1A2B44`, `mobileDarkText #E2EAF6`, `mobileDarkMuted #7A9CC0`). Ces memes valeurs hex sont **recopiees en dur** (pas via `Theme.of(context)` ni `AppColors.*`) dans des ecrans metier, dans les 3 apps qui partagent le plus de code :

| Fichier | Occurrences `Color(0x...)` |
|---|---|
| `leopardo_employee/lib/features/smart_attendance/screens/smart_attendance_screen.dart` | 45 |
| `leopardo_employee/lib/features/attendance/screens/attendance_screen.dart` | 39 |
| `leopardo_employee/lib/features/smart_attendance/screens/attendance_mode_picker_screen.dart` | 14 |
| `leopardo_manager/lib/features/attendance/screens/attendance_screen.dart` | present (meme palette recopiee) |
| `leopardo_manager/lib/features/smart_attendance/screens/{smart_attendance_dashboard_screen.dart,pending_sessions_screen.dart}` | present |
| `leopardo_hr/lib/features/attendance/screens/attendance_screen.dart` + `smart_attendance/screens/{smart_attendance_dashboard_screen.dart,pending_sessions_screen.dart}` | present (quasi-duplicata de `leopardo_manager`, 1245 lignes chacun) |
| `leopardo_manager/lib/main.dart`, `leopardo_hr/lib/main.dart` | present |
| `leopardo_platform_admin/lib/main.dart` | 2 (`Color(0xFF0B1120)`, `Color(0xFFE2EAF6)`) |

Total mesure : **106 litteraux** dans `leopardo_employee`, **36** dans `leopardo_manager`, **37** dans `leopardo_hr`, **3** dans `leopardo_platform_admin`. Consequence concrete : si `AppColors`/`AppTheme` change une seule de ces 6 valeurs (ex. ajustement contraste, rebranding), les ecrans de pointage — le coeur du produit — ne suivent pas automatiquement et divergent silencieusement du theme. `smart_attendance_screen.dart` va plus loin : il introduit **des couleurs Material par defaut jamais definies dans `AppColors`** (`0xFF2196F3`, `0xFF4CAF50`, `0xFF9C27B0`, `0xFF607D8B`, `0xFFEF5350`, `0xFFEF9A9A`, `0xFFFFA726`), donc une palette secondaire non gouvernee coexiste avec le design system officiel sur le meme ecran.

C'est exactement le risque que `PA2-MOB-010` ("composants unifies") est cense couvrir, mais le ticket actuel ne le detecte pas explicitement — voir tickets section 4.

### 2.2 Mode sombre force en permanence, sans bascule utilisateur, en contradiction avec le commentaire du code

`AppTheme` documente lui-meme : *"Le mode clair est la presentation par defaut. Le mode sombre reste supporte pour les surfaces qui en ont besoin, sans devenir l'experience principale du produit."* Or les 4 `MaterialApp`/`MaterialApp.router` racines (`leopardo_employee/lib/app.dart:262`, `leopardo_manager/lib/app.dart:321`, `leopardo_hr/lib/app.dart:319`, `leopardo_platform_admin/lib/src/platform_admin_app.dart:87`) declarent tous **`themeMode: ThemeMode.dark`** en dur — jamais `ThemeMode.system`, aucune preference utilisateur, aucun ecran de reglage pour choisir. `AppTheme.lightTheme` existe, est correctement construit, mais **n'est jamais reellement atteignable par un utilisateur final** sur aucune des 4 apps. Soit le commentaire du code est obsolete (le sombre est devenu la vraie experience principale et devrait etre documente comme tel), soit c'est une regression non voulue qui bloque de fait toute personne qui prefere un theme clair (accessibilite/preference visuelle) — dans les deux cas, c'est une decision produit non tranchee explicitement, a clarifier avant de considerer le "design system mobile 2026" termine.

### 2.3 `leopardo_platform_admin` ne partage pas le vocabulaire de composants des 3 autres apps

Sur les widgets `leopardo_core` verifies (`PulseButton`, `LeopardoBadge`, `ShimmerLoading`, `LeopardoQrCard`), `leopardo_platform_admin` affiche **0 utilisation** partout, contre 1-2 fichiers dans chacune des 3 autres apps. Meme `MobileSurface` (le plus utilise transversalement) n'apparait que dans 6 fichiers sur 6 ecrans totaux de cette app — ce qui suggere que chaque ecran platform admin reconstruit sa propre presentation plutot que de composer avec les briques partagees. Le perimetre plus restreint (login, liste clients, creation client, dashboard) explique une partie de l'ecart mais pas l'absence totale de badges/boutons/QR partages, alors que la creation client et le detail client sont des ecrans candidats naturels pour `LeopardoBadge` (statut trial/actif) et `LeopardoQrCard` (onboarding).

### 2.4 Dette i18n mobile : bloque une vraie finalisation design pour l'arabe (RTL) et le turc

Deja identifie dans `10_AUDIT_I18N_MULTILINGUE.md` (`PA2-I18N-009`), rappele ici car impact direct sur le design : le socle technique RTL existe (`isRtl` persiste, `AppLocalizations` generes), mais tres peu d'ecrans l'utilisent reellement.

| App | Litteraux `Text('...')` en dur (mesure) | Usages `context.l10n`/`.l10n.` (mesure) |
|---|---|---|
| `leopardo_employee` | ~113 | 2 |
| `leopardo_manager` | ~254 | 1 |
| `leopardo_hr` | ~252 | 1 |
| `leopardo_platform_admin` | ~10 | 0 |

Consequence design concrete : tant que le texte reste en dur, aucune verification visuelle RTL/longueur de chaine turque n'est possible sur la quasi-totalite des ecrans — le "design system mobile 2026" ne peut pas etre valide en `ar`/`tr` avant que `PA2-I18N-009` progresse significativement. A traiter en parallele, pas en sequence stricte apres.

## 3. Etat reel des tickets `PA2-MOB-006` a `PA2-MOB-010` — aucune preuve de livraison trouvee

`PILOTAGE.md` classe "Plans 25-29 | Mobile multi-app, release, excellence, platform admin | ✅ Livre", mais ce sont des plans **historiques** (`docs/archive/PLAN_ACTION/`), distincts des tickets `PA2-MOB-*` du plan **actif** (`docs/PLAN_ACTION2/`). Recherche exhaustive de `PA2-MOB-006`, `PA2-MOB-007`, `PA2-MOB-008`, `PA2-MOB-009`, `PA2-MOB-010` dans `CHANGELOG.md` (121 Ko, journal de tout changement selon la regle absolue #9 de `PILOTAGE.md`) : **zero occurrence pour les 5**. Aucune entree ne cite ces IDs comme livres, ce qui, avec la regle "chaque changement = entree CHANGELOG.md", indique que ces 5 tickets restent **non demarres ou non tracés**, pas silencieusement termines :

- `PA2-MOB-006` (demandes avance/absence detaillees) — aucune preuve.
- `PA2-MOB-007` (gestion RH mobile, nommer/revoquer RH) — aucune preuve.
- `PA2-MOB-008` (mon compte premium portable, biometrie) — aucune preuve ; bloque aussi `PA2-KIO-004` (enrolement biometrie mobile vers kiosk) qui en depend explicitement.
- `PA2-MOB-009` (mobile admin creation/activation client) — aucune preuve directe sous cet ID, mais le code existe deja et fonctionne (`company_create_screen.dart`, `company_requests_screen.dart`, `company_detail_screen.dart` sont bien presents et branches dans `leopardo_platform_admin`) : **a verifier/clore explicitement plutot qu'a laisser "P1 ouvert"**, car il semble en realite livre sans que le ticket ait ete ferme dans le backlog.
- `PA2-MOB-010` (design system mobile 2026) — aucune preuve, et les sections 2.1-2.3 ci-dessus montrent des ecarts concrets qui confirment qu'il n'est pas termine.

## 4. Tickets d'action (prefixe `PA2-MOB-*`, suite de 001-010 existants)

| ID | Priorite | Ticket | Surface | Definition of Done |
|---|---|---|---|---|
| PA2-MOB-011 | P1 | Eliminer les litteraux hex dupliques dans les ecrans pointage | `leopardo_employee/lib/features/{attendance,smart_attendance}`, `leopardo_manager`/`leopardo_hr` equivalents, `leopardo_platform_admin/lib/main.dart` | zero `Color(0x...)` litteral dans les fichiers listes en section 2.1 ; toutes les references passent par `AppColors.*`/`Theme.of(context)` ; les couleurs Material non gouvernees (`0xFF2196F3`, `0xFF4CAF50`, `0xFF9C27B0`, etc. dans `smart_attendance_screen.dart`) sont soit mappees sur une couleur `AppColors` existante, soit ajoutees explicitement au token system avec justification ; script de lint (`grep -rn` ou regle CI dediee) ajoute pour empecher la reapparition |
| PA2-MOB-012 | P1 | Trancher et documenter la politique de theme (sombre force vs clair par defaut vs choix utilisateur) | `leopardo_core/lib/core/theme/app_theme.dart`, `leopardo_{employee,manager,hr}/lib/app.dart`, `leopardo_platform_admin/lib/src/platform_admin_app.dart` | decision produit ecrite (dans `APV.md` ou ce fichier) : soit le commentaire de `AppTheme` est corrige pour refleter le sombre comme experience principale reelle, soit `themeMode` devient `ThemeMode.system` avec un reglage utilisateur explicite dans le compte ; les 4 apps sont alignees sur la meme decision |
| PA2-MOB-013 | P2 | Aligner `leopardo_platform_admin` sur le vocabulaire de composants partages | `leopardo_platform_admin/lib/src/features/companies`, `leopardo_core/lib/core/widgets` | `company_detail_screen.dart`/`company_create_screen.dart` utilisent `LeopardoBadge` pour le statut trial/actif et `LeopardoQrCard` si un flux QR onboarding client existe ou est prevu ; au moins parite d'usage de `MobileSurface`/`ShimmerLoading` avec les 3 autres apps sur les ecrans equivalents (liste, detail, creation) |
| PA2-MOB-014 | P1 | Auditer et clore explicitement le statut reel de `PA2-MOB-006` a `PA2-MOB-009` | `docs/PLAN_ACTION2/02_BACKLOG_ATOMIQUE.md`, `CHANGELOG.md` | chaque ticket `PA2-MOB-006`/`007`/`008`/`009` a un statut explicite (fait avec preuve CHANGELOG, partiellement fait avec ecart documente, ou non demarre) ; `PA2-MOB-009` verifie en priorite car le code applicatif correspondant (`company_create_screen.dart` et al.) semble deja exister sans entree CHANGELOG associee ; toute cloture s'accompagne d'une entree CHANGELOG retroactive citant l'ID |

Ordre d'execution recommande : `PA2-MOB-014` d'abord (evite de refaire un travail deja livre ou de fermer a tort un ticket non fait) → `PA2-MOB-011` (dette technique la plus mesurable et la moins ambigue) → `PA2-MOB-012` (decision produit bloquante avant de juger le design "coherent") → `PA2-MOB-013` (parite platform admin, priorite basse car perimetre le plus restreint).

---

## 5. Recapitulatif executif

| Domaine | Etat | Severite |
|---|---|---|
| Token system (`AppColors`/`AppTheme`) — existence et qualite intrinseque | Mature, contraste WCAG AA verifie sur le theme sombre | OK |
| Vocabulaire de composants partages (`leopardo_core/widgets`) | Reellement adopte par 3 apps sur 4 | OK |
| Respect des tokens dans les ecrans de pointage (coeur produit) | 106+36+37 litteraux hex dupliques, couleurs Material non gouvernees ajoutees | Eleve |
| Politique de theme clair/sombre | Contradiction commentaire code vs comportement reel, aucun choix utilisateur | Moyen-eleve |
| Parite design `leopardo_platform_admin` vs 3 autres apps | 0 usage des composants partages verifies | Moyen |
| Tracabilite reelle des tickets `PA2-MOB-006` a `010` | Aucune preuve CHANGELOG pour aucun des 5 | Moyen (risque de refaire ou de faussement clore) |
| i18n mobile bloquant la validation design multi-langue | Deja documente ailleurs (`PA2-I18N-009`), confirme ici comme dependance design | Moyen (suivi croise) |
