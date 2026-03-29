# TEMPLATES DE NOTIFICATIONS — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## STRUCTURE D'UNE NOTIFICATION

Chaque notification a 3 versions : Push (FCM), Email, Web (cloche dashboard).
Les clés i18n sont dans `resources/lang/{locale}/notifications.php` (Laravel)
et dans `assets/l10n/app_{locale}.arb` (Flutter).

---

## MATRICE DÉCLENCHEURS → CANAUX

| Déclencheur | Push Employé | Push Gestionnaire | Email Employé | Email Gestionnaire | Web Gestionnaire |
|-------------|:------------:|:-----------------:|:-------------:|:-----------------:|:----------------:|
| Absence soumise | — | ✅ | — | ✅ | ✅ |
| Absence approuvée | ✅ | — | ✅ | — | — |
| Absence refusée | ✅ | — | ✅ | — | — |
| Avance soumise | — | ✅ | — | ✅ | ✅ |
| Avance approuvée | ✅ | — | ✅ | — | — |
| Avance refusée | ✅ | — | ✅ | — | — |
| Tâche assignée | ✅ | — | ✅ | — | — |
| Tâche soumise (review) | — | ✅ | — | — | ✅ |
| Tâche refusée (retour) | ✅ | — | — | — | — |
| Bulletin disponible | ✅ | — | ✅ | — | — |
| Retard détecté | — | — | — | — | ✅ |
| Non pointé (H+1) | — | — | — | ✅ | ✅ |
| Contrat CDD (J-30) | — | — | — | ✅ | ✅ |
| Abonnement (J-15) | — | — | — | ✅ | ✅ |
| Confirmation pointage | ✅ | — | — | — | — |

---

## TEMPLATES PAR TYPE

### 1. ABSENCE SOUMISE

**Push Gestionnaire :**
```
Titre  : Demande de congé
Corps  : {employee_name} demande {days_count} jour(s) du {start_date} au {end_date}
Data   : {"type": "absence.submitted", "absence_id": 89, "route": "/manager/absences"}
```

**Email Gestionnaire :**
```
Sujet  : [Leopardo RH] Nouvelle demande de congé — {employee_name}
Corps  :
  Bonjour,
  {employee_name} a soumis une demande de congé.
  Type : {absence_type}
  Période : du {start_date} au {end_date} ({days_count} jour(s))
  Solde actuel : {leave_balance} jour(s)
  [Bouton: Voir la demande] → lien vers le dashboard
```

---

### 2. ABSENCE APPROUVÉE

**Push Employé :**
```
Titre  : Congé approuvé
Corps  : Votre demande du {start_date} au {end_date} a été approuvée
Data   : {"type": "absence.approved", "absence_id": 89, "route": "/absences"}
```

**Email Employé :**
```
Sujet  : [Leopardo RH] Votre congé a été approuvé
Corps  :
  Bonjour {employee_name},
  Votre demande de congé a été approuvée.
  Période : du {start_date} au {end_date}
  Votre solde restant : {leave_balance_after} jour(s)
  {comment_gestionnaire si présent}
```

---

### 3. ABSENCE REFUSÉE

**Push Employé :**
```
Titre  : Congé refusé
Corps  : Votre demande du {start_date} au {end_date} a été refusée
Data   : {"type": "absence.rejected", "absence_id": 89, "route": "/absences/89"}
```

**Email Employé :**
```
Sujet  : [Leopardo RH] Votre demande de congé
Corps  :
  Bonjour {employee_name},
  Votre demande de congé n'a pas pu être accordée.
  Motif : {decision_comment}
  N'hésitez pas à proposer d'autres dates.
```

---

### 4. TÂCHE ASSIGNÉE

**Push Employé :**
```
Titre  : Nouvelle tâche assignée
Corps  : "{task_title}" — Échéance : {due_date}
Data   : {"type": "task.assigned", "task_id": 156, "route": "/tasks/156"}
```

**Email Employé :**
```
Sujet  : [Leopardo RH] Nouvelle tâche : {task_title}
Corps  :
  Bonjour {employee_name},
  Une nouvelle tâche vous a été assignée.
  Titre : {task_title}
  Priorité : {priority}
  Échéance : {due_date}
  Description : {description}
  [Bouton: Voir la tâche]
```

---

### 5. TÂCHE EN REVIEW (Employé a terminé)

**Push Gestionnaire :**
```
Titre  : Tâche à valider
Corps  : {employee_name} a terminé "{task_title}"
Data   : {"type": "task.review", "task_id": 156, "route": "/manager/tasks"}
```

**Web Gestionnaire (cloche) :**
```
Titre  : Tâche soumise pour validation
Corps  : {employee_name} — {task_title}
```

---

