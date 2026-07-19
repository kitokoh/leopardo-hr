# 23 — PLAN API PRODUCTION-GRADE : VENDABLE ET SOLIDE

**Date :** 2026-05-24
**Objectif :** Transformer l'API backend de "feature-complete" en "enterprise-grade vendable" — chaque endpoint robuste, coherent, documente, testable et securise.

---

## Audit — Etat des lieux

| Metrique | Valeur actuelle | Cible vendable |
|---|---|---|
| Controllers | 87 | OK — couverture fonctionnelle tres large |
| Services | 39 | OK — bonne separation controller/service |
| Models | 82 | OK — tous ont $fillable |
| Routes | ~459 (225 GET, 131 POST, 46 PUT, 33 DELETE, 24 PATCH) | OK — surface complete |
| Tests | 143 fichiers | Bon — mais couverture inegale par module |
| FormRequests | ~56 fichiers | **LACUNE** — ~50 controllers utilisent encore `$request->validate()` inline |
| API Resources | 17 fichiers | **LACUNE CRITIQUE** — ~62 controllers retournent des arrays bruts |
| Policies (authorize) | 6 controllers sur 87 | **LACUNE** — la plupart des controllers n'utilisent pas de Policy |
| DB::transaction | 11 usages dans tout le codebase | **LACUNE** — operations multi-write sans protection transactionnelle |
| Eager loading (with) | 38 usages | OK mais certains index retournent des relations sans eager |
| Pagination | 78 usages | OK — la majorite des list endpoints paginent |
| OpenAPI spec | 6074 lignes | Bon — mais peut diverger du code reel |
| Error codes | 14 codes distincts | **LACUNE** — pas de catalogue centralise d'erreurs |

### Lacunes critiques identifiees

1. **Validation inline** : La majorite des controllers utilisent `$request->validate()` au lieu de FormRequests. Cela disperse les regles, empeche la reutilisation et complexifie la doc auto.

2. **Reponses non normalisees** : ~62 controllers retournent `response()->json(['data' => ...])` sans API Resource. Cela cree des contrats JSON fragiles — un changement de colonne casse tous les clients.

