# PATCH CC-03 — Chiffrement données sensibles Employee
# À appliquer PENDANT CC-03, lors de la création du modèle Employee
# Réf : 07_SECURITE_COMPLETE.md §2 — Obligation légale RGPD / Loi 18-07 DZ / 09-08 MA

---

## POURQUOI C'EST CRITIQUE

Ces 3 champs DOIVENT être chiffrés AES-256 en base :
- `employees.iban` — numéro de compte bancaire
- `employees.bank_account` — numéro de compte alternatif
- `employees.national_id` — numéro d'identité nationale

**Risque si non fait :** en cas de breach de la base de données PostgreSQL,
tous les numéros d'identité et IBAN de tous vos clients sont exposés en clair.
Obligation légale en France (RGPD Art.9), Algérie (Loi 18-07), Maroc (Loi 09-08).

---

## MODÈLE EMPLOYEE — CASTS OBLIGATOIRES

```php
// app/Models/Tenant/Employee.php

namespace App\Models\Tenant;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Employee extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $fillable = [
        'matricule', 'first_name', 'last_name', 'email', 'password',
        'phone', 'hire_date', 'contract_type', 'contract_end_date',
        'department_id', 'position_id', 'schedule_id', 'site_id',
        'role', 'manager_role', 'salary_base', 'currency',
        'leave_balance', 'status', 'country', 'badge_number',
        // Champs sensibles — chiffrés automatiquement via casts
        'national_id', 'iban', 'bank_account', 'bank_name',
    ];

    protected $hidden = [
        'password',
        // national_id et iban ne sont PAS dans $hidden —
        // ils sont chiffrés en base mais lisibles dans le code (déchiffrement auto)
        // Ils sont exclus des API Resources par défaut (voir EmployeeResource)
    ];

    protected $casts = [
        'hire_date'         => 'date',
        'contract_end_date' => 'date',
        'salary_base'       => 'decimal:2',
        'leave_balance'     => 'decimal:2',
        'password'          => 'hashed',

        // CHIFFREMENT AES-256-CBC — Laravel Crypt (clé = APP_KEY)
        'national_id'   => 'encrypted',   // ← Déchiffré auto à la lecture
        'iban'          => 'encrypted',   // ← Chiffré auto à l'écriture
        'bank_account'  => 'encrypted',   // ← Transparent pour le code
    ];
}
```

---

## EMPLOYEERESOURCE — CONTRÔLE D'ACCÈS AUX CHAMPS SENSIBLES

Les champs sensibles ne doivent pas apparaître dans toutes les réponses API.
Seuls le gestionnaire Principal, RH, Comptable et l'employé lui-même peuvent les voir.

```php
// app/Http/Resources/EmployeeResource.php

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user         = $request->user();
        $isSelf       = $user->id === $this->id;
        $canSeeSalary = in_array($user->manager_role, ['principal', 'rh', 'comptable'])
                        || $isSelf;
        $canSeeIban   = in_array($user->manager_role, ['principal', 'rh', 'comptable']);

        return [
            'id'             => $this->id,
            'matricule'      => $this->matricule,
            'first_name'     => $this->first_name,
            'last_name'      => $this->last_name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'role'           => $this->role,
            'manager_role'   => $this->manager_role,
            'department'     => new DepartmentResource($this->whenLoaded('department')),
            'position'       => new PositionResource($this->whenLoaded('position')),
            'hire_date'      => $this->hire_date?->toDateString(),
            'contract_type'  => $this->contract_type,
            'leave_balance'  => $this->leave_balance,
            'status'         => $this->status,
            'photo_url'      => $this->photo_url,

            // Champs sensibles — conditionnels selon le rôle
            'salary_base'    => $this->when($canSeeSalary, $this->salary_base),
            'iban'           => $this->when($canSeeIban, $this->iban),
            'bank_account'   => $this->when($canSeeIban, $this->bank_account),
            'national_id'    => $this->when($isSelf || $canSeeIban, $this->national_id),
        ];
    }
}
```

