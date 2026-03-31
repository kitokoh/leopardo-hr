# ERREURS ET LOGGING — LEOPARDO RH
# Version 1.1 | Mars 2026 — audit_logs + timezone + rétention

---

## 1. FORMAT DE RÉPONSE D'ERREUR API

Toutes les erreurs retournent un objet JSON standardisé :

```json
{
  "error": "CODE_ERREUR_MAJUSCULE",
  "message": "Description lisible par l'humain (traduite)",
  "details": { "champ": ["La validation a échoué"] }
}
```

### Codes HTTP recommandés :
- **400 Bad Request** : Requête malformée.
- **401 Unauthorized** : Token manquant ou expiré.
- **403 Forbidden** : Permission insuffisante (RBAC).
- **404 Not Found** : Ressource inexistante.
- **409 Conflict** : État invalide (ex: déjà pointé).
- **422 Unprocessable Entity** : Échec de validation (FormRequest).
- **429 Too Many Requests** : Rate limit atteint.
- **500 Internal Server Error** : Erreur PHP/DB imprévue.

---

## 2. NIVEAUX DE LOGGING (SERVER)

Nous utilisons les niveaux PSR-3 via `Log::` :

- **EMERGENCY / ALERT** : Système instable (DB down, Redis down). *Notification Super Admin immédiate.*
- **ERROR** : Exception non gérée dans le code. *Tracé dans Sentry/Bugsnag.*
- **WARNING** : Tentative d'accès interdit, anomalies de pointage suspectes.
- **INFO** : Connexion utilisateur, validation de paie, export bancaire.
- **DEBUG** : Détails des requêtes (uniquement en local).

---

## 3. LOGS APPLICATIFS (AUDIT)

Ne pas confondre avec les logs système. Les **Audit Logs** sont stockés en base de données (Table `audit_logs`) et consultables par les administrateurs. Ils tracent **qui** a fait **quoi** sur **quelle ressource** (ex: modification de salaire).

### 3.1 Stratégie de remplissage — Observer Eloquent

Le remplissage de la table `audit_logs` se fait via un **Observer Eloquent centralisé** (`app/Observers/AuditObserver.php`). Cet observer est attaché à chaque modèle métier concerné. Il n'est PAS activé sur les lectures (show/index) — uniquement sur les modifications (create, update, delete).

**Modèles observés :**

| Modèle | Actions tracées | Détails |
|--------|----------------|---------|
| `Employee` | create, update, delete (archive) | `old_values` contient les champs modifiés, `new_values` les nouvelles valeurs |
| `SalaryAdvance` | create, update (status change) | Tracer chaque changement de statut (pending → approved → active → repaid) |
| `Absence` | create, update, delete (cancel) | Tracer approbation/refus/annulation |
| `Payroll` | create, update (validate) | Tracer validation bulletin + PDF généré |
| `CompanySetting` | update | Tracer toute modification de paramètre entreprise |
| `AttendanceLog` | create, update (manual edit) | Tracer uniquement les modifications manuelles (is_manual_edit = true) |
| `Evaluation` | create, update | Tracer création et auto-évaluation |

**Ce qui n'est PAS tracé :**
- Les lectures (GET endpoints) — éviterait une explosion de la table
- Les logins réussis (déjà dans `super_admins.last_login_at` et `Log::info`)
- Les pings de santé (health check)
- Les requêtes de listing paginé

**Implémentation de l'Observer :**

```php
// app/Observers/AuditObserver.php
class AuditObserver
{
    private function log($model, string $action): void
    {
        if (!auth()->check()) return;

        $changes = $model->getDirty();
        // Ne pas logger si aucun champ notable n'a changé (ex: updated_at seul)
        if ($action === 'updated' && empty(array_diff(array_keys($changes), ['updated_at']))) {
            return;
        }

        AuditLog::create([
            'actor_type'  => auth()->user() instanceof SuperAdmin ? 'super_admin' : 'employee',
            'actor_id'    => auth()->id(),
            'actor_name'  => auth()->user()->name ?? auth()->user()->full_name ?? 'Système',
            'action'      => strtolower(class_basename($model)) . '.' . $action,
            'table_name'  => $model->getTable(),
            'record_id'   => (string) $model->id,
            'old_values'  => $action === 'updated' ? $model->getOriginal() : null,
            'new_values'  => $action !== 'deleted' ? $changes : null,
            'ip'          => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }

    public function created($model): void { $this->log($model, 'created'); }
    public function updated($model): void { $this->log($model, 'updated'); }
    public function deleted($model): void { $this->log($model, 'deleted'); }
}
```

