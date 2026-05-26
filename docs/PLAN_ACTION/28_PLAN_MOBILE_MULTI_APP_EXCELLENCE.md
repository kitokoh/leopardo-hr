# PLAN 28 - Mobile multi-app excellence

Date : 2026-05-26

## Objectif

Verifier et verrouiller le dernier choix architectural mobile : deux apps distinctes, un socle partage, une distribution Firebase fiable et une experience coherente pour le lancement marketing.

Le but n'est pas de recreer le mobile, mais de prouver que la separation est propre :

- `leopardo_employee` sert uniquement les employes ;
- `leopardo_manager` sert les managers principaux et RH ;
- `leopardo_core` porte le theme, les modeles, le client API, le storage, l'i18n et les composants communs ;
- `leopardo_mobile_legacy` reste une archive intouchable ;
- la CI valide les frontieres, les contrats API, la distribution Firebase et les builds.

## Lot 28.1 - Frontieres employee/manager

Actions :

- interdire dans `leopardo_employee` les routes, features, repositories et endpoints de decision manager ;
- conserver les workflows manager dans `leopardo_manager` : equipe, validations, absences, avances, placeholders manager ;
- verifier que `leopardo_core` n'importe jamais une app specifique ;
- garder `leopardo_mobile_legacy` immutable.

Critere de sortie :

- un validateur Plan 28 echoue si l'app employee contient `approve`, `reject`, `/team`, `/approvals` ou une route manager ;
- les validations Flutter et les builds debug restent verts.

## Lot 28.2 - Distribution Firebase/App Distribution

Actions :

- verifier que les fichiers Firebase installes correspondent aux IDs natifs stabilises ;
- verifier que les workflows utilisent les secrets separes ;
- verifier que l'upload Firebase est suivi d'un read-after-write ;
- documenter clairement que l'Android est distribue maintenant et que l'iOS demande un pipeline macOS signe produisant un `.ipa`.

Critere de sortie :

- un deploy `main` vert prouve que `com.leopardo.employee` et `com.leopardo.manager` sont visibles dans Firebase App Distribution.

## Lot 28.3 - Contrats API et workflows reels

Actions :

- garder les workflows critiques couverts : auth, pointage, absences, avances, paie, notifications, espace personnel, equipe et validations ;
- refuser les boutons vides et les routes statiques absentes ;
- verifier que les endpoints reels sont references dans les repositories/sources Dart.

Critere de sortie :

- `validate-mobile-workflow-contracts.ps1` et le nouveau validateur Plan 28 passent ensemble.

## Lot 28.4 - Coherence design mobile

Actions :

- maintenir l'usage de `MobileSurface`, `MobileTopBar`, `MobilePanel`, `MobileStatusPill` et des tons lisibles ;
- conserver le design pointage v3 sans `BottomNavigationBar` locale ;
- reduire progressivement les styles ad hoc dans les ecrans secondaires.

Critere de sortie :

- les ecrans critiques employee/manager restent lisibles en theme sombre et ne regressent pas vers des tons illisibles.

## Lot 28.5 - Readiness store

Actions :

- garder les IDs distincts Android/iOS ;
- conserver le mode strict bloque tant que les signatures store ne sont pas configurees ;
- preparer le pipeline iOS : certificats, profils, export `.ipa`, distribution Firebase/TestFlight.

Critere de sortie :

- Android testeurs : operationnel via Firebase App Distribution ;
- iOS : fichiers natifs poses et preconditions documentees avant pipeline signe.

## Etat initial observe

- Les deux APK Android ont ete publies dans Firebase App Distribution au build `main-1568 (1568)`.
- Les fichiers Firebase iOS sont installes, mais aucun `.ipa` n'est produit par CI.
- L'app employee gardait encore des methodes repository d'approbation/refus heritees ; elles doivent rester supprimees.

## Commandes de validation

```powershell
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-apps-split.ps1
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-release-readiness.ps1
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-workflow-contracts.ps1
powershell -ExecutionPolicy Bypass -File .\dev-hub\tools\validate-mobile-plan28.ps1
```
