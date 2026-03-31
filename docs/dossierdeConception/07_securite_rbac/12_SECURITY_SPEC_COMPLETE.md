# SPÉCIFICATION SÉCURITÉ COMPLÈTE — LEOPARDO RH
# Version 1.0 | Mars 2026
# Remplace : security_specification.md (supprimé — incomplet et contradictoire)

---

## 1. AUTHENTIFICATION — SANCTUM TOKENS OPAQUES

**⚠️ Leopardo RH utilise Laravel Sanctum avec des tokens OPAQUES, PAS des JWT.**

### Différence fondamentale
```
JWT (JSON Web Token)     : string encodé base64 contenant des claims. Stateless.
                           Vérifiable sans base de données.
Sanctum token opaque     : string aléatoire (ex: "leopardo_1|AbCdEfGh...")
                           Vérifié en base de données à chaque requête.
                           Révocable instantanément (DELETE FROM personal_access_tokens).
```

**Pourquoi Sanctum et pas JWT ?**
- Révocation immédiate : déconnexion d'un appareil volé = token invalidé en base
- Un token par appareil : l'employé peut voir et révoquer ses sessions
- Plus simple pour SPA + Mobile sans double système d'auth

### Durées de session
| Client | Durée token | Renouvellement |
|--------|-------------|----------------|
| App Flutter (mobile) | 90 jours | À chaque connexion, un nouveau token est créé |
| Navigateur web (SPA) | 8h d'inactivité | Cookie httpOnly SameSite=Strict |
| Super Admin | 4h | Obligatoire avec 2FA |
| Lecteur ZKTeco | Permanent | Changé manuellement en cas de compromission |

### Stockage du token côté client
```
Flutter    → flutter_secure_storage (Keychain iOS / Keystore Android) — chiffré
Web SPA    → Cookie httpOnly SameSite=Strict (inaccessible depuis JavaScript)
ZKTeco     → Configuré directement dans l'interface du lecteur
```

---

## 2. PROTECTION BRUTE FORCE

```
Tentatives login avant blocage : 5
Durée du blocage               : 15 minutes
Scope du blocage               : par adresse IP + par email (les deux indépendants)
Implémentation                 : Laravel RateLimiter (Redis)
Déblocage                      : automatique après 15 min, ou manuel par Super Admin
```

```php
// routes/api.php
RateLimiter::for('login', function (Request $request) {
    return [
        Limit::perMinutes(15, 5)->by($request->email),
        Limit::perMinutes(15, 5)->by($request->ip()),
    ];
});
```

---

## 3. CHIFFREMENT DES DONNÉES SENSIBLES

### Données chiffrées en base
```
employees.iban          → Laravel Crypt::encryptString() (AES-256-CBC)
employees.bank_account  → Laravel Crypt::encryptString() (AES-256-CBC)
employees.national_id   → Laravel Crypt::encryptString() (AES-256-CBC)
                          ⚠️  OBLIGATOIRE — conformité légale :
                          · RGPD (UE/France) Art. 9 : donnée personnelle sensible
                          · Loi 18-07 Algérie : protection des données personnelles
                          · Loi 09-08 Maroc   : idem
                          En cas de breach DB : le numéro national NE DOIT PAS être exposé en clair
```

### Cast dans le modèle Employee (les 3 champs)
```php
// app/Models/Tenant/Employee.php
protected $casts = [
    'iban'         => EncryptedCast::class,  // Crypt::encryptString automatique
    'bank_account' => EncryptedCast::class,
    'national_id'  => EncryptedCast::class,  // ← chiffré depuis v3.3.0
];
// → Chiffré automatiquement à l'écriture, déchiffré automatiquement à la lecture
// → Recherche par national_id impossible en DB (prévoir cache ou index hashed si besoin)
```

### Clé de chiffrement
```
APP_KEY dans .env → générée par php artisan key:generate
Longueur : 32 bytes aléatoires en base64
Rotation : annuelle (avec re-chiffrement des données existantes)
```

### Mots de passe
```
Algorithme : bcrypt avec coût ≥ 12
Implémentation : Hash::make() Laravel (utilise bcrypt automatiquement)
Vérification : Hash::check() — jamais de comparaison directe de chaînes
```

---

## 4. HTTPS / TLS

```
Protocole minimum : TLS 1.2 (TLS 1.3 préféré)
Certificat        : Let's Encrypt via Certbot (renouvellement auto)
HSTS              : Strict-Transport-Security: max-age=31536000; includeSubDomains
Headers sécurité  :
  - X-Frame-Options: SAMEORIGIN
  - X-Content-Type-Options: nosniff
  - Referrer-Policy: strict-origin-when-cross-origin
  - Content-Security-Policy: (configurer selon besoins)
```

---

## 5. RBAC ET AUTORISATION

Voir `10_RBAC_COMPLET.md` pour la matrice complète.

### Implémentation
```
Middleware  : TenantMiddleware → SetLocale → RoleMiddleware → PolicyCheck
Policies   : EmployeePolicy, AttendancePolicy, AbsencePolicy, PayrollPolicy, TaskPolicy
Règle     : Vérification côté serveur systématique — jamais se fier au client
```

