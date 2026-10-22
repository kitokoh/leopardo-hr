<?php

declare(strict_types=1);

/**
 * #8747 / #8748 / #8750 [BOS-CHAT] — messages d'erreur du chat horizontal.
 *
 * Ces messages sont renvoyes tels quels au client : ils passent par le
 * catalogue (PA2-I18N-007) et non par un litteral dans le controleur.
 */
return [
    'attachment_unavailable' => 'Ek dosya kullanılamıyor.',
    'report_requires_conversation' => 'Bir mesaj bildirimi ilgili konuşma üzerinden yapılmalıdır.',
    'public_empty_body' => 'Mesaj boş olamaz.',
    'public_body_too_long' => 'Mesaj :max karakteri aşamaz.',
    'public_thread_title' => ':name ile konuşma',
];
