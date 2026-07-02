# Diagramme de séquence — Calcul de la Paie

```mermaid
sequenceDiagram
    autonumber

    participant G as Gestionnaire
    participant PC as PayrollController
    participant PS as PayrollService
    participant RD as Redis
    participant JOB as GeneratePayslipPdfJob<br/>(Queue)
    participant PG as PostgreSQL (tenant)
    participant ST as Storage
    participant NS as NotificationService

    G->>PC: POST /payroll/validate<br/>{employee_ids, month, year}

    Note over PC: Vérification RBAC
    PC->>PC: ManagerMiddleware +<br/>PlanLimitMiddleware

    PC->>JOB: Dispatch GeneratePayslipPdfJob<br/>(async queue)

    PC-->>G: 202 {job_id} (pour polling)

    activate JOB

    loop Pour chaque employé
        JOB->>PS: calculateForEmployee(<br/>employee_id, month, year)

        PS->>RD: GET tenant:{uuid}:settings
        RD-->>PS: company_settings<br/>(CNSS, taux, etc.)

        PS->>PG: GET hr_model<br/>(cotisations, baremes IR)
        PG-->>PS: country HR model

        PS->>PG: GET salary_base FROM employees
        PG-->>PS: salary_base

        PS->>PG: SELECT * FROM attendance_logs<br/>WHERE month = :month AND employee_id
        PG-->>PS: attendance records
        Note over PS: Calcul heures supplémentaires<br/>+ majoration

        Note over PS: brut_total =<br/>salaire_base + heurs_sup

        Note over PS: Calcul deductions sécurité sociale<br/>(part salariée)

        Note over PS: base_imposable =<br/>brut_total - cotisations_ss

        Note over PS: Calcul IR<br/>par tranches progressives

        PS->>PG: SELECT * FROM attendance_logs<br/>WHERE late_minutes > 0
        PG-->>PS: retards du mois
        Note over PS: Conversion fuseau horaire<br/>entreprise requise !

        Note over PS: Calcul pénalités de retard

        PS->>PG: SELECT * FROM salary_advances<br/>WHERE status='active'
        PG-->>PS: advances actifs

        Note over PS: Calcul deductions absences<br/>(jours non travaillés)

        Note over PS: net_a_payer =<br/>brut - ss - ir - pénalités<br/>- avance - absences

        PS->>PG: UPDATE salary_advances<br/>SET amount_remaining =<br/>amount_remaining - deduction

        PS->>PG: INSERT payroll_records<br/>(status='draft')
        PG-->>PS: payroll record créé
    end

    JOB->>JOB: Render Blade template<br/>(DomPDF)

    JOB->>ST: Store PDF in<br/>storage/app/payslips/{year}/{month}/
    ST-->>JOB: pdf_path

    JOB->>PG: UPDATE payroll_records<br/>SET pdf_path = :path,<br/>status = 'validated'
    PG-->>JOB: Mise à jour OK

    JOB->>NS: Send push notification<br/>"payslip_available"
    NS-->>JOB: Notification envoyée

    deactivate JOB

    Note over G: Le gestionnaire interroge<br/>GET /payroll/job/{job_id}<br/>pour suivre le statut
```

---

## Explication des interactions

| Étape | Interaction | Détail |
|--------|-------------|---------|
| 1-2 | **Requête de validation de paie** | Le gestionnaire lance le calcul pour une liste d'employés, un mois et une année donnés. Les middleware RBAC et de limites de plan sont vérifiés en premier lieu. |
| 3-4 | **Job asynchrone** | Le calcul étant potentiellement lourd, il est dispatché dans une queue Laravel. Le gestionnaire reçoit un `job_id` pour interroger le statut via polling. |
| 5a-b | **Configuration & modèle RH** | Les réglages de l'entreprise sont récupérés depuis le cache Redis (clé `tenant:{uuid}:settings`). Les modèles de cotisations et barèmes IR sont issus du modèle RH du pays. |
| 5c-e | **Calcul du brut** | Le salaire de base est additionné aux heures supplémentaires calculées à partir des `attendance_logs` du mois. |
| 5f-h | **Deductions sociales et fiscales** | La sécurité sociale (part salariée) est déduite du brut. L'impôt sur le revenu est calculé par tranches progressives. |
| 5i | **Pénalités de retard** | Les minutes de retard sont récupérées depuis les pointages. La conversion en fuseau horaire de l'entreprise est obligatoire pour un calcul correct. |
| 5j-k | **Avances & absences** | Les avances sur salaire actives sont déduites du net. Les jours d'absence non rémunérés sont également déduits. |
| 5l-m | **Net à payer** | Le salaire net est calculé et stocké en base avec le statut `draft`. Le `amount_remaining` des avances est mis à jour. |
| 6-8 | **Génération du bulletin PDF** | Le job rend un template Blade via DomPDF, stocke le fichier dans le système de fichiers et met à jour le chemin et le statut (`validated`). |
| 9 | **Notification employé** | L'employé reçoit une notification push l'informant que son bulletin de paie est disponible. |
