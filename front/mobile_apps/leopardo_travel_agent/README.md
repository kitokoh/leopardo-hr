# Leopardo Travel Agent — app mobile agent/vendeur TravelAgency

**Périmètre** : application mobile agent/vendeur de la solution verticale TravelAgency — vente guichet, encaissement cash, check-in QR, manifeste et point de vente (TRAVEL-701/#6088 + TRAVEL-810/#6100). Consomme le module backend `TravelAgency`.

**Statut** : intégrée à melos (`melos.yaml`) et à la CI mobile. Voir `pubspec.yaml` pour le détail des dépendances et `docs/specifications/SOLUTION_TRAVEL_AGENCY.md` pour la spécification métier.

**Plateformes** : Android à ce stade (`android/`).

**Outillage** : package melos `leopardo_travel_agent` ; design system, services API et widgets partagés via `leopardo_core` (URL backend fournie en `--dart-define`, jamais codée en dur — cf. issue #7963).
