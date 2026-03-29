# STRATÉGIE MULTI-TENANT — LEOPARDO RH
# Version 1.0 | Mars 2026
# Remplace : multitenancy_strategy.md (supprimé — contenait une architecture erronée)

---

## STRATÉGIE RETENUE : MULTI-SCHÉMA POSTGRESQL

**⚠️ DÉCISION FIGÉE — NE PAS MODIFIER**

La stratégie est **Multi-schéma PostgreSQL**, PAS "Single Database + tenant_id".
Ces deux approches sont mutuellement exclusives. Le choix est définitif.

---

## POURQUOI MULTI-SCHÉMA ET PAS tenant_id ?

| Critère | Multi-schéma (retenu) | Shared table + tenant_id |
|---------|-----------------------|--------------------------|
| Isolation des données | Garantie par PostgreSQL | Dépend des WHERE — risque de fuite |
| Complexité du code | Middleware unique | WHERE tenant_id sur chaque requête |
| Sauvegarde par tenant | `pg_dump schema_name` simple | Extraction complexe |
| Performance | Pas de filtre global | Filtre systématique sur toutes les tables |
| Migration d'un tenant | Déplaçable indépendamment | Très complexe |
| RGPD (suppression) | `DROP SCHEMA` | Suppression ligne par ligne |

---

## ARCHITECTURE CONCRÈTE

```
Base de données : leopardo_db (PostgreSQL 16)

┌─────────────────────────────────────────┐
│ Schéma PUBLIC (partagé)                 │
│ - companies                             │
│ - plans                                 │
│ - super_admins                          │
│ - invoices                              │
│ - billing_transactions                  │
│ - languages                             │
│ - hr_model_templates                    │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ Schéma company_a1b2c3d4 (Entreprise A)  │
│ - employees                             │
│ - attendance_logs                       │
│ - absences   ... (20 tables)            │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ Schéma company_e5f6g7h8 (Entreprise B)  │
│ - employees                             │
│ - attendance_logs                       │
│ - absences   ... (20 tables)            │
└─────────────────────────────────────────┘
```

---

## IMPLÉMENTATION LARAVEL

### TenantMiddleware (pièce centrale)
```php
// app/Http/Middleware/TenantMiddleware.php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user(); // résolu par Sanctum

    // Récupérer le schema_name de l'entreprise de l'employé
    $company = Company::find($user->company_id); // depuis le schéma PUBLIC
    // Note : employees.company_id est résolu différemment (voir ci-dessous)

    // Switcher le search_path PostgreSQL
    DB::statement("SET search_path TO {$company->schema_name}, public");

    // Setter la locale pour l'i18n
    App::setLocale($company->language);

    return $next($request);
}
```

### Comment résoudre company_id depuis le token Sanctum ?
```php
// Le token Sanctum stocke tokenable_id = employees.id
// MAIS employees.id est dans un schéma tenant qu'on ne connaît pas encore

// Solution : stocker company_id dans la table sanctum_tokens (schéma public)
// OU : utiliser un token custom qui encode company_id + employee_id

// Approche recommandée : ajouter company_id à personal_access_tokens
// Migration dans le schéma public :
// ALTER TABLE personal_access_tokens ADD COLUMN company_id UUID REFERENCES companies(id);

// Lors de la création du token (AuthController) :
$token = $employee->createToken($request->device_name);
// Ajouter company_id au token :
DB::table('personal_access_tokens')
    ->where('id', $token->accessToken->id)
    ->update(['company_id' => $companyId]);
```

### Création d'un schéma tenant
```php
// app/Services/TenantService.php
public function createTenantSchema(Company $company): void
{
    DB::transaction(function () use ($company) {
        // 1. Appeler la fonction PostgreSQL
        DB::statement("SELECT create_tenant_schema('{$company->schema_name}')");

        // 2. Switcher vers le nouveau schéma
        DB::statement("SET search_path TO {$company->schema_name}");

        // 3. Insérer les paramètres par défaut
        $this->seedDefaultSettings($company);

        // 4. Appliquer le modèle RH du pays
        $this->applyHRModel($company->country);

        // 5. Insérer les types d'absence standards
        $this->seedAbsenceTypes();

        // 6. Insérer le planning de travail par défaut
        $this->seedDefaultSchedule();
    });
}
```

