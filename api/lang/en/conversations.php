<?php

declare(strict_types=1);

/**
 * #8747 / #8748 / #8750 [BOS-CHAT] — messages d'erreur du chat horizontal.
 *
 * Ces messages sont renvoyes tels quels au client : ils passent par le
 * catalogue (PA2-I18N-007) et non par un litteral dans le controleur.
 */
return [
    'attachment_unavailable' => 'Attachment unavailable.',
    'report_requires_conversation' => 'A message report must go through the conversation concerned.',
];