### 6. BULLETIN DE PAIE DISPONIBLE

**Push Employé :**
```
Titre  : Bulletin de paie disponible
Corps  : Votre bulletin de {period_month}/{period_year} est disponible
Data   : {"type": "payroll.available", "payroll_id": 42, "route": "/payslips/42"}
```

**Email Employé :**
```
Sujet  : [Leopardo RH] Votre bulletin de paie — {mois_annee}
Corps  :
  Bonjour {employee_name},
  Votre bulletin de paie de {mois_annee} est disponible.
  Net à payer : {net_salary} {currency}
  [Bouton: Télécharger le bulletin]
  (optionnel : pièce jointe PDF si send_email_to_employees = true)
```

---

### 7. EMPLOYÉ NON POINTÉ

**Email Gestionnaire :**
```
Sujet  : [Leopardo RH] {employee_name} n'a pas pointé aujourd'hui
Corps  :
  Bonjour,
  {employee_name} n'a pas enregistré son arrivée.
  Date : {today}
  Heure de début prévue : {schedule_start}
  [Bouton: Saisir un pointage manuel]
```

**Web Gestionnaire :**
```
Titre  : Non pointé
Corps  : {employee_name} — prévu à {schedule_start}
```

---

### 8. CONTRAT CDD EXPIRANT

**Email Gestionnaire (envoyé à J-30, J-15, J-7) :**
```
Sujet  : [Leopardo RH] Contrat de {employee_name} expire dans {days_remaining} jour(s)
Corps  :
  Bonjour,
  Le contrat {contract_type} de {employee_name} arrive à échéance.
  Date d'expiration : {contract_end}
  Jours restants : {days_remaining}
  Pensez à renouveler le contrat ou à préparer la fin de collaboration.
  [Bouton: Voir la fiche employé]
```

---

### 9. CONFIRMATION DE POINTAGE

**Push Employé :**
```
Titre  : Arrivée enregistrée   |   Départ enregistré
Corps  : Aujourd'hui à {heure_serveur}
Data   : {"type": "attendance.confirmed", "route": "/attendance"}
```
(Notification silencieuse — affichée dans le centre de notifications, pas en popup)

---

## CLÉS I18N LARAVEL

```php
// resources/lang/fr/notifications.php
return [
    'absence_submitted_title'    => 'Demande de congé',
    'absence_submitted_body'     => ':name demande :days jour(s) du :start au :end',
    'absence_approved_title'     => 'Congé approuvé',
    'absence_approved_body'      => 'Votre demande du :start au :end a été approuvée',
    'absence_rejected_title'     => 'Congé refusé',
    'absence_rejected_body'      => 'Votre demande du :start au :end a été refusée',
    'task_assigned_title'        => 'Nouvelle tâche assignée',
    'task_assigned_body'         => '":title" — Échéance : :due',
    'task_review_title'          => 'Tâche à valider',
    'task_review_body'           => ':name a terminé ":title"',
    'payroll_available_title'    => 'Bulletin disponible',
    'payroll_available_body'     => 'Votre bulletin :period est disponible',
    'not_clocked_in_subject'     => '[Leopardo RH] :name n\'a pas pointé aujourd\'hui',
    'contract_expiring_subject'  => '[Leopardo RH] Contrat de :name expire dans :days jour(s)',
    'check_in_confirmed_title'   => 'Arrivée enregistrée',
    'check_in_confirmed_body'    => 'Aujourd\'hui à :time',
    'check_out_confirmed_title'  => 'Départ enregistré',
    'check_out_confirmed_body'   => 'Aujourd\'hui à :time',
];
```

---

## IMPLÉMENTATION NOTIFICATIONSERVICE

```php
// app/Services/NotificationService.php
public function notifyAbsenceSubmitted(Employee $manager, Absence $absence): void
{
    // 1. Notification en base (web cloche)
    Notification::create([
        'recipient_id' => $manager->id,
        'type'         => 'absence.submitted',
        'title'        => __('notifications.absence_submitted_title'),
        'body'         => __('notifications.absence_submitted_body', [
            'name'  => $absence->employee->full_name,
            'days'  => $absence->days_count,
            'start' => $absence->start_date->format('d/m/Y'),
            'end'   => $absence->end_date->format('d/m/Y'),
        ]),
        'data'         => ['absence_id' => $absence->id, 'route' => '/manager/absences'],
    ]);

    // 2. Push FCM (tous les appareils du gestionnaire)
    $tokens = $manager->devices()->pluck('fcm_token')->toArray();
    if (!empty($tokens)) {
        $this->sendFCM($tokens, 'absence.submitted', $absence);
    }

    // 3. Email
    Mail::to($manager->email)->queue(new AbsenceSubmittedMail($absence));
}
```