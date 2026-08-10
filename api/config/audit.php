<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Journalisation des accès aux données sensibles (Spec S-2, #1662)
    |--------------------------------------------------------------------------
    |
    | Les lectures sensibles (bulletins, exports, journal de paie, certificat,
    | fin de contrat) sont tracées dans `audit_logs` via DataAccessAuditLogger
    | avec la catégorie `sensitive_data_access`.
    |
    | `sampling` borne le volume d'écriture : 1.0 = 100 % des accès, 0.1 = 10 %,
    | 0.0 = journalisation désactivée. Un échantillon tiré par accès permet de
    | conserver une piste proportionnelle sans exploser la base (critère
    | « volume borné » de la spec S-2).
    */
    'sensitive_access' => [
        'sampling' => max(0.0, min(1.0, (float) env('SENSITIVE_ACCESS_SAMPLING', 1.0))),

        // Actions de lecture considérées sensibles (report `audit:sensitive-report`).
        'actions' => [
            'hr_data.pay_slip_downloaded',
            'hr_data.payment_doc_downloaded',
            'hr_data.payroll_journal_viewed',
            'hr_data.bank_export_viewed',
            'hr_data.bank_export_downloaded',
            'hr_data.end_of_contract_viewed',
            'hr_data.certificate_downloaded',
        ],
    ],
];
