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


## État au 2026-08-25 (mis à jour — audit #5279 lot final)

| App | Fichiers lib/ | Imports `leopardo_core` | Rôle | Chantier |
|---|---|---|---|---|
| `leopardo_core` | partagé | — | Package partagé (API, offline, push, i18n, thème, widgets, modèles, providers) | socle |
| `leopardo_employee` | ~79 | oui | App employé canonique | référence |
| `leopardo_manager` | 45 | oui | Manager/RH | **#2601/#5279** — 0 doublon identique, 4 quasi-doublons restants (bloqués F-27) |
| `leopardo_hr` | 39 | oui | RH dédiée | **#2601/#5279** — idem |
| `leopardo_platform_admin` | ~12 | oui | Super-admin plateforme | parité widgets (S-6) |
| `leopardo_marketing` | ~6 | oui | Marketing | #3910 |

**2026-08-25 (#5279 lot final)** : quasi-doublons `lib/` hr/manager **20 → 4** (−80 %, DoD ≥ 50 % ✅).
Les 4 restants (`attendance_provider`, `history_screen`, `register_screen`, `tenant_branding_provider`)
sont bloqués par la DI app (`authProvider`/`attendance_repository` divergents) → **F-27 (auth/repos unifiés),
gelé** — suivi #2601. Détail : `DEDUP_T008_INVENTAIRE.md` §2026-08-25.

## Règle

Tout code dupliqué détecté entre apps doit être extrait vers `leopardo_core`
(imports inter-apps interdits — verrouillé par `validate-mobile-apps-split.ps1`).
Mettre à jour ce tableau à chaque extraction.

## État au 2026-08-24 (après lots 1-2 #5279 et lot 3)

| App | Fichiers lib/ | Import `leopardo_core` | Chantier |
|---|---|---|---|
| `leopardo_hr` | 36 | oui | #2601/#5279 — extraction vers core |
| `leopardo_manager` | 42 | oui | #2601/#5279 — extraction vers core |

**Lot 3 (2026-08-24, PR #5428)** : 3 écrans extraits (version manager « glass » canonique,
identiques après normalisation de préfixe, hr modernisé au passage) :
`features/tasks/screens/task_list_screen.dart`,
`features/evaluations/screens/evaluation_list_screen.dart`,
`features/schedules/screens/schedule_list_screen.dart` (import `core/providers/core_providers.dart`
remplacé par l'import core explicite). 3 paires éliminées (6 instances). Callers mis à jour
(`app.dart` ×2). Cumul lots 1+2+3 : 20 paires éliminées (40 instances).

**Reste à extraire (lots suivants)** : écrans dépendant d'`authProvider` (team, absences,
salary_advances — nécessitent l'extraction du cluster auth), `company_branding`
(`tenant_branding_provider` dépend d'`authProvider`), `auth/*` (register/login/access_denied,
0.98-1.00), `home/*` (0.97), `attendance/screens/*` (après stabilisation PRs #5406/#5314/#5355),
`smart_attendance/*`, `settings/screens` (0.81).
