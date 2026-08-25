<?php

return [
    // Employee file document checklist (issue #5326 — gap G3, spec hr-lifecycle §5)

    // Document types
    'type_contract_signed' => 'Signed contract',
    'type_employee_file' => 'Employee file',
    'type_career_decision' => 'Career decision',
    'type_departure_record' => 'Departure record',
    'type_notice_summary' => 'Notice period summary',
    'type_settlement' => 'End-of-service settlement',
    'type_certificate' => 'Certificate of employment',
    'type_other' => 'Other document',

    // Statuses
    'status_received' => 'Received',
    'status_uploaded' => 'Uploaded',
    'status_generated' => 'Generated',
    'status_missing' => 'Missing',

    // Messages
    'created' => 'Document recorded successfully.',
    'updated' => 'Document updated successfully.',
    'deleted' => 'Document removed from the file.',
    'not_found' => 'Document not found in your company.',
    'forbidden' => 'Only a principal manager or an HR manager can manage employee file documents.',
    'dossier_complete' => 'Complete file',
    'dossier_incomplete' => 'Incomplete file',
];
