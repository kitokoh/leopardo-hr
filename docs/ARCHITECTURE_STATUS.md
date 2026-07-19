# Architecture DDD — État des modules

> Mis à jour le 2026-07-19 (audit doc) | Phase 5 en cours — nettoyage legacy (PR #824)

## 1. Tableau de l'état DDD — 19 modules actifs

| Module          | Domain | Contracts | Exceptions | Application | DTOs | Infra | Interfaces | Providers | Tests |
|-----------------|:------:|:---------:|:----------:|:-----------:|:----:|:-----:|:----------:|:---------:|:-----:|
| **Absence**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Attendance**  | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Billing**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Cabinet**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Cameras**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **EdgeSync** 🆕  | ✅ | — | — | ✅ | — | — | ✅ | ✅ | ⚠️ |
| **Expense**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Fleet**       | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Growth** 🆕   | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ |
| **HR**          | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Marketing** 🆕| ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Notification**| ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Onboarding** 🆕| ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ |
| **Payroll**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Planning**    | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Platform** 🆕 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ |
| **Recruitment** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **SmartAttendance** 🆕 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ |
| **Training** 🆕 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ |

> ⚠️ = Module créé dans Phase 3–4 ou ajouté depuis, tests Feature à completer/verifier en Phase 5.
> — = Non applicable (module `EdgeSync` suit une structure specialisee synchro/offline, pas le squelette DDD standard Contracts/Exceptions/DTOs).
> Corrige lors de l'audit doc du 2026-07-19 : ce tableau omettait `EdgeSync` et `Marketing`, deux modules reellement presents sous `api/app/Modules/` (19 au total, pas 16).

---

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

## 3. Métriques globales

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

## 5. Roadmap restante (Phase 5+)

| Item | Priorité | Effort | Statut |
|------|:--------:|:------:|:------:|
| Supprimer `app/Models/` doublons (75 modèles) | P1 | Élevé | 🔧 En cours |
| Finaliser `app/DTOs/` racine (3 DTOs) | P1 | Faible | 🔧 En cours |
| Peupler `app/Shared/` (Traits/Attributes/Enums) | P2 | Moyen | ⏳ À faire |
| Migrer `Core/Tenant/` (TenantManager) | P2 | Moyen | ⏳ À faire |
| Tests Feature pour Growth, Platform, Onboarding, Training | P1 | Moyen | ⏳ À faire |
| PHPStan level 5+ via `phpstan-baseline.neon` | P2 | Moyen | ⏳ À faire |
| OpenAPI/Swagger (`dedoc/scramble`) | P2 | Faible | ⏳ À faire |
| routes/web.php — Web controllers (hors scope ADR actuel) | P3 | Faible | ⏳ À faire |
| Cloudflare Workers build (fix ou supprimer) | P3 | Faible | ⏳ À faire |
| i18n backend `fr/en/ar` via `lang/` | P3 | Moyen | ⏳ À faire |
| Event Sourcing Absence + Expense (CQRS) | P4 | Très élevé | ⏳ À faire |
| PostgreSQL RLS (remplace filtres `company_id`) | P4 | Très élevé | ⏳ À faire |

### Nettoyage legacy — bilan PR #824 (2026-07-01)

✅ 90 controllers `app/Http/Controllers/Api/V1/` supprimés
✅ 26 services `app/Services/` supprimés (26 doublons Infrastructure)
✅ 4 couches `Infrastructure/` créées (Growth, Platform, Onboarding, Training)
✅ 51 fichiers consommateurs mis à jour (imports redirigés vers modules)
