<?php

declare(strict_types=1);

// BC-33 GEO (GEO-01..05, #8350-#8354) — رسائل النواة الجغرافية المشتركة.
return [
    'check_postgis_description' => 'يتحقق من توفر امتداد PostGIS ‏(BC-33 GEO)‏',
    'postgis_unavailable' => 'PostGIS غير متوفر: تعمل وحدة geo في وضع منخفض (Haversine). راجع docs/infra/POSTGIS_NEON.md.',
    'postgis_available' => 'PostGIS متوفر (الإصدار :version).',
];