**Enregistrement dans `AppServiceProvider` :**

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Employee::observe(AuditObserver::class);
    SalaryAdvance::observe(AuditObserver::class);
    Absence::observe(AuditObserver::class);
    Payroll::observe(AuditObserver::class);
    CompanySetting::observe(AuditObserver::class);
    AttendanceLog::observe(AuditObserver::class);
    Evaluation::observe(AuditObserver::class);
}
```

### 3.2 Rétention et purge automatique

Les `audit_logs` ont une rétention de **24 mois**. Au-delà, les entrées sont purgées automatiquement via une commande Artisan planifiée en cron.

```bash
# php artisan audit:purge --months=24
# À planifier dans le scheduler Laravel :
$schedule->command('audit:purge --months=24')->monthlyOn(1, '03:00');
```

### 3.3 Exclusion des champs sensibles

Les `old_values` et `new_values` de l'audit log ne doivent **jamais** contenir de données sensibles. L'Observer exclut automatiquement ces champs :

```php
private array $sensitiveFields = ['password_hash', 'national_id', 'iban', 'bank_account', 'two_fa_secret'];

private function filterSensitive(array $data): array
{
    return array_diff_key($data, array_flip($this->sensitiveFields));
}
```

---

## 4. GESTION DES TIMEZONES DANS LES CALCULS

### 4.1 Problème

Les entreprises utilisent des timezones différentes (ex: `Africa/Algiers`, `Europe/Paris`, `Europe/Istanbul`). Les horodatages de pointage (`check_in`, `check_out`) sont stockés en **TIMESTAMPTZ UTC** dans PostgreSQL. Mais les plannings de travail (`schedules.start_time`, `schedules.end_time`) sont stockés en **TIME sans timezone**. Le calcul de retard et d'heures supplémentaires nécessite une conversion explicite.

### 4.2 Règle de conversion

```php
// app/Services/AttendanceService.php

// 1. Récupérer la timezone de l'entreprise
$timezone = $employee->company->timezone; // ex: "Africa/Algiers"

// 2. Convertir le TIMESTAMPTZ UTC en heure locale pour le comparatif
$checkInLocal = $attendanceLog->check_in->setTimezone($timezone);

// 3. Comparer avec le planning (TIME stocké en heure locale)
$scheduleStart = Carbon::parse($schedule->start_time, $timezone);
$lateThreshold = $scheduleStart->copy()->addMinutes($schedule->late_tolerance_minutes);

// 4. Déterminer le statut
$status = $checkInLocal->lte($lateThreshold) ? 'ontime' : 'late';
```

### 4.3 Règles obligatoires pour les développeurs

- **Toujours** stocker les timestamps en UTC dans la base (Laravel `->save()` le fait automatiquement si `config('app.timezone') = 'UTC'`).
- **Toujours** convertir en heure locale de l'entreprise avant d'afficher ou de comparer avec un planning.
- **Jamais** utiliser `now()` brut pour un calcul métier — utiliser `now($company->timezone)`.
- Les dates affichées dans l'API sont **toujours** au format ISO 8601 avec le décalage UTC (`Z`).
- L'app Flutter reçoit les dates UTC et les convertit côté client via la timezone de l'entreprise (récupérée dans `/auth/me`).

### 4.4 Impact sur les rapports

Les rapports mensuels (pointage, absences, paie) doivent calculer les jours ouvrés et les heures dans la timezone de l'entreprise, pas en UTC. Un employé qui pointe le 1er avril à 23h30 à Istanbul stocke le 1er avril 20h30 UTC — mais c'est bien le 1er avril dans son timezone locale.
