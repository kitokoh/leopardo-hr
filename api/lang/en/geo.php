<?php

declare(strict_types=1);

// BC-33 GEO (GEO-01..05, #8350-#8354) — transverse geospatial core messages.
return [
    'check_postgis_description' => 'Checks PostGIS extension availability (BC-33 GEO)',
    'postgis_unavailable' => 'PostGIS unavailable: the geo module runs in degraded mode (Haversine). See docs/infra/POSTGIS_NEON.md.',
    'postgis_available' => 'PostGIS available (version :version).',
];
