<?php

return [
    // Labels generaux
    'cabinet' => 'Placard',
    'folders' => 'Dossiers',
    'documents' => 'Documents',
    'shares' => 'Partages',
    'stats' => 'Statistiques',

    // Types
    'type_folder' => 'dossier',
    'type_document' => 'document',
    'type_item' => 'element',

    // Actions
    'create_folder' => 'Creer un dossier',
    'upload_document' => 'Ajouter un document',
    'share' => 'Partager',
    'download' => 'Telecharger',
    'move' => 'Deplacer',
    'rename' => 'Renommer',
    'delete' => 'Supprimer',
    'revoke_share' => 'Revoquer le partage',

    // Messages
    'folder_created' => 'Dossier cree avec succes.',
    'folder_updated' => 'Dossier mis a jour.',
    'folder_deleted' => 'Dossier supprime.',
    'document_uploaded' => 'Document ajoute avec succes.',
    'document_updated' => 'Document mis a jour.',
    'document_deleted' => 'Document supprime.',
    'document_moved' => 'Document deplace.',
    'share_created' => 'Partage cree avec succes.',
    'share_revoked' => 'Partage revoque.',
    'share_expired' => 'Ce lien de partage a expire.',

    // Email de partage
    'share_email_subject' => ':name vous a partage un element : :item',
    'share_email_heading' => 'Document partage via Leopardo RH',
    'share_email_body' => ':name vous a partage le :type ":item" depuis son placard personnel.',
    'share_email_button' => 'Acceder au document',
    'share_email_expires' => 'Ce lien expire le :date.',
    'share_email_footer' => 'Ce message a ete envoye automatiquement par Leopardo RH. Si vous n\'avez pas demande ce partage, ignorez cet email.',

    // Stats
    'total_documents' => 'Total documents',
    'total_size' => 'Espace utilise',
    'total_folders' => 'Total dossiers',
];
