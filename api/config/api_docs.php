<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Documentation API publique (routes Blade /docs, /api-explorer, /tester-guide)
    |--------------------------------------------------------------------------
    |
    | Le tier dev Render (« gestionemployerbackend ») tourne avec
    | APP_ENV=production (dette de nommage historique documentée dans render.yaml) :
    | le middleware EnsureApiDocsAuthorized ne peut donc pas distinguer dev de prod
    | via app()->environment(). Ce flag explicite rétablit l'intention d'origine
    | (issue #5588) :
    |
    |   - tier dev : API_DOCS_PUBLIC=true -> doc accessible sans session (QA/démos)
    |   - prod     : non défini (false)   -> doc verrouillée (Gate viewApiDocs)
    |
    | Défaut false = aucun changement de comportement en production.
    */
    'public' => (bool) env('API_DOCS_PUBLIC', false),
];
