# Mobile runtime smoke report - 2026-06-01

## Perimetre

Ce rapport couvre le Lot 67.1 : verifier que les trois apps mobiles de lancement ne peuvent pas regresser vers une page noire, grise ou un logo infini au demarrage.

Applications :

- `front/mobile_apps/leopardo_employee`
- `front/mobile_apps/leopardo_manager`
- `front/mobile_apps/leopardo_platform_admin`

## Garde ajoute

Le script `dev-hub/tools/validate-mobile-runtime-smoke.ps1` est execute dans `mobile-apps-ci.yml`.

Il verifie :

- `runApp()` est appele avant tout `await` dans `main()`.
- Chaque app est enveloppee par `StartupGate`.
- Chaque app declare un `criticalInitializer` et un `optionalInitializer`.
- Chaque app configure `ErrorWidget.builder` avec un message Leopardo lisible.
- Les routes initiales restent actionnables :
  - employee : `/welcome`
  - manager : `/welcome`
  - platform admin : `/platform/login`
- `StartupGate` garde :
  - demarrage apres premier frame via `addPostFrameCallback`
  - timeout critique
  - degradation automatique
  - bouton `Continuer`
  - texte explicite `Ouverture de votre espace...`
- Les tests core `StartupGate` couvrent l'affichage du garde et l'auto-continuation apres timeout.

## Decision technique

Ce garde ne remplace pas les tests Flutter complets ni les tests device reels, mais il bloque les regressions structurelles qui ont provoque les retours testeurs :

- attente native avant premier rendu ;
- bootstrap bloquant ;
- absence de message actionnable ;
- retour vers un splash/logo non informatif.

## Validation locale effectuee

Commande :

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-runtime-smoke.ps1
```

Resultat attendu :

```text
[mobile-runtime] employee: startup shell is guarded.
[mobile-runtime] manager: startup shell is guarded.
[mobile-runtime] platform_admin: startup shell is guarded.
[mobile-runtime] StartupGate anti-black-screen contract is valid.
```

## Validation CI attendue

Le workflow `Mobile Apps CI - Flutter` doit afficher l'etape :

- `Validate mobile runtime smoke`

et rester vert avant merge.

## Risque restant

Un test device reel reste necessaire pour prouver les fichiers Firebase natifs, permissions OS, reseau testeur et distribution store. Ce sujet est repris par les lots Plan 67.5 et 67.6.
