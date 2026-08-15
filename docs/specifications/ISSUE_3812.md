# ISSUE_3812 — 13 écrans orphelins jamais enregistrés dans GoRouter

**Statut**: Fixed (PR `fix/3812-orphan-screens-cleanup`) · **Priorité**: P3 · **Module**: mobile (3 apps)

## Constat (audit MOBILE-1)

Écrans sans route GoRouter ni `context.go/push`, feature-dirs auto-référencés (seule
référence externe = registration provider inutilisée). Liste ré-auditée : les écrans
routés depuis (#3826) ou supprimés (#2597) sont exclus.

## Correctif

11 dossiers features (~40 fichiers) supprimés + 10 registrations `core_providers.dart`.
Garde `check-mobile-manifest-routes.sh` verte (aucune route manifeste supprimée).
