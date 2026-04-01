# PATCH TRANSVERSAL — i18n complète + Notifications + State machines + UML
# À lire AVANT de commencer CC-01, et à relire à chaque module
# Réf : 11_I18N_STRATEGIE_COMPLETE.md + 14_NOTIFICATION_TEMPLATES.md + 05_STATE_MACHINES.md

---

## 1. STRATÉGIE i18n COMPLÈTE

Réf : `docs/dossierdeConception/13_i18n/11_I18N_STRATEGIE_COMPLETE.md`

### Structure des fichiers de traduction Laravel

```
api/lang/
├── fr/
│   ├── auth.php          ← Messages auth
│   ├── errors.php        ← Codes d'erreur métier → phrase lisible
│   ├── notifications.php ← Titres et corps push/email
│   ├── pdf.php           ← Labels bulletins de paie
│   └── validation.php    ← Validation FormRequests
├── ar/   (même structure — textes RTL)
├── en/   (même structure)
└── tr/   (même structure)
```

### lang/fr/errors.php — liste COMPLÈTE des codes d'erreur

```php
return [
    // Auth
    'INVALID_CREDENTIALS'           => 'Email ou mot de passe incorrect.',
    'ACCOUNT_SUSPENDED'             => 'Compte suspendu. Contactez votre administrateur.',
    'SUBSCRIPTION_EXPIRED'          => "L'abonnement de votre entreprise a expiré.",
    'GRACE_PERIOD_READ_ONLY'        => 'Période de grâce : lecture seule jusqu\'au renouvellement.',
    'UNAUTHENTICATED'               => 'Session expirée. Veuillez vous reconnecter.',
    'FORBIDDEN_NOT_SUPER_ADMIN'     => 'Accès réservé aux Super Administrateurs.',

    // Employés
    'EMPLOYEE_NOT_FOUND'            => 'Employé introuvable.',
    'PLAN_EMPLOYEE_LIMIT_REACHED'   => 'Limite de :max employés atteinte pour le plan :plan.',

    // Pointage
    'ALREADY_CHECKED_IN'            => 'Vous avez déjà pointé votre arrivée à :time.',
    'MISSING_CHECK_IN'              => "Aucun pointage d'arrivée trouvé pour aujourd'hui.",
    'CHECKOUT_BEFORE_CHECKIN'       => "L'heure de départ ne peut pas précéder l'arrivée.",
    'GPS_OUTSIDE_ZONE'              => 'Vous n\'êtes pas dans la zone GPS autorisée (:distance m).',

    // Absences
    'INSUFFICIENT_LEAVE_BALANCE'    => 'Solde insuffisant. Disponible : :available j, demandé : :requested j.',
    'OVERLAP_WITH_EXISTING'         => 'Cette période chevauche une absence existante.',

    // Avances
    'ADVANCE_ALREADY_ACTIVE'        => 'Une avance est déjà en cours de remboursement.',

    // Config
    'DEPARTMENT_HAS_EMPLOYEES'      => 'Impossible de supprimer : ce département a :count employé(s).',
    'SCHEDULE_IN_USE'               => 'Impossible de supprimer : ce planning est utilisé par :count employé(s).',

    // Abonnement
    'EMAIL_ALREADY_EXISTS'          => 'Cet email est déjà enregistré.',
];
```

### Règle : jamais de chaîne hardcodée — pattern universel

```php
// ❌ INTERDIT partout
return response()->json(['message' => 'Employé créé avec succès']);

// ✅ CORRECT — toujours __()
return response()->json(['message' => __('messages.employee_created')], 201);

// ✅ CORRECT — avec paramètres
return response()->json(['message' => __('errors.PLAN_EMPLOYEE_LIMIT_REACHED', [
    'max'  => $plan->max_employees,
    'plan' => $plan->name,
])], 403);
```

### RTL arabe — règles spécifiques Flutter

