# Leopardo Accounting — app mobile comptabilité

**Périmètre** : application mobile de comptabilité (facturation, suivi des impayés) — consommée par le métier comptable/finance.

**Statut** : vitrine fonctionnelle — l'écran stats est encore un mock (chantier ouvert, cf. README racine mobile_apps). Intégrée à melos et à la CI (`mobile-apps-ci.yml`).

**Plateformes** : Android uniquement à ce stade (`android/`). Les autres plateformes (iOS, web…) ne sont pas encore générées — à ajouter quand le périmètre comptable sera stabilisé (voir `docs/mobile/RESTO_MOBILE.md` pour le pattern d'intégration).

**Outillage** : `.gitignore` présent (règles fichiers générés, #6607) ; package melos `leopardo_accounting` ; dépendances partagées via `leopardo_core`.
