# STATUTS — Catalogue des statuts + couleurs associées

> Source de vérité des statuts affichés dans l'app (pointage, invitations, employés, etc.).
> Toute PR qui introduit un nouveau statut doit l'ajouter ici et définir son Flutter widget + sa classe Tailwind dans la même PR.

## Statuts de pointage (`attendance_logs.status`)

| Code | Libellé FR | Couleur token | Flutter widget | Tailwind classes |
|---|---|---|---|---|
| `present` | Présent | `AppColors.success` | `LeopardoBadge.present()` | `bg-emerald-100 text-emerald-700` |
| `late` | En retard | `AppColors.warning` | `LeopardoBadge.late()` | `bg-amber-100 text-amber-700` |
| `absent` | Absent | `AppColors.danger` | `LeopardoBadge.absent()` | `bg-red-100 text-red-700` |
| `early_leave` | Départ anticipé | `AppColors.warning` | `LeopardoBadge.earlyLeave()` | `bg-amber-100 text-amber-700` |
| `half_day` | Demi-journée | `AppColors.info` | `LeopardoBadge.halfDay()` | `bg-blue-100 text-blue-700` |
| `holiday` | Jour férié | `AppColors.textMuted` | `LeopardoBadge.holiday()` | `bg-slate-100 text-slate-700` |
| `weekend` | Weekend | `AppColors.textMuted` | `LeopardoBadge.weekend()` | `bg-slate-100 text-slate-700` |
| `on_leave` | En congé | `AppColors.info` | `LeopardoBadge.onLeave()` | `bg-blue-100 text-blue-700` |

## Statuts d'invitation (`user_invitations`)

| Code | Libellé FR | Couleur token | Flutter widget | Tailwind classes |
|---|---|---|---|---|
| `pending` | En attente | `AppColors.warning` | `LeopardoBadge.pending()` | `bg-amber-100 text-amber-700` |
| `sent` | Envoyée | `AppColors.info` | `LeopardoBadge.sent()` | `bg-blue-100 text-blue-700` |
| `accepted` | Acceptée | `AppColors.success` | `LeopardoBadge.accepted()` | `bg-emerald-100 text-emerald-700` |
| `expired` | Expirée | `AppColors.danger` | `LeopardoBadge.expired()` | `bg-red-100 text-red-700` |
| `revoked` | Révoquée | `AppColors.textMuted` | `LeopardoBadge.revoked()` | `bg-slate-100 text-slate-700` |

## Statuts d'employé (`employees.status`)

| Code | Libellé FR | Couleur token | Flutter widget | Tailwind classes |
|---|---|---|---|---|
| `active` | Actif | `AppColors.success` | `LeopardoBadge.active()` | `bg-emerald-100 text-emerald-700` |
| `on_leave` | En congé | `AppColors.info` | `LeopardoBadge.onLeave()` | `bg-blue-100 text-blue-700` |
| `suspended` | Suspendu | `AppColors.warning` | `LeopardoBadge.suspended()` | `bg-amber-100 text-amber-700` |
| `archived` | Archivé | `AppColors.textMuted` | `LeopardoBadge.archived()` | `bg-slate-100 text-slate-600` |

## Statuts de feature flag (`companies.features`)

| Code | Libellé FR | Couleur token | Flutter widget |
|---|---|---|---|
| `enabled` | Activé | `AppColors.success` | `LeopardoBadge.enabled()` |
| `trial` | Essai | `AppColors.info` | `LeopardoBadge.trial()` |
| `disabled` | Désactivé | `AppColors.textMuted` | `LeopardoBadge.disabled()` |

## Règles

- **Libellé** : toujours localisé (`lang/fr/statuses.php` + `lib/l10n/app_fr.arb`).
- **Couleur** : toujours passée par le token `AppColors.*`, jamais en dur.
- **Badge** : utiliser la factory `LeopardoBadge.{status}()` côté Flutter et le composant `<x-attendance-badge status="..." />` côté Blade.
- **Ajout d'un statut** : (1) migration de la colonne ENUM ou contrainte CHECK, (2) ligne dans ce fichier, (3) factory Flutter, (4) case Tailwind, (5) lang FR.
