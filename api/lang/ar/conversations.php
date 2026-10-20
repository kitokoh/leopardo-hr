<?php

declare(strict_types=1);

/**
 * #8747 / #8748 / #8750 [BOS-CHAT] — messages d'erreur du chat horizontal.
 *
 * Ces messages sont renvoyes tels quels au client : ils passent par le
 * catalogue (PA2-I18N-007) et non par un litteral dans le controleur.
 */
return [
    'attachment_unavailable' => 'المرفق غير متاح.',
    'report_requires_conversation' => 'يجب أن يمر التبليغ عن رسالة عبر المحادثة المعنية.',
    'public_empty_body' => 'لا يمكن أن تكون الرسالة فارغة.',
    'public_body_too_long' => 'لا يمكن أن تتجاوز الرسالة :max حرفًا.',
    'public_thread_title' => 'محادثة مع :name',
];
