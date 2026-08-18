# Traqueur de convergence mobile (F-27, #1557)

> Inventaire de la duplication entre les apps Flutter et leur cible de refactor
> vers `leopardo_core`. Créé le 2026-08-17 (document référencé par
> `docs/mobile/CONVERGENCE_F27.md` mais jamais créé jusqu'ici).
> Stratégie : voir `CONVERGENCE_F27.md` — la convergence **réduit la duplication**,
> elle ne fusionne pas les apps.

## État au 2026-08-17

| App | Fichiers | Imports `leopardo_core` | Rôle | Chantier de dé-duplication |
|---|---|---|---|---|
| `leopardo_core` | — | — | Package partagé (API, offline Hive, push, i18n, thème, widgets, modèles, providers) | socle |
| `leopardo_employee` | ~79 | oui | App employé canonique (pointage hors-ligne, géofencing, congés, paie self-service) | référence |
| `leopardo_manager` | ~94 | ~89 | Manager/RH (dashboard, équipes, validations) | **#2601** — extraction des écrans partagés vers `leopardo_core` |
| `leopardo_hr` | ~93 | ~88 | RH dédiée (split de manager) | **#2601** — quasi-duplication de `leopardo_manager` (92 vs 93 fichiers) |
| `leopardo_platform_admin` | ~12 | ~11 | Super-admin plateforme (`/platform/*`) | parité widgets (S-6) |
| `leopardo_marketing` | ~6 | oui | Marketing (publication réseaux sociaux) | #3910 — 2 écrans seulement, fonctionnalités manquantes |

## Zones de duplication connues (à mesurer finement)

- `leopardo_hr` / `leopardo_manager` : ~92/93 fichiers quasi identiques (issue **#2601**,
  chantier structurel documenté — extraction des écrans partagés vers `leopardo_core`).
- `smartAttendance` : repository/provider dupliqué `smartAttendanceRepositoryCoreProvider`
  dans `leopardo_employee` — doublon mort retiré (fix 2026-08-17).
- `projectRepositoryProvider` (`leopardo_employee/hr/manager`) : providers morts supprimés
  (fix 2026-08-17).

## Règle

Tout code dupliqué détecté entre apps doit être extrait vers `leopardo_core`
(imports inter-apps interdits — verrouillé par `validate-mobile-apps-split.ps1`).
Mettre à jour ce tableau à chaque extraction.