```dart
// lib/app/theme/app_theme.dart

MaterialApp(
  locale: _currentLocale,
  supportedLocales: const [
    Locale('fr'), Locale('ar'), Locale('en'), Locale('tr'),
  ],
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  // Flutter gère le RTL automatiquement si locale = 'ar'
  // Pas besoin de Directionality() manuel dans chaque widget
  // SAUF pour les widgets qui ne respectent pas le RTL :
  // TextFormField avec icônes → vérifier que prefixIcon devient suffixIcon en RTL
)
```

```dart
// Vérifier le RTL dans chaque écran :
// Builder(builder: (context) {
//   final isRTL = Directionality.of(context) == TextDirection.rtl;
//   return isRTL ? RtlLayout() : LtrLayout();
// })

// Pattern recommandé pour les listes et tableaux :
Row(
  // NE PAS utiliser mainAxisAlignment: MainAxisAlignment.start codé en dur
  // Flutter inverse automatiquement le sens en RTL
  children: [icon, SizedBox(width: 8), text],
)
```

---

## 2. MATRICE COMPLÈTE DES NOTIFICATIONS

Réf : `docs/dossierdeConception/12_notifications/14_NOTIFICATION_TEMPLATES.md`

### Quand déclencher chaque notification (résumé pour les controllers)

| Événement | Où dans le code | Canaux |
|---|---|---|
| Absence soumise | `AbsenceController::store()` | Push Gestionnaire + Email Gestionnaire + Web |
| Absence approuvée | `AbsenceService::approve()` | Push Employé + Email Employé |
| Absence refusée | `AbsenceService::reject()` | Push Employé + Email Employé |
| Avance soumise | `AdvanceController::store()` | Push Gestionnaire + Email Gestionnaire + Web |
| Avance approuvée | `AdvanceController::approve()` | Push Employé + Email Employé |
| Avance refusée | `AdvanceController::reject()` | Push Employé + Email Employé |
| Tâche assignée | `TaskController::store()` / `assign()` | Push Employé + Email Employé |
| Tâche soumise en review | `TaskController::updateStatus('review')` | Push Gestionnaire + Web |
| Bulletin disponible | `GeneratePayslipPdfJob::handle()` | Push Employé + Email Employé |
| Pointage confirmé | `AttendanceService::checkIn()` | Push Employé (silencieux) |
| Non pointé H+1 | `AttendanceCheckMissingCommand` | Email Gestionnaire + Web |
| Contrat CDD J-30, J-15, J-7 | `ContractsExpiryAlertsCommand` | Email Gestionnaire + Web |
| Trial se termine dans 2j | `CheckExpiredSubscriptionsCommand` | Email Manager Principal |

### NotificationService — méthode unifiée

```php
// app/Services/NotificationService.php

class NotificationService
{
    public function send(Employee $recipient, string $type, array $data): void
    {
        // 1. Persister en base (notification center in-app)
        $notification = Notification::create([
            'employee_id' => $recipient->id,
            'type'        => $type,
            'title'       => __("notifications.{$type}.title", $data),
            'body'        => __("notifications.{$type}.body", $data),
            'data'        => $data,
            'is_read'     => false,
        ]);

        // 2. Badge Redis
        Cache::increment("tenant:{$recipient->company->uuid}:notif:{$recipient->id}:unread");

        // 3. Push FCM (via job async)
        if ($recipient->devices()->where('is_active', true)->exists()) {
            dispatch(new SendFcmNotificationJob($recipient, $type, $data, $notification->id))
                ->onQueue('notifications');
        }

        // 4. Email (si configuré pour ce type)
        if ($this->shouldSendEmail($type)) {
            dispatch(new SendNotificationEmailJob($recipient, $type, $data))
                ->onQueue('emails');
        }
    }

    private function shouldSendEmail(string $type): bool
    {
        return in_array($type, [
            'absence.approved', 'absence.rejected',
            'advance.approved', 'advance.rejected',
            'task.assigned', 'payslip.available',
            'contract.expiring', 'subscription.expiring',
        ]);
    }
}
```

