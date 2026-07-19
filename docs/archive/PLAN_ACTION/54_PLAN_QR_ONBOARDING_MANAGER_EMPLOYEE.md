# Plan 54 - QR onboarding reel et ajout employe fiable

Date : 2026-05-28

## Objectif

Transformer le flux QR existant en experience mobile vraiment utilisable sur le terrain : un QR visuel scannable, un fallback presse-papiers propre, et un ajout employe qui donne un retour clair en cas d'erreur API.

## Livraisons

- Socle mobile :
  - ajout d'un composant partage `LeopardoQrCard` dans `leopardo_core`,
  - rendu QR natif via `qr_flutter`, avec style sombre/clair lisible, arrondi, zone blanche et correction d'erreur moyenne,
  - action de copie conservee comme fallback quand le scan camera se fait depuis un autre telephone ou outil.
- Mobile employee :
  - l'espace compte affiche maintenant un vrai QR employe scannable,
  - le jeton signe reste copiable,
  - le collage du QR entreprise depuis le presse-papiers est explicite.
- Mobile manager :
  - le QR entreprise devient un QR visuel scannable,
  - l'import QR employe propose un bouton de collage depuis presse-papiers avant pre-remplissage,
  - les erreurs d'ajout employe affichent le message API lisible au lieu du `toString()` technique.
- Backend :
  - aucune route parallele ajoutee ; le plan reutilise les contrats signes existants :
    `GET /me/qr-profile`, `GET /company/qr-onboarding`,
    `POST /company/qr-onboarding/scan-employee`,
    `POST /company/qr-onboarding/create-employee`,
    `POST /me/company-qr/scan`.

## Validation attendue

- `dart format` sur `leopardo_core`, `leopardo_employee` et `leopardo_manager` touches.
- `git diff --check`.
- GitHub Actions : `Analyze leopardo_core`, `Analyze leopardo_employee`, `Analyze leopardo_manager`, builds debug mobiles.

## Suite

- Ajouter plus tard un scanner camera natif avec permissions Android/iOS dediees si le test terrain confirme le besoin dans l'app elle-meme.
- Prochain lot recommande : super admin mobile login strict + dashboard entreprises actionnable.
