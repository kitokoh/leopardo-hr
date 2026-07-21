<?php

return [
    // Rôles
    'role_manager' => 'Manager',
    'role_employee' => 'Employé',
    'manager_role_principal' => 'Gérant principal',
    'manager_role_rh' => 'Responsable RH',
    'manager_role_dept' => 'Chef de département',
    'manager_role_comptable' => 'Comptable',
    'manager_role_superviseur' => 'Superviseur',

    // Statuts
    'status_active' => 'Actif',
    'status_suspended' => 'Suspendu',
    'status_archived' => 'Archivé',

    // Actions
    'created' => 'Employé créé avec succès.',
    'updated' => 'Employé mis à jour avec succès.',
    'archived' => 'Employé archivé avec succès.',
    'invited' => 'Invitation envoyée avec succès.',
    'role_assign_forbidden' => 'Seul l\'administrateur de l\'entreprise peut assigner des rôles.',
    'role_assign_not_in_company' => 'Employé introuvable dans votre entreprise.',
    'role_assigned' => 'Rôle \':role\' assigné avec succès. Un email avec les liens de téléchargement de l\'application a été envoyé.',
    'role_removed' => 'Rôle retiré. L\'employé est désormais un employé standard.',
    'team_roles_forbidden' => 'Seul l\'administrateur de l\'entreprise peut consulter les rôles de l\'équipe.',

    // Labels
    'first_name' => 'Prénom',
    'last_name' => 'Nom',
    'email' => 'Email',
    'phone' => 'Téléphone',
    'department' => 'Département',
    'position' => 'Poste',
    'schedule' => 'Horaire',
    'contract_type' => 'Type de contrat',
    'salary_base' => 'Salaire de base',
    'date_of_birth' => 'Date de naissance',
    'nationality' => 'Nationalité',
    'gender' => 'Genre',
    'hire_date' => 'Date d\'embauche',
    'emergency_contact' => 'Contact d\'urgence',
];
