<?php

declare(strict_types=1);

/**
 * #8747 / #8748 [BOS-CHAT] — configuration du chat horizontal.
 *
 * Le module reste dans BC-13 COMMS (`app/Modules/Notification`) : ce fichier
 * ne déclare AUCUN nouveau système de fichiers, il désigne simplement le
 * disque existant de `config/filesystems.php` que le chat doit utiliser.
 *
 * ⚠️ R1 de l'audit (`docs/mission-business-os/16_chat_horizontal_audit.md`) :
 * sur Render, le disque `local` vit dans le conteneur et **disparaît à chaque
 * redéploiement**. En production, `CHAT_ATTACHMENT_DISK` doit donc pointer vers
 * un disque **persistant** (le disque `s3` déjà déclaré, ou `private` monté sur
 * un volume). Sans cette variable, le comportement historique est conservé
 * (`FILESYSTEM_DISK`, `local` par défaut) — aucun déploiement existant ne
 * change de comportement sans action explicite.
 */
return [

    'attachments' => [
        /*
         * Disque de stockage des pièces jointes de conversation.
         *
         * Repli en cascade : `CHAT_ATTACHMENT_DISK` → `FILESYSTEM_DISK` →
         * `local`. Le nom du disque réellement utilisé est enregistré sur
         * chaque message (`conversation_messages.attachment_disk`), donc un
         * changement de disque ne rend pas les pièces déjà stockées illisibles.
         */
        'disk' => env('CHAT_ATTACHMENT_DISK', env('FILESYSTEM_DISK', 'local')),

        /*
         * Taille maximale acceptée à l'upload, en kilo-octets.
         * Volontairement identique à la limite de la phase 1 (5 Mo) : la
         * relever est un choix produit, pas une conséquence technique.
         */
        'max_kb' => (int) env('CHAT_ATTACHMENT_MAX_KB', '5120'),

        /*
         * Durée de validité d'une URL temporaire, en secondes, quand le disque
         * configuré sait en produire (S3). Sur un disque local, l'API sert le
         * fichier elle-même après contrôle d'accès : pas d'URL publique.
         */
        'temporary_url_ttl' => (int) env('CHAT_ATTACHMENT_URL_TTL', '300'),
    ],

    /*
     * #8747 — taille de page par défaut de l'historique d'un fil.
     * Bornée côté service (`MAX_MESSAGE_PAGE_SIZE`), donc une valeur aberrante
     * fournie par un client ne peut pas faire charger tout le fil.
     */
    'messages' => [
        'per_page' => (int) env('CHAT_MESSAGES_PER_PAGE', '30'),
        'max_per_page' => 100,
    ],

];