### lang/fr/notifications.php — structure

```php
return [
    'absence.submitted' => [
        'title' => 'Demande de congé',
        'body'  => ':employee_name demande :days_count jour(s) du :start_date au :end_date',
    ],
    'absence.approved' => [
        'title' => 'Congé approuvé ✅',
        'body'  => 'Votre demande du :start_date a été approuvée.',
    ],
    'absence.rejected' => [
        'title' => 'Congé refusé',
        'body'  => 'Votre demande du :start_date a été refusée. Motif : :reason',
    ],
    'payslip.available' => [
        'title' => 'Bulletin de paie disponible',
        'body'  => 'Votre bulletin :period est disponible.',
    ],
    'attendance.confirmed' => [
        'title' => 'Pointage enregistré',
        'body'  => 'Arrivée enregistrée à :time.',
    ],
    // ... (14 types au total — voir 14_NOTIFICATION_TEMPLATES.md)
];
```

---

## 3. STATE MACHINES — TRANSITIONS D'ÉTAT OBLIGATOIRES

Réf : `docs/dossierdeConception/19_diagrammes_uml/05_state_machines.md`

Ces machines à états définissent les transitions VALIDES.
Toute transition non listée ici doit retourner une erreur 422.

### Absence (absences.status)

```
pending   → approved   : PUT /absences/{id}/approve (manager)
pending   → rejected   : PUT /absences/{id}/reject  (manager)
pending   → cancelled  : PUT /absences/{id}/cancel  (employé)
approved  → cancelled  : PUT /absences/{id}/cancel  (employé, si future)
```

**Transitions INTERDITES** (retourner 422 INVALID_TRANSITION) :
- rejected → approved
- cancelled → pending
- approved → pending

```php
// Validation dans AbsenceController
private function validateTransition(Absence $absence, string $newStatus): void
{
    $allowed = [
        'pending'  => ['approved', 'rejected', 'cancelled'],
        'approved' => ['cancelled'],
        'rejected' => [],
        'cancelled'=> [],
    ];

    if (!in_array($newStatus, $allowed[$absence->status] ?? [])) {
        abort(422, __('errors.INVALID_TRANSITION'));
    }
}
```

### Avance (salary_advances.status)

```
pending   → approved   : PUT /advances/{id}/approve
pending   → rejected   : PUT /advances/{id}/reject
approved  → active     : AUTO — première déduction paie (PayrollService)
approved  → rejected   : Annulation avant première déduction
active    → repaid     : AUTO — amount_remaining = 0 (PayrollService)
```

### Tâche (tasks.status)

```
pending     → in_progress  : PUT /tasks/{id}/status (employé assigné)
in_progress → review       : PUT /tasks/{id}/status (employé — soumet pour validation)
review      → completed    : PUT /tasks/{id}/status (manager — valide)
review      → in_progress  : PUT /tasks/{id}/status (manager — retourne pour corrections)
completed   → archived     : PUT /tasks/{id}/status (manager)
```

### Paie (payrolls.status)

```
draft       → calculated   : POST /payroll/calculate
calculated  → processing   : POST /payroll/validate (async — dispatch job)
processing  → validated    : AUTO — job terminé avec succès
processing  → failed       : AUTO — job en erreur
validated   → archived     : AUTO — fin de période
```

### Évaluation (evaluations.status)

```
draft       → submitted    : POST /evaluations/{id}/submit (manager)
submitted   → acknowledged : POST /evaluations/{id}/acknowledge (employé)
```

---

## 4. DOCUMENTS UML À LIRE AVANT CHAQUE MODULE

Avant de coder un module, l'IA DOIT lire le diagramme de séquence correspondant.
Ces diagrammes montrent les interactions exactes entre composants.