### Règle absolue dans tous les Models tenant
```php
// ❌ INTERDIT — jamais ce pattern dans un model tenant
Employee::where('company_id', $companyId)->get();

// ✅ CORRECT — le search_path est déjà switché par TenantMiddleware
Employee::all();
Employee::where('department_id', $deptId)->get();
Employee::find($id); // automatiquement dans le bon schéma
```

---

## SÉCURITÉ DE L'ISOLATION

### Ce qui garantit l'isolation
1. `SET search_path TO company_{uuid}` : PostgreSQL ne peut pas "voir" les autres schémas
2. TenantMiddleware vérifié sur TOUTES les routes tenant (pas seulement certaines)
3. Sanctum + company_id dans le token : impossible d'usurper le schéma d'une autre entreprise
4. Tests automatiques : un test Pest vérifie qu'un token de l'entreprise A ne peut pas accéder aux données de l'entreprise B

### Test d'isolation obligatoire (Pest)
```php
it('isole les données entre deux tenants', function () {
    // Créer deux entreprises avec leurs schémas
    $companyA = Company::factory()->withSchema()->create();
    $companyB = Company::factory()->withSchema()->create();

    // Créer un employé dans chaque entreprise
    $employeeA = Employee::factory()->forCompany($companyA)->create();
    $employeeB = Employee::factory()->forCompany($companyB)->create();

    // Authentifier en tant qu'employé A
    $tokenA = $employeeA->createToken('test')->plainTextToken;

    // Tenter d'accéder aux données de B depuis le token A
    $response = $this->withToken($tokenA)
        ->getJson("/api/v1/employees/{$employeeB->id}");

    // Doit être 404 (employé B invisible depuis le schéma de A)
    $response->assertStatus(404);
});
```

---

## SCALABILITÉ

### Limites actuelles (o2switch VPS)
- PostgreSQL supporte des centaines de schémas sans dégradation notable
- Jusqu'à ~200 entreprises actives simultanément sans optimisation supplémentaire
- Au-delà : migration vers un cluster PostgreSQL ou des bases dédiées par région

### GESTION DES MIGRATIONS DE SCHÉMA (MÉTHODE)

Dans une architecture multi-schéma, les migrations standard de Laravel (`php artisan migrate`) ne touchent que le schéma par défaut (public). Pour mettre à jour tous les schémas tenants lors d'une évolution du code :

**Stratégie de migration groupée :**
```php
// app/Console/Commands/TenantsMigrate.php
public function handle()
{
    $companies = Company::where('status', '!=', 'archived')->get();

    foreach ($companies as $company) {
        $this->info("Migrating tenant: {$company->name}");

        // Switch schema
        DB::statement("SET search_path TO {$company->schema_name}, public");

        // Exécuter les fichiers de migration spécifiques aux tenants
        // Note : On peut utiliser une table 'migrations' propre à chaque schéma
        // ou gérer les versions manuellement via TenantService.
    }
}
```

**Règles de sécurité pour les migrations :**
1. **Zéro temps d'arrêt** : Toujours ajouter des colonnes (nullable) ou des tables. Ne jamais supprimer/renommer de colonne sans une phase de transition.
2. **Atomicité** : Chaque migration de tenant doit être enveloppée dans une transaction.
3. **Journalisation** : Tracer le succès/échec de chaque migration par tenant dans les logs de déploiement.

### MIGRATION VERS BASE DÉDIÉE (Futur)
Si un client enterprise nécessite une base dédiée :
1. `pg_dump company_{uuid}`
2. Restaurer dans une base dédiée
3. Mettre à jour `companies.db_connection` dans le schéma public
4. `TenantMiddleware` utilise la connexion dédiée pour ce tenant

Cette migration est transparente pour l'application Laravel.