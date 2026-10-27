<?php

declare(strict_types=1);

// GSI-12 (#8523) — messages de synchronisation des connecteurs.
// Les jetons sont des valeurs dynamiques (:detail) : le message reste
// traduisible sans concaténation côté code (PA2-I18N-007).
return [
    'unauthorized' => 'kimlik bilgileri sağlayıcı tarafından reddedildi (yeniden bağlanma gerekli)',
    'quota' => 'sağlayıcı kotası aşıldı',
    'malformed' => 'okunamayan yanıt: :detail',
    'transport' => 'taşıma hatası: :detail',
];
