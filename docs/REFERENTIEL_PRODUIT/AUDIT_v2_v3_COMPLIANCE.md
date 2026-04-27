# AUDIT — Conformité code actuel vs APV v2 + Design System v3

> Date : avril 2026
> Portée : `api/` + `mobile/` sur `main`
> Objectif : identifier ce qui aligne avec la vision cible (APV v2 + Design v3) et ce qui contredit.

## Légende

| Symbole | Sens |
|---|---|
| ✅ Aligné | Le code respecte la vision |
| ⚠️ À ajuster | Le code fonctionne mais ne respecte pas la vision (à corriger progressivement) |
| ❌ Contredit | Le code fait l'inverse de la vision (à corriger dans une PR dédiée) |
| — Non applicable | Pas encore implémenté et Phase 2 |

## Architecture backend

| Sujet | État | Verdict | Action |
|---|---|---|---|
| Multi-tenant schema/shared | Implémenté, testé | ✅ | — |
| Migration `companies.schema_name` unique partial | Implémenté | ✅ | — |
| `routes/api.php` monolithique | Tout dans un fichier | ⚠️ | Extraire `routes/modules/rh.php` |
| `companies.features` JSONB | Absent (seul `plans.features` existe) | ❌ | Ajouter en Sprint B |
| `FeatureFlag::enabled()` service | Absent | ❌ | Créer en Sprint B |
| `employees.metadata` JSONB | Absent | ❌ | Ajouter en Sprint B |
| Super admin séparé | Table + guard dédiés | ✅ | — |
| RBAC par rôle | `EmployeePolicy`, `EnsureManagerRole` | ✅ | — |
| Self-service `/me/*` | Implémenté + testé | ✅ | — |

## Architecture mobile

| Sujet | État | Verdict | Action |
|---|---|---|---|
| `mobile/lib/` monolithique | Pas encore de packages séparés | ⚠️ | Acceptable en Phase 1. Split en packages = Phase 2. |
| `AppTheme` avec couleurs dark hardcodées | `Color(0xFF0F172A)` etc. | ⚠️ | Remplacer par `AppColors` tokens domaine |
| Font `Roboto` | Hardcodée | ⚠️ | Remplacer par `Inter` (aligné avec web) |
| Stades d'apprentissage | Absent | — | Phase 2, stade simplifié 2 paliers accepté |
| Modèle `Employee` parse `manager_role` | Fait (PR #76) | ✅ | — |
| Modèle `Employee` parse `capabilities` en Map | Fait (PR #76) | ✅ | — |

## Conformité APV v2 — Les 12 Lois

| Loi | État | Verdict | Action |
|---|---|---|---|
| L.01 Home = Conversation | Actuel = liste des fonctions sur `/me`, pas de chat | ❌ | Sprint C : scaffold Home conversationnelle |
| L.02 Mobile = source de vérité | Employé a aujourd'hui un `/me` web **et** des écrans mobile | ❌ | Sprint C : bloquer login web pour `role=employee`, rediriger vers "télécharge mobile" |
| L.03 Core n'importe aucun module | Pas encore de modules, donc vide = aligné | ✅ | Valider par test CI en Sprint B |
| L.04 Modules ne se connaissent pas | Pas encore de modules | — | — |
| L.05 Couleur = domaine | Hardcodé, pas de tokens domaine | ❌ | Sprint B : `AppColors` Flutter + `tailwind.config.js` étendu |
| L.06 Leo enseigne, l'interface confirme | Pas de Leo | — | Phase 2 |
| L.07 Grille partagée | Pas de fichier source-de-vérité unique | ❌ | `docs/COULEURS.md` + `docs/STATUTS.md` créés. Mettre à jour ensemble. |
| L.08 Un module = un package | Pas encore | ✅ | Prêt pour Sprint B |
| L.09 Contrat module immutable | Pas de modules | — | — |
| L.10 JSONB d'abord | Partiel (`company_settings` existe, mais pas sur `employees`) | ⚠️ | Sprint B : ajouter `metadata` JSONB |
| L.11 Progressive disclosure | Absent | — | Phase 2 |
| L.12 Leo spécialisé | Pas de Leo | — | Phase 2 |

## Conformité Design System v3

| Élément | État | Verdict | Action |
|---|---|---|---|
| Home conversationnelle | Absent (écran `/me` liste) | ❌ | Sprint C |
| `ChatInputBar` | Absent | ❌ | Sprint C (désactivé, sans API) |
| `QuickActionsRow` / chips | Absent | ❌ | Sprint C |
| `AlertBanner` widget | Absent | ❌ | Sprint C |
| `EmptyState` widget | Absent | ❌ | Sprint C |
| `LeopardoBadge` widget | Absent | ❌ | Sprint C |
| `ShimmerWidget` | Présent (`shimmer_loading.dart`) | ⚠️ | Renommer vers `ShimmerWidget` + uniformiser |
| Skeleton screens | Absent | ❌ | Sprint C |
| Humanised error messages | Mélange FR/EN | ⚠️ | Sprint C : passer par `HumanizedError` helper |
| SplashScreen | Absent | ⚠️ | Sprint C |
| Favicon + métadata | Basique | ⚠️ | Sprint C |
| WCAG AA contrast | Non vérifié | ⚠️ | Sprint C : audit avec plugin Flutter/Chrome |
| Typography Inter | Web oui (Fonts.bunny), mobile Roboto | ❌ | Sprint B : charger Inter en mobile |

## Conformité modules Phase 2 (Finance, Caméras)

Non implémentés — conforme à la roadmap (activation à la demande, après 3 pilotes MVP déployés).

- `Leopardo_RH_Finance_Complet.pdf` → archivé dans `docs/vision/`
- `Leopardo_RH_Camera_Complet+1.pdf` → archivé dans `docs/vision/`

## Résumé priorisé

### Sprint B (module boundaries foundation) — cette PR
1. `routes/modules/rh.php` extraits
2. Migration `metadata` JSONB sur `employees`, `companies`, `user_invitations`
3. Migration `companies.features` JSONB
4. Service `FeatureFlag`
5. Test d'invariant core-module (symbolique : vérifier qu'aucun controller `App\Http\Controllers\*` (hors `Modules\*`) n'importe `App\Modules\*`)

### Sprint B bis (design tokens) — cette PR
6. `AppColors.dart` avec tokens domaine + sémantiques
7. `tailwind.config.js` étendu avec les mêmes tokens
8. `LeopardoBadge` Flutter + `<x-attendance-badge>` Blade
9. `AlertBanner` Flutter + `<x-alert-banner>` Blade
10. `EmptyState` Flutter + `<x-empty-state>` Blade

### Sprint C (Home conversationnelle) — PR suivante
11. Home mobile : chips domaines + `ChatInputBar` (désactivé) + alertes
12. Home web : sidebar Leo (désactivée) + contenu 2-col
13. Employé bloqué en login web, redirigé vers "télécharge l'app"
14. Typography Inter côté mobile
