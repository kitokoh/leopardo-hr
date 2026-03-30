# PROMPT MAÎTRE — CLAUDE CODE
# Leopardo RH | Backend Laravel 11 · PostgreSQL 16 · Vue.js 3
# Version 2.0 | Mars 2026 — MULTITENANCY HYBRIDE

---

## CONTEXTE ET MISSION

Tu développes le backend de **Leopardo RH**, un SaaS RH multilingue multi-entreprises.
Stack : Laravel 11 (PHP 8.3) + PostgreSQL 16 + Vue.js 3 (Inertia) + Redis + Flutter (mobile, parallèle).

---

## DOCUMENTS À LIRE AVANT DE CODER

| Priorité | Document | Pourquoi |
|----------|----------|---------|
| 1 | `08_multitenancy/08_MULTITENANCY_STRATEGY.md` | Architecture fondamentale |
| 2 | `04_architecture_erd/04_ERD_COMPLET.md` | Toutes les tables + ordre migrations |
| 3 | `06_api_contrats/06_API_CONTRATS_COMPLETS.md` | 70 endpoints avec payloads exacts |
| 4 | `05_regles_metier/05_REGLES_METIER.md` | Règles paie, pointage, congés |
| 5 | `07_securite_rbac/10_RBAC_COMPLET.md` | Permissions par rôle |

---

## MULTITENANCY HYBRIDE — RÈGLE ABSOLUE

### Deux modes coexistent :

**Mode SHARED (Trial / Starter / Business)** :
- `search_path` → `shared_tenants`
- Global Scope `CompanyScope` actif → `WHERE company_id` injecté automatiquement
- Les modèles ont un champ `company_id` en DB

**Mode SCHEMA (Enterprise)** :
- `search_path` → `company_{uuid}`
- Aucun Global Scope → isolation physique par le schéma
- Les modèles n'ont PAS de `company_id` en DB

### LE MIDDLEWARE FAIT TOUT — les Controllers ne savent rien

```php
// ✅ CORRECT dans TOUS les Controllers (fonctionne dans les deux modes)
Employee::all();
Employee::where('department_id', $id)->get();
Employee::create(['first_name' => 'Ahmed', ...]);

// ❌ INTERDIT — le middleware s'en occupe
Employee::where('company_id', $id)->get();
DB::statement("SET search_path TO ...");
```

### Trait obligatoire sur tous les modèles Tenant

```php
use App\Traits\HasCompanyScope;  // ← sur TOUS les modèles dans app/Models/Tenant/
```

---

## ORDRE DES MIGRATIONS (NE PAS CHANGER)

```
01 departments    (sans manager_id)
02 positions
03 schedules
04 sites
05 employees
06 ALTER TABLE departments ADD COLUMN manager_id   ← APRÈS employees
07 devices
08 attendance_logs  ...  21 notifications
```

---

## RÈGLES DE CODE LARAVEL (non négociables)

1. **Test-first** : écrire le test Pest AVANT le Controller
2. **FormRequest** : toute validation dans un FormRequest, jamais dans le Controller
3. **Service Layer** : la logique métier dans les Services (PayrollService, AttendanceService...)
4. **DB::transaction()** : obligatoire pour les opérations multi-tables (paie, congés, avances)
5. **Timestamps serveur** : JAMAIS utiliser le timestamp client pour le pointage — toujours `now()`
6. **Check-out = UPDATE** : JAMAIS un INSERT (contrainte UNIQUE employee_id + date)
7. **i18n** : TOUTES les chaînes via `__('messages.key')` — jamais de string en dur
8. **Cache Redis** : tagger par tenant UUID pour invalidation propre

---

## CHIFFREMENT DONNÉES SENSIBLES

```php
// Ces 3 champs DOIVENT utiliser EncryptedCast
protected $casts = [
    'iban'         => EncryptedCast::class,
    'bank_account' => EncryptedCast::class,
    'national_id'  => EncryptedCast::class,  // ← RGPD — pas d'exception
];
```

---

## FORMAT RÉPONSE API (standard strict)

```php
// Liste
return response()->json(['data' => Resource::collection($items), 'meta' => [...]]);

// Ressource unique
return response()->json(['data' => new Resource($item), 'message' => __('...')], 201);

// Erreur métier
return response()->json(['error' => 'INSUFFICIENT_LEAVE_BALANCE', 'message' => __('...')], 422);
```

---

## ORDRE D'EXÉCUTION MVP

1. Infrastructure (Laravel init, PostgreSQL multi-schéma, Redis, Sanctum, i18n)
2. TenantMiddleware + CompanyScope + HasCompanyScope trait
3. Migrations public + shared_tenants
4. Auth (login, logout, me, refresh)
5. Module Employés + RBAC
6. Module Pointage + AttendanceService
7. Module Absences + AbsenceService
8. Module Paie + PayrollService
9. Notifications + Jobs async
10. Interface Vue.js
