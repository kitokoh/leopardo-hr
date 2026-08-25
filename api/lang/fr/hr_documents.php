<?php

return [
    // Checklist documents du dossier employé (issue #5326 — gap G3, spec hr-lifecycle §5)

    // Types de documents
    'type_contract_signed' => 'Contrat signé',
    'type_employee_file' => 'Fiche employé',
    'type_career_decision' => 'Décision de carrière',
    'type_departure_record' => 'Enregistrement de départ',
    'type_notice_summary' => 'Récapitulatif préavis',
    'type_settlement' => 'Solde de tout compte',
    'type_certificate' => 'Attestation d\'emploi',
    'type_other' => 'Autre document',

    // Statuts
    'status_received' => 'Reçu',
    'status_uploaded' => 'Téléversé',
    'status_generated' => 'Généré',
    'status_missing' => 'Manquant',

    // Messages
    'created' => 'Document enregistré avec succès.',
    'updated' => 'Document mis à jour avec succès.',
    'deleted' => 'Document supprimé du dossier.',
    'not_found' => 'Document introuvable dans votre entreprise.',
    'forbidden' => 'Seul un gérant principal ou un responsable RH peut gérer les documents du dossier employé.',
    'dossier_complete' => 'Dossier complet',
    'dossier_incomplete' => 'Dossier incomplet',
];
