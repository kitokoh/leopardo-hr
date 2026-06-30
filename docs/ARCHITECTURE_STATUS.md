# Architecture DDD — État des modules

> Mis à jour le 2026-06-30 | Phase 4 complète

## 1. Tableau de l'état DDD — 16 modules actifs

| Module          | Domain | Contracts | Exceptions | Application | DTOs | Infra | Interfaces | Providers | Tests |
|-----------------|:------:|:---------:|:----------:|:-----------:|:----:|:-----:|:----------:|:---------:|:-----:|
| **Absence**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Attendance**  | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Billing**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Cabinet**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Cameras**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Expense**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Fleet**       | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Growth** 🆕   | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ |
| **HR**          | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Notification**| ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Onboarding** 🆕| ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ |
| **Payroll**     | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Planning**    | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Platform** 🆕 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ |
| **Recruitment** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Training** 🆕 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ |

> ⚠️ = Module créé dans Phase 3–4, tests Feature à ajouter dans Phase 5.

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

| Item | Priorité | Effort |
|------|:--------:|:------:|
| Tests Feature pour Growth, Platform, Onboarding, Training | P1 | Moyen |
| PHPStan level 5+ via `phpstan-baseline.neon` | P2 | Moyen |
| OpenAPI/Swagger (`dedoc/scramble`) | P2 | Faible |
| routes/web.php — Web controllers (hors scope ADR actuel) | P3 | Faible |
| Cloudflare Workers build (fix ou supprimer) | P3 | Faible |
| i18n backend `fr/en/ar` via `lang/` | P3 | Moyen |
| Event Sourcing Absence + Expense (CQRS) | P4 | Très élevé |
| PostgreSQL RLS (remplace filtres `company_id`) | P4 | Très élevé |
