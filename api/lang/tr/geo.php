<?php

declare(strict_types=1);

// BC-33 GEO (GEO-01..05, #8350-#8354) — paylaşılan coğrafi çekirdek mesajları.
return [
    'check_postgis_description' => 'PostGIS uzantısının kullanılabilirliğini denetler (BC-33 GEO)',
    'postgis_unavailable' => 'PostGIS kullanılamıyor: geo modülü sınırlı modda çalışır (Haversine). Bkz. docs/infra/POSTGIS_NEON.md.',
    'postgis_available' => 'PostGIS kullanılabilir (sürüm :version).',
];