---

## OBSERVER — EXCLURE LES CHAMPS SENSIBLES DES AUDIT LOGS

Les champs chiffrés ne doivent jamais être loggés dans `audit_logs`,
même en version chiffrée (le hash chiffré changerait à chaque écriture et polluerait les logs).

```php
// app/Observers/EmployeeObserver.php

class EmployeeObserver
{
    // Champs JAMAIS loggés dans audit_logs
    private array $neverLog = [
        'password',
        'national_id',
        'iban',
        'bank_account',
    ];

    public function updated(Employee $employee): void
    {
        $dirty = collect($employee->getDirty())
            ->except($this->neverLog)
            ->toArray();

        if (empty($dirty)) return;

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'employee.updated',
            'table_name' => 'employees',
            'record_id'  => $employee->id,
            'old_values' => collect($employee->getOriginal())
                ->only(array_keys($dirty))
                ->except($this->neverLog)
                ->toArray(),
            'new_values' => $dirty,
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

---

## MIGRATION — Vérifier la longueur des colonnes

Les données chiffrées AES-256 sont plus longues que les valeurs originales.
Un IBAN de 27 caractères devient ~100 caractères après chiffrement.

```php
// database/migrations/tenant/..._create_employees_table.php

// CORRECT — colonnes assez larges pour le chiffrement :
$table->text('national_id')->nullable();    // text, pas varchar(20)
$table->text('iban')->nullable();           // text, pas varchar(34)
$table->text('bank_account')->nullable();   // text, pas varchar(30)
```

---

## TEST OBLIGATOIRE

```php
// tests/Feature/Security/EncryptionTest.php

it('national_id is stored encrypted in database', function () {
    $company  = Company::factory()->withSchema()->create();
    $employee = Employee::factory()->inSchema($company)->create([
        'national_id' => '123456789012345678',
    ]);

    // Valeur dans le modèle → déchiffrée automatiquement
    expect($employee->national_id)->toBe('123456789012345678');

    // Valeur brute en base → chiffrée (doit être différente de la valeur originale)
    $raw = DB::selectOne(
        "SELECT national_id FROM employees WHERE id = ?",
        [$employee->id]
    );
    expect($raw->national_id)->not->toBe('123456789012345678');
    expect(strlen($raw->national_id))->toBeGreaterThan(30); // chiffré = plus long
});

it('iban is stored encrypted and decrypted correctly', function () {
    $company  = Company::factory()->withSchema()->create();
    $employee = Employee::factory()->inSchema($company)->create([
        'iban' => 'DZ590123456789012345678901',
    ]);

    $loaded = Employee::find($employee->id);
    expect($loaded->iban)->toBe('DZ590123456789012345678901');
});

it('sensitive fields are excluded from audit logs', function () {
    $company  = Company::factory()->withSchema()->create();
    $manager  = Employee::factory()->inSchema($company)->managerPrincipal()->create();
    $employee = Employee::factory()->inSchema($company)->create([
        'national_id' => 'SENSITIVE_ID',
        'salary_base' => 50000,
    ]);

    $this->actingAs($manager);
    $employee->update(['national_id' => 'NEW_SENSITIVE_ID', 'salary_base' => 55000]);

    $log = AuditLog::where('record_id', $employee->id)->latest()->first();

    expect($log)->not->toBeNull();
    expect(isset($log->new_values['national_id']))->toBeFalse(); // exclu
    expect($log->new_values['salary_base'])->toBe(55000);        // inclus
});

it('employee cannot see other employee iban via API', function () {
    $company   = Company::factory()->withSchema()->create();
    $employeeA = Employee::factory()->inSchema($company)->create();
    $employeeB = Employee::factory()->inSchema($company)->create(['iban' => 'DZ59xxx']);
    $token     = $employeeA->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
         ->getJson("/api/v1/employees/{$employeeB->id}");

    $response->assertStatus(200);
    expect($response->json('data.iban'))->toBeNull(); // champ absent de la réponse
});
```
