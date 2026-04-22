# COULEURS — Tokens partagés Flutter ↔ Tailwind

> Source de vérité des couleurs Leopardo RH.
> Toute PR qui modifie une couleur doit modifier **ce fichier, `AppColors.dart` et `tailwind.config.js` dans le même commit** (L.07).

## Couleurs par domaine

| Domaine | Rôle | Hex | Flutter token | Tailwind class bg | Tailwind class text |
|---|---|---|---|---|---|
| RH | Pointage, employés, absences | `#10B981` | `AppColors.rh` | `bg-emerald-500` | `text-emerald-500` |
| Finance | Factures, dépenses, achats | `#F59E0B` | `AppColors.finance` | `bg-amber-500` | `text-amber-500` |
| Sécurité | Caméras, alarmes | `#3B82F6` | `AppColors.security` | `bg-blue-500` | `text-blue-500` |
| IA / Leo | Chat assistant, suggestions IA | `#7C3AED` | `AppColors.ia` | `bg-violet-600` | `text-violet-600` |

### Variantes claires (fond de chips, badges légers)

| Domaine | Hex | Flutter token | Tailwind |
|---|---|---|---|
| RH | `#D1FAE5` | `AppColors.rhLight` | `bg-emerald-100` |
| Finance | `#FEF3C7` | `AppColors.financeLight` | `bg-amber-100` |
| Sécurité | `#DBEAFE` | `AppColors.securityLight` | `bg-blue-100` |
| IA / Leo | `#EDE9FE` | `AppColors.iaLight` | `bg-violet-100` |

## Couleurs sémantiques (statuts)

Voir `docs/STATUTS.md` pour l'association statut ↔ couleur.

| Usage | Hex | Flutter | Tailwind |
|---|---|---|---|
| Succès / présent | `#10B981` | `AppColors.success` | `emerald-500` |
| Avertissement / retard | `#F59E0B` | `AppColors.warning` | `amber-500` |
| Danger / absent | `#EF4444` | `AppColors.danger` | `red-500` |
| Info / neutre | `#3B82F6` | `AppColors.info` | `blue-500` |

## Neutres (fonds, textes, bordures)

| Rôle | Hex | Flutter | Tailwind |
|---|---|---|---|
| Fond app (clair) | `#FFFFFF` | `AppColors.bgLight` | `bg-white` |
| Fond card (clair) | `#F8FAFC` | `AppColors.cardLight` | `bg-slate-50` |
| Fond app (sombre) | `#0F172A` | `AppColors.bgDark` | `bg-slate-900` |
| Fond card (sombre) | `#1E293B` | `AppColors.cardDark` | `bg-slate-800` |
| Texte primaire (clair) | `#0F172A` | `AppColors.textLight` | `text-slate-900` |
| Texte secondaire (clair) | `#64748B` | `AppColors.textMuted` | `text-slate-500` |
| Texte primaire (sombre) | `#F1F5F9` | `AppColors.textDark` | `text-slate-100` |
| Texte secondaire (sombre) | `#94A3B8` | `AppColors.textMutedDark` | `text-slate-400` |
| Bordure | `#E2E8F0` | `AppColors.border` | `border-slate-200` |
| Bordure (sombre) | `#334155` | `AppColors.borderDark` | `border-slate-700` |

## Règles d'usage

- **Jamais de couleur hardcodée** dans un écran ou une vue. Toujours passer par les tokens.
- **Une couleur = un domaine** (L.05). Interdiction de réutiliser le vert RH pour de la finance.
- **Contraste minimum** : le texte sur un fond doit respecter WCAG AA (ratio ≥ 4.5 pour texte normal, ≥ 3 pour texte large).
- **Mode sombre** : le dark mode est supporté mais n'est pas le défaut. Les tokens `Dark` sont utilisés via `Theme.of(context).brightness`.
