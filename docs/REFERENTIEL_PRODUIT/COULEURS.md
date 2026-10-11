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

Voir `docs/REFERENTIEL_PRODUIT/STATUTS.md` pour l'association statut ↔ couleur.

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

## Échelle emerald — couleur de marque RH (documentée le 2026-10-11)

> Référence actée : **palette emerald par défaut de Tailwind**, identique sur la vitrine
> web (`front/web/tailwind.config.ts`) et l'admin (`front/admin-dashboard/tailwind.config.js`).
> Le step 500 (`#10B981`) est la couleur RH/Succès (tableaux ci-dessus) ; le step 900
> (`#064E3B`) sert d'émissif sombre dans les scènes 3D de la vitrine
> (`HeroBusinessOS3D`, `site/gh-pages/index.html`) et de fond profond aux surfaces emerald.

| step | Hex (web & admin) | Usage typique |
|---|---|---|
| 50 | `#ECFDF5` | fonds de section emerald très clair |
| 100 | `#D1FAE5` | fonds de chips / badges (`AppColors.rhLight`) |
| 200 | `#A7F3D0` | bordures douces |
| 300 | `#6EE7B7` | accents sur fond clair |
| 400 | `#34D399` | accents |
| 500 | `#10B981` | emerald primaire (`AppColors.rh` / `AppColors.success`) |
| 600 | `#059669` | hover / actif |
| 700 | `#047857` | texte sur fond clair |
| 800 | `#065F46` | texte / fonds sombres |
| 900 | `#064E3B` | émissif 3D vitrine, fonds emerald profonds |
| 950 | `#022C22` | fonds emerald très sombres |

## Échelle cyan — surface secondaire / liens (décision 2026-09-09, issue #7129)

> Référence actée : **palette cyan par défaut de Tailwind**, identique sur la vitrine web
> (`front/web/tailwind.config.ts`) et l'admin (`front/admin-dashboard/tailwind.config.js`).
> Les valeurs 50/100/200/300 du web (cyan custom désaturé) ont été alignées sur cette
> référence le 2026-09-09. Tokens Flutter déclarés dans `AppColors` (`cyan50`…`cyan950`)
> pour la parité L.07 (garde check-design-token-sync.py) — usage mobile si besoin, sinon
> échelle réservée web/admin.

| step | Hex (web & admin) | Usage typique |
|---|---|---|
| 50 | `#ecfeff` | fonds de section cyan très clair |
| 100 | `#cffafe` | fonds de chips / badges cyan |
| 200 | `#a5f3fc` | bordures douces |
| 300 | `#67e8f9` | accents sur fond clair |
| 400 | `#22d3ee` | accents |
| 500 | `#06b6d4` | cyan primaire (action secondaire) |
| 600 | `#0891b2` | hover / actif |
| 700 | `#0e7490` | texte sur fond clair |
| 800 | `#155e75` | texte / fonds sombres |
| 900 | `#164e63` | fonds sombres |
| 950 | `#083344` | fonds très sombres |

## Règles d'usage

- **Jamais de couleur hardcodée** dans un écran ou une vue. Toujours passer par les tokens.
- **Une couleur = un domaine** (L.05). Interdiction de réutiliser le vert RH pour de la finance.
- **Contraste minimum** : le texte sur un fond doit respecter WCAG AA (ratio ≥ 4.5 pour texte normal, ≥ 3 pour texte large).
- **Mode sombre / clair par surface** : **mobile (apps Flutter) = dark par défaut**
  (expérience principale, décision PA2-MOB-012 — `app_theme.dart`), le light étant conservé
  pour previews/tests ; **web & admin = light par défaut** (Tailwind). Les tokens `Dark`
  s'appliquent au mobile et aux thèmes sombres via `Theme.of(context).brightness`. Ne pas
  annoncer « dark partout » ni « dark optionnel » : la règle dépend de la surface
  (aligné le 2026-09-09 sur PA2-MOB-012).
