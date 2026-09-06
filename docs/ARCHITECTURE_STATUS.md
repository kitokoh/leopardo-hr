# Architecture DDD — État des modules

> Mis à jour le 2026-09-05 (audit vérité docs) | Phase 5 terminée — nettoyage legacy (PR #824)

## 1. Tableau de l'état DDD — 27 modules actifs

| Module          | Domain | Contracts | Exceptions | Application | DTOs | Infra | Interfaces | Providers | Tests |
|-----------------|:------:|:---------:|:----------:|:-----------:|:----:|:-----:|:----------:|:---------:|:-----:|
| **Absence** | — | — | — | — | — | — | ✅ | ✅ | ✅ |
| **Accounting** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Attendance** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Billing** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **CRM** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Cabinet** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Cameras** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Catalog** | ✅ | — | — | — | — | — | — | ✅ | ✅ |
| **Delivery** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **EdgeSync** | ✅ | — | — | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **EduManager** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Expense** | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Fleet** | ✅ | — | ✅ | — | — | — | ✅ | ✅ | ⚠️ |
| **FuelStation** | ✅ | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ |
| **Growth** | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ |
| **HR** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Marketing** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Notification** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Onboarding** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Payroll** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Planning** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ⚠️ |
| **Platform** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Recruitment** | ✅ | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ⚠️ |
| **Restaurant** | ✅ | — | — | — | — | — | — | ✅ | ✅ |
| **RestaurantManager** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ⚠️ |
| **Showcase** | ✅ | — | — | — | — | — | — | ✅ | ✅ |
| **TravelAgency** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |

> Tableau **régénéré depuis le disque** le 2026-09-05 (audit vérité — doublons CRM ×2, Restaurant ×3,
> RestaurantManager ×3, Accounting ×3, Delivery/EduManager ×2 et titre dupliqué supprimés).
> ✅ = au moins un fichier PHP dans la couche (ou dossier de tests dédié sous `api/tests/{Feature,Unit}/`) ;
> — = couche absente/vide. ⚠️ = aucun dossier de tests dédié identifié pour ce module.
> `EdgeSync` : structure spécialisée synchro/offline (pas de Contracts/Exceptions/DTOs — conforme).
> Décisions 2026-09-06 (ADR-0020, #6899/#6901/#6896 — délégation fondateur) :
> `Restaurant` = **fournisseur de contenu** (Solution/Survey consommés par RestaurantManager et `Core\Solutions`) —
> Application/Infrastructure/Interfaces **N/A intentionnel** (fini « en cours ») ; `Fleet` = Application/Infrastructure
> vides **conservées**, à peupler au fil des besoins fonctionnels (aucune Action factice) ; `Payroll` = Application en
> construction par lots (cartographie `PAYROLL_APPLICATION_CARTOGRAPHIE.md`, #6896 reste ouverte).
> Détails et dérogations : `api/ARCHITECTURE.md`.

## 2. Routes — État de la migration

| Fichier | Legacy avant | Legacy après | Statut |
|---------|:-----------:|:------------:|:------:|
| `routes/modules/rh.php` | 0 | 0 | ✅ déjà fait |
| `routes/modules/absence.php` | 0 | 0 | ✅ déjà fait |
| `routes/modules/expense.php` | 0 | 0 | ✅ déjà fait |
| `routes/modules/payroll_engine.php` | 0 | 0 | ✅ déjà fait |
| `routes/modules/cameras.php` | 0 | 0 | ✅ déjà fait |
| `routes/modules/cabinet.php` | 3 | **0** | ✅ Phase 3–4 |
| `routes/modules/tracking.php` | 6 | **0** | ✅ Phase 3–4 |
| `routes/modules/billing.php` | 3 | **0** | ✅ Phase 3–4 |
| `routes/modules/dashboard.php` | 4 | **0** | ✅ Phase 3–4 |
| `routes/modules/integrations.php` | 4 | **0** | ✅ Phase 3–4 |
| `routes/modules/planning.php` | 1 | **0** | ✅ Phase 3–4 |
| `routes/modules/hr_app.php` | 1 | **0** | ✅ Phase 3–4 |
| `routes/modules/growth.php` | 2 | **0** | ✅ Phase 3–4 |
| `routes/modules/sso.php` | 1 | **0** | ✅ Phase 3–4 |
| `routes/modules/user.php` | 2 | **0** | ✅ Phase 3–4 |
| `routes/api.php` | 25 | **0** | ✅ Phase 4 |
| `routes/web.php` | 11 | 11 | ⚠️ Web controllers (hors scope API DDD) |
| `routes/ai.php` | 1 | **0** | ✅ Phase 4 |

**Total API routes : 0 import legacy** `App\Http\Controllers\Api\V1`.

---

## 3. Métriques — historique par phase (Phase 1→4, chantier clos 2026-07 ; 25 modules actifs aujourd'hui)

| Indicateur | Phase 1 | Phase 2 | Phase 3 | Phase 4 |
|------------|:-------:|:-------:|:-------:|:-------:|
| Modules DDD actifs | 12 | 12 | 13 | **16** |
| Modules 100% complets | 1 | 12 | 12 | **16** |
| Domain/Contracts | 2/12 | 12/12 | 13/13 | **16/16** |
| Legacy imports routes/modules/* | 27 | 27 | 0 | **0** |
| Legacy imports routes/api.php | 25 | 25 | 25 | **0** |
| Coverage gate | 60% | 65% | 65% | **65% (required)** |

---

## 4. CI/CD — Coverage Gate

**Activé comme required check** sur `main` depuis Phase 4 :
- Check : `Backend Coverage (PHP 8.4 + PostgreSQL 16)`
- Seuil : 65% minimum
- Strict : oui (la branche cible doit être à jour)

---

> **Absence** : façade HTTP pure (Interfaces + Providers uniquement, dérogation PA2-ARCH-002) — les modèles de
> congés/absences appartiennent canoniquement à `Planning`. **Expense** : module DDD complet depuis 2026-09-06 (#6894)
> — couche `Application/` créée (Actions `GenerateExpenseAccountingEntries` / `VoidExpenseAccountingEntries`, écritures
> comptables #5235) ; la CI n'exempte plus qu'`Absence` de l'exigence des couches (`FACADE_ONLY_MODULES="Absence"`,
> `architecture-check.yml`). Voir `api/ARCHITECTURE.md`.

## 5. Roadmap restante (Phase 5+)

| Item | Priorité | Effort | Statut |
|------|:--------:|:------:|:------:|
| Supprimer `app/Models/` doublons (75 modèles) | P1 | Élevé | ✅ Fait — répertoire supprimé, 92 modèles migrés (voir `api/ARCHITECTURE.md`) |
| Finaliser `app/DTOs/` racine (3 DTOs) | P1 | Faible | ✅ Fait — répertoire supprimé (DTOs dans les modules) |
| Peupler `app/Shared/` (Traits/Attributes/Enums) | P2 | Moyen | ✅ Fait |
| Migrer `Core/Tenant/` (TenantManager) | P2 | Moyen | ✅ Fait — voir `api/ARCHITECTURE.md` «Nettoyage complet» et `api/app/Core/Tenant/README.md` |
| Tests Feature pour Growth, Platform, Onboarding | P1 | Moyen | ✅ Fait — dossiers `api/tests/Feature/{Growth,Platform,Onboarding}` présents |
| PHPStan niveau 5 via `phpstan-modules.neon` | P2 | Moyen | ✅ Fait — gate bloquant CI (niveau 5) |
| OpenAPI canonique | P2 | Faible | ✅ Fait — `api/openapi.yaml` source de vérité, Redocly 0 erreur |
| routes/web.php — Web controllers (hors scope ADR actuel) | P3 | Faible | ⏳ À faire |
| Cloudflare Workers build (fix ou supprimer) | P3 | Faible | ⏳ À faire |
| i18n backend via `lang/` | P3 | Moyen | ✅ Fait — `api/lang/{fr,en,ar,tr}` (voir `shared/i18n`) |
| Event Sourcing Absence + Expense (CQRS) | P4 | Très élevé | ⏳ À faire |
| PostgreSQL RLS (remplace filtres `company_id`) | P4 | Très élevé | ⏳ À faire |

### Nettoyage legacy — bilan PR #824 (2026-07-01)

✅ 90 controllers `app/Http/Controllers/Api/V1/` supprimés
✅ 26 services `app/Services/` supprimés (26 doublons Infrastructure)
✅ 4 couches `Infrastructure/` créées (Growth, Platform, Onboarding)
✅ 17 shims `app/Services/` supprimés (2026-08-11, #1728) — répertoire vide/supprimé, consommateurs sur les canoniques
✅ 51 fichiers consommateurs mis à jour (imports redirigés vers modules)