### Isolation tenant dans les Policies
```php
// Toujours vérifier que la ressource appartient au tenant actif
// (le search_path PostgreSQL garantit l'isolation, mais les Policies ajoutent une couche)
public function update(Employee $authUser, Employee $targetEmployee): bool
{
    // Le fait que targetEmployee existe (findOrFail) dans ce schéma
    // garantit déjà qu'il appartient à ce tenant.
    // La Policy vérifie ensuite les permissions de rôle.
    return $authUser->role === 'manager'
        && in_array($authUser->manager_role, ['principal', 'rh']);
}
```

---

## 6. JOURNAL D'AUDIT

### Ce qui est tracé automatiquement (via Observers)
```
employee.created        employee.updated        employee.archived
absence.submitted       absence.approved        absence.rejected
advance.submitted       advance.approved        advance.rejected
payroll.calculated      payroll.validated
attendance.corrected    (toute correction manuelle de pointage)
settings.updated        (tout changement de paramètre entreprise)
auth.login              auth.logout             auth.failed_attempt
```

### Structure d'un log
```
actor_type  : 'employee' | 'super_admin' | 'system'
actor_id    : ID de l'auteur (employees.id ou super_admins.id)
actor_name  : "Ahmed Benali" — dénormalisé pour l'affichage
action      : "employee.updated"
table_name  : "employees"
record_id   : "42"
old_values  : {"salary_base": 75000}  (champs modifiés uniquement)
new_values  : {"salary_base": 80000}
ip          : "197.200.x.x"
user_agent  : "Mozilla/5.0..."
```

### Rétention et purge
```
Rétention : 24 mois minimum
Purge auto : commande Artisan `audit:purge --older-than=24months`
             Planifiée en cron le 1er de chaque mois
```

---

## 7. VALIDATION DES DONNÉES

```
Règle absolue  : Validation TOUJOURS côté serveur (FormRequest Laravel)
                 La validation côté Flutter est UX uniquement — pas de sécurité
FormRequest   : 1 par action (StoreEmployeeRequest, CheckInRequest, etc.)
Sanitization  : strip_tags sur les champs texte libres
Injection SQL : Eloquent ORM avec bindings préparés — jamais de raw SQL avec input user
XSS           : Vue.js échappe automatiquement — pas de v-html sur données API
CSRF          : Token Laravel pour web SPA — Mobile utilise Bearer token (immunisé)
```

---

## 8. RATE LIMITING API

```
Default     : 60 requêtes/minute par token Bearer
Login       : 5 tentatives/15min par IP + email
Payroll     : 5 appels/heure par tenant (calcul paie coûteux)
Export      : 10 exports/heure par tenant
Webhook ZKT : 1000 requêtes/minute par device token (pointages en rafale)
```

---

## 9. DONNÉES BIOMÉTRIQUES (ZKTeco)

**Leopardo RH ne stocke AUCUNE donnée biométrique.**

```
Ce que le lecteur ZKTeco stocke    : empreintes digitales (en local dans le lecteur)
Ce que Leopardo RH reçoit          : employee_zkteco_id + timestamp + direction
Ce que Leopardo RH stocke          : employee_id (interne) + timestamp + statut
Les empreintes digitales           : jamais transmises, jamais stockées
```

---

## 10. SAUVEGARDES

```
Dump PostgreSQL complet   : quotidien à 00h30 UTC
Dump incrémental          : toutes les heures
Fichiers (storage/)       : quotidien
Rétention quotidienne     : 30 jours
Rétention hebdomadaire    : 3 mois
Stockage sauvegarde       : volume séparé du serveur principal
Test de restauration      : mensuel automatisé (cron qui restaure + vérifie l'intégrité)
```

---

## 11. CONFORMITÉ RGPD

```
Droit à l'effacement     : employees.status = 'archived' + anonymisation des données personnelles
                           (pas de suppression physique — les bulletins de paie doivent être conservés)
Droit à la portabilité   : Export ZIP de toutes les données d'un employé (endpoint Super Admin)
Politique de confidentialité : Affichée lors de la première connexion
Consentement             : Enregistré dans employees.email_verified_at (acceptation des CGU)
DPO contact              : Configurable dans les paramètres Super Admin
```

---

## 12. SÉCURITÉ MOBILE (Flutter)

```
Token stockage     : flutter_secure_storage uniquement (jamais SharedPreferences)
Certificate pinning: SSL pinning recommandé en production (flutter_certificate_pinner)
Biométrie téléphone: Face ID / empreinte pour déverrouiller l'app (local_auth)
                     NE PAS confondre avec le biométrique ZKTeco
Obfuscation        : flutter build apk --obfuscate --split-debug-info=.symbols/
Jailbreak/Root     : Détecter et avertir (flutter_jailbreak_detection) — pas bloquer
Capture d'écran    : Désactiver sur les écrans sensibles (bulletins, salaires)
```