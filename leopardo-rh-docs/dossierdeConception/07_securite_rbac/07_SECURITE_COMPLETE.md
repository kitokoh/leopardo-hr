# SÉCURITÉ COMPLÈTE — LEOPARDO RH
# Version 2.0 | Mars 2026
# CORRIGÉ : national_id chiffré (RGPD), politique revocation token

---

## 1. AUTHENTIFICATION — SANCTUM TOKENS OPAQUES

Leopardo RH utilise **Laravel Sanctum avec tokens opaques** (pas JWT).

| Client | Durée token | Renouvellement |
|--------|-------------|----------------|
| App Flutter | 90 jours | Nouveau token à chaque connexion |
| Navigateur SPA | 8h d'inactivité | Cookie httpOnly SameSite=Strict |
| Super Admin | 4h | 2FA obligatoire |
| ZKTeco | Permanent | Changement manuel si compromis |

---

## 2. CHIFFREMENT DES DONNÉES SENSIBLES

### Données chiffrées en base (AES-256-CBC via Laravel Crypt)

```php
// CHIFFREMENT OBLIGATOIRE sur ces champs :
employees.iban              → Crypt::encryptString($value)
employees.bank_account      → Crypt::encryptString($value)
employees.national_id       → Crypt::encryptString($value)  ← CORRIGÉ v2.0

// POURQUOI national_id est chiffré (même si identifiant administratif) :
// - RGPD (UE/France) : Art. 9 — données personnelles sensibles
// - Loi 18-07 Algérie : protection données à caractère personnel
// - Loi 09-08 Maroc : idem
// - En cas de breach DB : le numéro national ne doit pas être exposé en clair

// CAST dans le modèle Employee :
protected $casts = [
    'iban'         => EncryptedCast::class,
    'bank_account' => EncryptedCast::class,
    'national_id'  => EncryptedCast::class,
];
// → Chiffré automatiquement à l'écriture, déchiffré automatiquement à la lecture
```

---

## 3. PROTECTION BRUTE FORCE

```php
RateLimiter::for('login', function (Request $request) {
    return [
        Limit::perMinutes(15, 5)->by($request->email),      // 5 essais par email
        Limit::perMinutes(15, 5)->by($request->ip()),        // 5 essais par IP
    ];
});
// Blocage 15 min. Déblocage : automatique ou manuel par Super Admin.
```

---

## 4. POLITIQUE DE RÉVOCATION DE TOKEN

```php
// app/Services/SecurityService.php

class SecurityService
{
    // Révocation d'UN appareil (vol, perte)
    public function revokeDevice(Employee $employee, string $deviceName): void
    {
        $employee->tokens()->where('name', $deviceName)->delete();
        // → Cet appareil sera déconnecté à la prochaine requête
    }

    // Révocation de TOUS les appareils (compromission compte)
    public function revokeAllDevices(Employee $employee): void
    {
        $employee->tokens()->delete();
        $this->notificationService->notify($employee, 'security.all_sessions_revoked');
    }

    // Révocation automatique après changement de mot de passe
    public function onPasswordChanged(Employee $employee, string $keepCurrentToken = null): void
    {
        $query = $employee->tokens();
        if ($keepCurrentToken) {
            $query->where('id', '!=', $keepCurrentToken);
        }
        $query->delete();
    }
}
```

---

## 5. PROTECTION XSS / INJECTION

```php
// Toutes les entrées texte passent par FormRequest → validation stricte
// Les ressources API (EmployeeResource) sérialisent via json_encode — pas de HTML brut
// PostgreSQL : requêtes paramétrées via Eloquent (pas de query string concaténée)

// Headers de sécurité dans Nginx :
// X-Frame-Options: DENY
// X-Content-Type-Options: nosniff
// Content-Security-Policy: default-src 'self'
// Referrer-Policy: strict-origin-when-cross-origin
```

---

## 6. ISOLATION MULTITENANCY (sécurité critique)

### Mode SHARED — Tests obligatoires
```php
// tests/Feature/Security/TenantIsolationTest.php
it('un employé de la société A ne peut pas voir les données de la société B', function () {
    $companyA = Company::factory()->shared()->create();
    $companyB = Company::factory()->shared()->create();
    $employeeA = Employee::factory()->for($companyA)->create();
    $employeeB = Employee::factory()->for($companyB)->create();

    // Authentifié comme employé A, tentative d'accès aux données de B via URL manipulation
    $tokenA = $employeeA->createToken('test')->plainTextToken;
    $response = $this->withToken($tokenA)
        ->getJson("/api/v1/employees/{$employeeB->id}");

    $response->assertStatus(404); // Invisible — pas 403 (security by obscurity)
});
```

---

## 7. RGPD ET CONFORMITÉ PAR PAYS

| Pays | Loi applicable | Durée conservation | Remarques |
|------|---------------|-------------------|-----------|
| France | RGPD + Loi Informatique et Libertés | 5 ans paie, 3 ans pointage | DPO recommandé > 250 employés |
| Algérie | Loi 18-07 | 10 ans documents RH | Pas de transfert hors Algérie sans autorisation |
| Maroc | Loi 09-08 | 5 ans | CNDP notification obligatoire |
| Tunisie | Loi organique 2004-63 | 5 ans | Instance nationale INPDP |
| Turquie | KVKK | 10 ans paie | Consentement explicite requis |

### Droit à l'oubli (RGPD)
```php
// Quand une entreprise résilie : Conservation 30 jours, puis suppression
// Mode schema Enterprise : DROP SCHEMA company_{uuid} CASCADE (simple)
// Mode shared : Suppression ligne par ligne avec transaction (voir TenantService)
```
