# Diagramme de se9quence — Calcul de la Paie

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

    Note over PC: Ve9rification RBAC
    PC->>PC: ManagerMiddleware +<br/>PlanLimitMiddleware

    PC->>JOB: Dispatch GeneratePayslipPdfJob<br/>(async queue)

    PC-->>G: 202 {job_id} (pour polling)

    activate JOB

    loop Pour chaque employe9
        JOB->>PS: calculateForEmployee(<br/>employee_id, month, year)

        PS->>RD: GET tenant:{uuid}:settings
        RD-->>PS: company_settings<br/>(CNSS, taux, etc.)

        PS->>PG: GET hr_model<br/>(cotisations, baremes IR)
        PG-->>PS: country HR model

        PS->>PG: GET salary_base FROM employees
        PG-->>PS: salary_base

        PS->>PG: SELECT * FROM attendance_logs<br/>WHERE month = :month AND employee_id
        PG-->>PS: attendance records
        Note over PS: Calcul heures supple9mentaires<br/>+ majoration

        Note over PS: brut_total =<br/>salaire_base + heurs_sup

        Note over PS: Calcul deductions se9curite9 sociale<br/>(part salarie9e)

        Note over PS: base_imposable =<br/>brut_total - cotisations_ss

        Note over PS: Calcul IR<br/>par tranches progressives

        PS->>PG: SELECT * FROM attendance_logs<br/>WHERE late_minutes > 0
        PG-->>PS: retards du mois
        Note over PS: Conversion fuseau horaire<br/>entreprise requise !

        Note over PS: Calcul pe9nalite9s de retard

        PS->>PG: SELECT * FROM salary_advances<br/>WHERE status='active'
        PG-->>PS: advances actifs

        Note over PS: Calcul deductions absences<br/>(jours non travaille9s)

        Note over PS: net_a_payer =<br/>brut - ss - ir - pe9nalite9s<br/>- avance - absences

        PS->>PG: UPDATE salary_advances<br/>SET amount_remaining =<br/>amount_remaining - deduction

        PS->>PG: INSERT payroll_records<br/>(status='draft')
        PG-->>PS: payroll record cre9e9
    end

    JOB->>JOB: Render Blade template<br/>(DomPDF)

    JOB->>ST: Store PDF in<br/>storage/app/payslips/{year}/{month}/
    ST-->>JOB: pdf_path

    JOB->>PG: UPDATE payroll_records<br/>SET pdf_path = :path,<br/>status = 'validated'
    PG-->>JOB: Mise a jour OK

    JOB->>NS: Send push notification<br/>"payslip_available"
    NS-->>JOB: Notification envoye9e

    deactivate JOB

    Note over G: Le gestionnaire interroge<br/>GET /payroll/job/{job_id}<br/>pour suive le statut
```

---

## Explication des interactions

| E9tape | Interaction | De9tail |
|--------|-------------|---------|
| 1-2 | **Reque2te de validation de paie** | Le gestionnaire lance le calcul pour une liste d'employe9s, un mois et une anne9e donne9s. Les middleware RBAC et de limites de plan sont ve9rifie9s en premier lieu. |
| 3-4 | **Job asynchrone** | Le calcul e9tant potentiellement lourd, il est dispatche9 dans une queue Laravel. Le gestionnaire rec00oit un `job_id` pour interroger le statut via polling. |
| 5a-b | **Configuration & mode9le RH** | Les re9glages de l'entreprise sont recupe9re9s depuis le cache Redis (cle9 `tenant:{uuid}:settings`). Les mode9les de cotisations et baremes IR sont issus du mode9le RH du pays. |
| 5c-e | **Calcul du brut** | Le salaire de base est additionne9 aux heures supple9mentaires calcule9es e0 partir des `attendance_logs` du mois. |
| 5f-h | **Deductions sociales et fiscales** | La se9curite9 sociale (part salarie9e) est de9duite du brut. L'imp00ft sur le revenu est calcule9 par tranches progressives. |
| 5i | **Pe9nalite9s de retard** | Les minutes de retard sont recupe9re9es depuis les pointages. La conversion en fuseau horaire de l'entreprise est obligatoire pour un calcul correct. |
| 5j-k | **Avances & absences** | Les avances sur salaire actives sont de9duites du net. Les jours d'absence non re9mune9re9s sont e9galement de9duits. |
| 5l-m | **Net e0 payer** | Le salaire net est calcule9 et stocke9 en base avec le statut `draft`. Le `amount_remaining` des avances est mis e0 jour. |
| 6-8 | **Ge9ne9ration du bulletin PDF** | Le job rend un template Blade via DomPDF, stocke le fichier dans le syste9me de fichiers et met e0 jour le chemin et le statut (`validated`). |
| 9 | **Notification employe9** | L'employe9 rec00oit une notification push l'informant que son bulletin de paie est disponible. |