| Module | Document à lire |
|---|---|
| Auth + Login multi-tenant | `docs/dossierdeConception/19_diagrammes_uml/01_auth_multi_tenant_sequence.md` |
| PayrollService | `docs/dossierdeConception/19_diagrammes_uml/02_payroll_calculation_sequence.md` |
| Absences (approbation) | `docs/dossierdeConception/19_diagrammes_uml/03_absence_approval_sequence.md` |
| Pointage (check-in/out) | `docs/dossierdeConception/19_diagrammes_uml/04_attendance_checkin_sequence.md` |
| Inscription publique | `docs/dossierdeConception/19_diagrammes_uml/09_public_registration_sequence.md` |
| Toutes les entités | `docs/dossierdeConception/19_diagrammes_uml/05_state_machines.md` |

---

## 5. GUIDE AJOUT PAYS — CE QUE L'IA DOIT IMPLÉMENTER

Réf : `docs/dossierdeConception/05_regles_metier/15_GUIDE_AJOUT_PAYS.md`

Le Super Admin peut ajouter un pays depuis le panneau admin.
L'IA doit implémenter dans CC-07 :

```php
// app/Http/Controllers/Admin/HRModelController.php

// GET /admin/hr-models — Liste les modèles disponibles
public function index(): JsonResponse
{
    $models = HRModelTemplate::select('country', 'name', 'currency', 'is_active')->get();
    return response()->json(['data' => $models]);
}

// GET /admin/hr-models/{country} — Détail d'un modèle
public function show(string $country): JsonResponse
{
    $model = HRModelTemplate::where('country', $country)->firstOrFail();
    return response()->json(['data' => $model]);
}

// PUT /admin/hr-models/{country} — Modifier les taux d'un pays (Super Admin uniquement)
// Utile pour mettre à jour les taux légaux chaque année
public function update(Request $request, string $country): JsonResponse
{
    $model = HRModelTemplate::where('country', $country)->firstOrFail();
    $model->update($request->validated());
    return response()->json(['message' => "Modèle $country mis à jour."]);
}
```

### Alerte annuelle mise à jour jours fériés islamiques

```php
// app/Console/Commands/AlertHolidayUpdate.php
// Planifié en novembre chaque année

class AlertHolidayUpdate extends Command
{
    protected $signature = 'holidays:alert-update';

    public function handle(): void
    {
        $countriesWithIslamicHolidays = ['DZ', 'MA', 'TN', 'TR', 'SN', 'CI'];

        // Notifier le Super Admin par email
        // Mail::to(config('app.super_admin_email'))->send(new HolidayUpdateReminderMail(
        //     countries: $countriesWithIslamicHolidays,
        //     year: now()->addYear()->year
        // ));

        $this->info('Alerte mise à jour jours fériés envoyée pour ' . now()->addYear()->year);
    }
}
```

---

## CHECKLIST DE CONFORMITÉ i18n + NOTIFICATIONS (à vérifier avant chaque commit)

```
Backend :
[ ] Toutes les réponses JSON utilisent __() — aucune chaîne hardcodée
[ ] Les 4 fichiers lang/{fr,ar,en,tr}/errors.php sont à jour
[ ] Les 4 fichiers lang/{fr,ar,en,tr}/notifications.php sont à jour
[ ] Le Middleware SetLocale est enregistré APRÈS TenantMiddleware dans bootstrap/app.php
[ ] Les fallbacks sont testés (clé absente en 'ar' → retombe sur 'fr')

Flutter :
[ ] Tous les textes UI utilisent context.l10n.XXX
[ ] Les 4 fichiers ARB sont synchronisés (pas de clé manquante)
[ ] L'arabe RTL est testé sur au moins 3 écrans principaux
[ ] Les nombres et dates sont formatés selon la locale (intl package)

Notifications :
[ ] Chaque approbation/rejet déclenche bien une notification push + email
[ ] Le badge Redis est incrémenté à chaque nouvelle notification
[ ] Le badge Redis est décrémenté quand notification marquée lue
[ ] Les 9 types de notifications de la matrice sont tous implémentés
```