3. **Policies manquantes** : Seuls 6 controllers sur 87 utilisent `$this->authorize()`. Le middleware `api.manager` couvre les routes, mais il manque un controle fin model-level (ex: un comptable ne devrait pas modifier un contrat d'un autre departement).

4. **Transactions absentes** : Les operations multi-ecritures (creation contrat + amendment, recrutement job + applicants, payroll run + pay slips) n'ont pas de DB::transaction. Un echec partiel peut corrompre les donnees.

5. **Catalogue erreurs inexistant** : Les codes erreur sont eparpilles dans les controllers. Pas de fichier centralise, pas de constantes, pas de mapping i18n complet.

6. **Rate limiting inegal** : Les limiters nommes (`payroll-sensitive`, `auth-sensitive`) existent mais ne couvrent pas tous les endpoints sensibles (ex: exports, social declarations).

7. **Eager loading partiel** : Certains `index()` retournent des relations sans `->with()`, creant des N+1 en production.

8. **Logging metier absent** : Les audit_logs couvrent les events mais les actions metier critiques (approbation pret, validation payroll, cloture recrutement) ne sont pas tracees systematiquement.

---

## Plan d'execution — 8 iterations

### Iteration 1 : Normalisation des reponses API (API Resources)

**Objectif** : Chaque endpoint retourne un API Resource au lieu d'un array brut.

**Priorite** : Les modules consommes par le mobile et l'admin dashboard.

```
Batch 1A — Resources critiques (clients les consomment deja) :
- DashboardController → DashboardSummaryResource (existant, verifier usage)
- AbsenceController → AbsenceResource (nouveau)
- AttendanceController → AttendanceLogResource (existant, verifier usage)
- PayrollController → PayrollResource (nouveau)
- PayrollRunController → PayrollRunResource (existant, verifier usage)
- DepartmentController → DepartmentResource (nouveau)
- PositionController → PositionResource (nouveau)
- ScheduleController → ScheduleResource (nouveau)
- SiteController → SiteResource (nouveau)

Batch 1B — Resources modules avances :
- ContractController → ContractResource (existant, verifier)
- TrainingController → TrainingCourseResource (existant), TrainingSessionResource (nouveau)
- RecruitmentController → JobPostingResource (existant), ApplicantResource (existant)
- EmployeeLoanController → LoanResource (existant, verifier)
- ExpenseClaimController → ExpenseClaimResource (existant, verifier)
- ApprovalController → ApprovalRequestResource (nouveau)
- OrgChartController → OrgChartNodeResource (nouveau)

Batch 1C — Resources admin/plateforme :
- BillingController → SubscriptionResource (existant), InvoiceResource (nouveau)
- AuditLogController → AuditLogResource (nouveau)
- WebhookController → WebhookEndpointResource (nouveau)
- NotificationController → NotificationResource (nouveau)
- HrReportController → (reports gardent les arrays, pas de Resource)
```

### Iteration 2 : Extraction FormRequests

**Objectif** : Chaque mutation (POST/PUT/PATCH/DELETE) utilise un FormRequest au lieu de `$request->validate()`.

```
Batch 2A — Modules critiques :
- DepartmentController → StoreDepartmentRequest, UpdateDepartmentRequest
- PositionController → StorePositionRequest, UpdatePositionRequest
- ScheduleController → StoreScheduleRequest, UpdateScheduleRequest
- SiteController → StoreSiteRequest, UpdateSiteRequest
- ProjectController → StoreProjectRequest, UpdateProjectRequest
- TaskController → StoreTaskRequest, UpdateTaskRequest

Batch 2B — Modules avances :
- ContractController → UpdateContractRequest, AmendContractRequest
- TrainingController → UpdateCourseRequest, UpdateSessionRequest, UpdateEnrollmentRequest
- RecruitmentController → UpdateJobPostingRequest, StoreInterviewRequest, UpdateInterviewRequest
- ApprovalController → StoreWorkflowRequest, UpdateWorkflowRequest
- WebhookController → StoreWebhookRequest, UpdateWebhookRequest

Batch 2C — Modules admin/paie :
- PayrollRunController → StorePayrollRunRequest
- SalaryStructureController → StoreSalaryStructureRequest, UpdateSalaryStructureRequest
- SalaryComponentController → StoreSalaryComponentRequest, UpdateSalaryComponentRequest
- TaxSlabController → StoreTaxSlabRequest
- SocialContributionController → StoreSocialContributionRequest
```

### Iteration 3 : Transactions et integrite des donnees

**Objectif** : Toute operation multi-write est enveloppee dans `DB::transaction()`.

```
Controllers concernes :
- ContractController::store (contrat + amendment eventuel)
- ContractController::renew (ancien contrat + nouveau)
- ContractController::terminate (contrat + audit)
- RecruitmentController::storeJob + storeApplicant
- TrainingController::storeCourse + storeSession
- ExpenseClaimController::store (claim + items)
- ApprovalController::approve/reject (decision + notification + status update)
- BillingController::upgrade/cancel (subscription + invoice)
- PayrollRunController::calculate (run + pay slips + lines)
- UserAuthController::register (user + link)

Services concernes :
- EmployeeService::create (employee + company settings + notifications)
- AuthService::login (user lookup + token + audit)
```

### Iteration 4 : Catalogue d'erreurs centralise

**Objectif** : Un fichier unique `app/Enums/ApiError.php` (backed enum) avec tous les codes, messages, et status HTTP.

```
Livrables :
- ApiError enum avec ~40 codes (AUTH_*, VALIDATION_*, RBAC_*, TENANT_*, BILLING_*, PAYROLL_*)
- Trait HasApiErrors pour les controllers
- Migration de tous les response()->json(['error' => '...']) vers ApiError::XXX->response()
- Fichier lang/fr/errors.php et lang/en/errors.php et lang/ar/errors.php complets
- Test unitaire verifiant que chaque ApiError a une traduction dans les 3 langues
```

### Iteration 5 : Policies model-level completes

**Objectif** : Chaque modele sensible a une Policy enregistree, et les controllers appellent `$this->authorize()`.

```
Policies a creer/completer :
- AbsencePolicy (employe voit les siennes, manager approuve son equipe)
- DepartmentPolicy (manager cree/modifie, employe lit)
- PositionPolicy (idem department)
- ContractPolicy (manager cree, employe lit le sien)
- LeavePolicy (principal/rh modifie, employe lit)
- TrainingPolicy (existante, verifier couverture)
- RecruitmentPolicy (existante, verifier couverture)
- LoanPolicy (manager approuve, employe demande)
- ExpensePolicy (manager approuve, employe soumet)
- ApprovalPolicy (contexte-dependant)
- ProjectPolicy (manager cree, equipe lit)
- TaskPolicy (assigne lit/modifie, manager supervise)
- AuditLogPolicy (principal/rh/comptable lit)
- WebhookPolicy (principal/rh configure)
- SchedulePolicy, SitePolicy, NotificationPolicy
```

### Iteration 6 : Eager loading et performance N+1

**Objectif** : Eliminer les N+1 queries sur les endpoints list/index.

```
Audit et fix :
- Ajouter ->with() sur tous les index() qui retournent des relations
- Ajouter ->withCount() pour les compteurs (employees_count, absences_count)
- Ajouter select() pour limiter les colonnes retournees sur les listes
- Verifier les exports (ExportController) qui font ->get() sur des tables entieres
- Ajouter des indexes DB si manquants (verifier via EXPLAIN)
- Cache Redis sur les endpoints dashboard/kpi/reports (lecture frequente, mutation rare)
```

### Iteration 7 : Logging metier et audit trail complet

**Objectif** : Chaque action metier critique produit un audit_log avec old/new values.

```
Actions a instrumenter :
- Approbation/rejet absence
- Approbation/rejet pret
- Approbation/rejet note de frais
- Validation/annulation payroll run
- Changement statut contrat (activate/suspend/terminate)
- Changement statut candidat (recrutement pipeline)
- Modification plan tarifaire (billing)
- Desactivation/activation feature flag
- Configuration webhook
- Modification schedule/site/department
```

### Iteration 8 : Hardening securite et rate limiting

**Objectif** : Couverture complete des limiters, headers de securite, et protection CSRF/CORS.

```
Livrables :
- Rate limiter sur exports (eviter le scraping)
- Rate limiter sur social declarations (operations couteuses)
- Rate limiter sur bank exports (operations sensibles)
- Validation stricte des IDs dans les routes (whereNumber sur toutes les routes)
- Verification que tous les endpoints tenant verifient company_id
- Test d'isolation cross-tenant sur les 10 modules les plus sensibles
- Headers CORS restrictifs en production
- Content-Security-Policy sur les vues Blade
```

---

## Ordre d'execution

| Iteration | Effort | Dependance | Impact business |
|---|---|---|---|
| **1 — API Resources** | ~2h | Aucune | CRITIQUE — contrats JSON stables pour mobile/admin |
| **2 — FormRequests** | ~2h | Aucune | HAUTE — validation robuste, doc auto |
| **3 — Transactions** | ~1h | Aucune | CRITIQUE — integrite donnees |
| **4 — Catalogue erreurs** | ~1h | Aucune | HAUTE — UX erreur coherente |
| **5 — Policies** | ~1.5h | Aucune | HAUTE — securite model-level |
| **6 — N+1 / Performance** | ~1h | Aucune | MOYENNE — scalabilite |
| **7 — Audit metier** | ~1h | Aucune | HAUTE — tracabilite pour clients enterprise |
| **8 — Hardening securite** | ~1h | Aucune | CRITIQUE — protection production |

**Total estime : ~10-11h d'implementation**

---

## Definition of Done

L'API est consideree "vendable" quand :

- [ ] Chaque endpoint mutation utilise un FormRequest
- [ ] Chaque endpoint retourne un API Resource (sauf reports/health)
- [ ] Chaque operation multi-write est dans une transaction
- [ ] Le catalogue d'erreurs couvre tous les codes avec traduction FR/EN/AR
- [ ] Les 15 modeles sensibles ont une Policy
- [ ] Aucun N+1 sur les endpoints pagines (verifie via telescope ou query log)
- [ ] Les actions metier critiques produisent un audit log
- [ ] Les exports et operations couteuses ont un rate limiter
- [ ] Les tests existants passent toujours
- [ ] RBAC_ROUTE_MATRIX.md est a jour
