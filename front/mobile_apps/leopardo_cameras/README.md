# Leopardo Caméras — app mobile surveillance

**Périmètre** : application mobile « Leopardo Caméras » (BC-19 DEVICE, #7426) — mur des caméras du tenant, visionnage direct via stream-token, événements et alertes (#7427). Consomme le module backend `Cameras`.

**Statut** : intégrée à melos (`melos.yaml`) et à la CI mobile. Voir `pubspec.yaml` pour le détail des dépendances.

**Plateformes** : Android à ce stade (`android/`).

**Outillage** : package melos `leopardo_cameras` ; design system, services API et widgets partagés via `leopardo_core` (URL backend fournie en `--dart-define`, jamais codée en dur — cf. issue #7963).
