<?php

declare(strict_types=1);

// BC-33 GEO (GEO-01..05, #8350-#8354) — messages du core géospatial
// transverse (PA2-I18N-007 : jamais de français en dur, tout passe par ce
// catalogue).
return [
    'check_postgis_description' => 'Vérifie la disponibilité de l\'extension PostGIS (BC-33 GEO)',
    'postgis_unavailable' => 'PostGIS indisponible : le module geo fonctionne en mode dégradé (Haversine). Voir docs/infra/POSTGIS_NEON.md.',
    'postgis_available' => 'PostGIS disponible (version :version).',
];
