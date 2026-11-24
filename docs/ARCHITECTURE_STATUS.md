# Architecture DDD — État des modules

> **Document à sections mesurées.** Les sections 1, 3 et 5 sont **générées depuis le disque**
> par `dev-hub/tools/generate-architecture-status.py` (issue #9801) : ne pas les éditer à la main,
> la CI échoue si le fichier diverge du disque. Les sections 2 et 4 restent rédigées.
>
> Dernière régénération : 2026-11-22 (audit vérité docs — 41 modules mesurés).

<!-- BEGIN GENERATED ARCHITECTURE STATUS S1 -->

## 1. Tableau de l'etat DDD — 42 modules actifs

| Module | Domain | Contracts | Exceptions | Application | DTOs | Infra | Interfaces | Providers | Tests |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Absence** | ✅ | — | — | — | — | — | ✅ | ✅ | ⚠️ |
| **Accounting** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Attendance** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Billing** | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Branding** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **CRM** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Cabinet** | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Cameras** | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Catalog** | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Communication** | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Connectors** | ✅ | — | ✅ | — | — | ✅ | ✅ | ✅ | ✅ |
| **Delivery** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **EdgeSync** | ✅ | — | — | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **EduManager** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Expense** | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **FarmOs** | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | ✅ | ⚠️ |
| **Fleet** | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **FuelStation** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Fundraising** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Geo** | ✅ | ✅ | ✅ | — | — | ✅ | ✅ | ✅ | ✅ |
| **HR** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **HealthManager** | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **HospitalityManager** | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | ✅ | ⚠️ |
| **Library** | ✅ | ✅ | — | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Marketing** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Notification** | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Onboarding** | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Pages** | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Paiements** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Payroll** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Pharmacy** | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Planning** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Platform** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Recruitment** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Restaurant** | ✅ | — | — | — | — | — | — | ✅ | ✅ |
| **RestaurantManager** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ⚠️ |
| **Retail** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **SearchIntelligence** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Showcase** | ✅ | — | — | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **Trade** | ✅ | — | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ |
| **TravelAgency** | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ | ⚠️ |
| **Vtc** | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

> Tableau **regenere depuis le disque** par `dev-hub/tools/generate-architecture-status.py` (issue #9801). Ne pas editer a la main : la CI echoue si le fichier diverge du disque.
>
> ✅ = au moins un fichier PHP dans la couche ; — = couche absente/vide ; ⚠️ = aucun dossier de tests dedie sous `api/tests/{Feature,Unit}/`.
>
> Chemins mesures : `Domain/`, `Domain/Contracts/`, `Domain/Exceptions/`, `Application/`, `Application/DTOs/`, `Infrastructure/`, `Interfaces/`, `Providers/`.
>
> Absences **decidees** (pas des retards) :
> - `Absence` — facade HTTP assumee (PA2-ARCH-002, ADR-0031) — domaine propriete de Planning.
> - `EdgeSync` — structure specialisee synchro/offline — pas de Contracts/Exceptions/DTOs.
> - `Restaurant` — fournisseur de contenu (ADR-0020) — Application/Infra/Interfaces N/A intentionnel.

<!-- END GENERATED ARCHITECTURE STATUS S1 -->

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
| `routes/modules/sso.php` | 1 | **0** | ✅ Phase 3–4 |
| `routes/modules/user.php` | 2 | **0** | ✅ Phase 3–4 |
| `routes/api.php` | 25 | **0** | ✅ Phase 4 |
| `routes/web.php` | 11 | 11 | ⚠️ Web controllers (hors scope API DDD) |
| `routes/ai.php` | 1 | **0** | ✅ Phase 4 |

**Total API routes : 0 import legacy** `App\Http\Controllers\Api\V1`.

---

<!-- BEGIN GENERATED ARCHITECTURE STATUS S3 -->

## 3. Metriques — etat courant mesure

| Indicateur | Valeur |
|---|---|
| Modules DDD actifs | **42** |
| Fichiers de test sur disque | 1523 |
| Modules sans dossier de tests dedie | 5 (`Absence`, `FarmOs`, `HospitalityManager`, `RestaurantManager`, `TravelAgency`) |
| Modules sans couche `Application` | 4 (`Absence`, `Connectors`, `Geo`, `Restaurant`) |

> L'historique par phase (Phase 1→4, chantier clos 2026-07) est conserve ci-dessous a titre d'archive. Il ne decrit **pas** l'etat courant : le lire comme tel etait la source des trois comptages contradictoires releves par l'audit du 2026-11-22.

<!-- END GENERATED ARCHITECTURE STATUS S3 -->

### 3.1 Archive — historique par phase (Phase 1→4, chantier clos 2026-07)

> ⚠️ **Ce tableau ne décrit PAS l'état courant.** Il est conservé comme trace du chantier de
> migration. Les valeurs « 16 » et « 25 » qu'il contient ont été lues comme l'état courant et
> sont la source des comptages contradictoires relevés par l'audit du 2026-11-22.

| Indicateur | Phase 1 | Phase 2 | Phase 3 | Phase 4 |
|------------|:-------:|:-------:|:-------:|:-------:|
| Modules DDD actifs | 12 | 12 | 13 | **16** |
| Modules 100% complets | 1 | 12 | 12 | **16** |
| Domain/Contracts | 2/12 | 12/12 | 13/13 | **16/16** |
| Legacy imports routes/modules/* | 27 | 27 | 0 | **0** |
| Legacy imports routes/api.php | 25 | 25 | 25 | **0** |
| Coverage gate | 60% | 65% | 65% | **65%** |

---

## 4. CI/CD — Coverage Gate

> ⚠️ **Corrigé le 2026-11-22 (issue #9801).** Cette section déclarait le check
> `Backend Coverage (PHP 8.4 + PostgreSQL 16)` comme **required** et **strict: oui**.
> C'est faux : la protection de branche réelle porte **4 contextes requis**, et
> `Backend Coverage` n'en fait pas partie. La source de vérité est
> `BRANCH_PROTECTION_REQUIRED.md` (doc canonique) — cette section y renvoie désormais
> au lieu de dupliquer une valeur qui a dérivé.

- Check : `Backend Coverage (PHP 8.4 + PostgreSQL 16)`
- Seuil : 65% minimum
- Statut : **non requis au merge** — voir `BRANCH_PROTECTION_REQUIRED.md` pour la liste
  réelle des contextes requis.

---

> **Absence** : façade HTTP pure (Interfaces + Providers uniquement, dérogation PA2-ARCH-002) — les modèles de
> congés/absences appartiennent canoniquement à `Planning`. **Expense** : module DDD complet depuis 2026-09-06 (#6894)
> — couche `Application/` créée (Actions `GenerateExpenseAccountingEntries` / `VoidExpenseAccountingEntries`, écritures
> comptables #5235) ; la CI n'exempte plus qu'`Absence` de l'exigence des couches (`FACADE_ONLY_MODULES="Absence"`,
> `architecture-check.yml`). Voir `api/ARCHITECTURE.md`.

<!-- BEGIN GENERATED ARCHITECTURE STATUS S5 -->

## 5. Roadmap restante — chaque ligne re-verifiee sur le disque

| Item | Priorite | Effort | Statut |
|---|:---:|:---:|---|
| Supprimer `app/Models/` doublons | P1 | Eleve | ✅ Fait |
| Finaliser `app/DTOs/` racine | P1 | Faible | ✅ Fait |
| Peupler `app/Shared/` (Traits/Attributes/Enums) | P2 | Moyen | ✅ Fait |
| Migrer `Core/Tenant/` (TenantManager) | P2 | Moyen | ✅ Fait |
| Tests Feature pour Platform, Onboarding | P1 | Moyen | ✅ Fait |
| PHPStan via `phpstan-modules.neon` | P2 | Moyen | ✅ Fait |
| OpenAPI canonique | P2 | Faible | ✅ Fait |
| i18n backend via `lang/` | P3 | Moyen | ✅ Fait |

> Statuts **mesures** : `✅ Fait` signifie que le chemin attendu existe (ou a bien disparu, pour les suppressions). Une ligne « Fait » dont le chemin est absent est un faux positif — c'est ce que la garde empeche.

<!-- END GENERATED ARCHITECTURE STATUS S5 -->

### Nettoyage legacy — bilan PR #824 (2026-07-01)

✅ 90 controllers `app/Http/Controllers/Api/V1/` supprimés
✅ 26 services `app/Services/` supprimés (26 doublons Infrastructure)
✅ 4 couches `Infrastructure/` créées (Growth, Platform, Onboarding)
✅ 17 shims `app/Services/` supprimés (2026-08-11, #1728) — répertoire vide/supprimé, consommateurs sur les canoniques
✅ 51 fichiers consommateurs mis à jour (imports redirigés vers modules)
