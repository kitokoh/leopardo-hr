<?php

/**
 * #9349 (audit sécurité 2026-11-10, F7) — validation d'une origine CORS
 * fournie par l'environnement.
 *
 * Une origine CORS est un triplet `scheme://host[:port]` SANS chemin, sans
 * wildcard et sans identifiants. Tout le reste est refusé (retour `null`,
 * éliminé par le `array_filter()` de `allowed_origins`) plutôt que propagé :
 * avec `supports_credentials => true`, une origine erronée est une faille,
 * pas un désagrément.
 *
 * Hors `local`/`testing`, le schéma `https` est obligatoire.
 */
if (! function_exists('cors_env_origin')) {
    function cors_env_origin(string $key): ?string
    {
        $raw = env($key);

        if (! is_string($raw)) {
            return null;
        }

        $value = trim($raw);

        if ($value === '' || str_contains($value, '*')) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        // Une origine ne porte ni chemin, ni query, ni fragment, ni userinfo.
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        if (isset($parts['path']) && $parts['path'] !== '' && $parts['path'] !== '/') {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $isLocal = in_array(env('APP_ENV', 'production'), ['local', 'testing'], true);

        if ($scheme !== 'https' && ! $isLocal) {
            return null;
        }

        return $scheme.'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // #9349 (audit sécurité 2026-11-10, F7) — `['*']` remplacé par la liste
    // explicite des méthodes réellement servies. Avec
    // `supports_credentials => true`, un `*` sur les méthodes élargit la
    // surface cross-origin sans aucun bénéfice : aucune route n'utilise
    // TRACE/CONNECT, et les méthodes exotiques n'ont pas à être annoncées.
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter([
        // Dev servers (Vite) — whitelistés en dur et indépendamment de
        // FRONTEND_URL pour rester déterministes (issues #1769) :
        //  - localhost:3000 : front web (défaut documenté de FRONTEND_URL)
        //  - localhost:3001 : admin dashboard (ajouté dans #1785)
        //  - localhost:5173 : admin dashboard Vite dev server (port documenté
        //    de `npm run dev` dans DEVELOPMENT.md — issue #6687)
        //  - 127.0.0.1:4173 : admin dashboard E2E Playwright local (port par
        //    défaut de front/admin-dashboard/playwright.config.js) — sans lui,
        //    le job Web E2E échoue en masse sur le health-check CORS bloqué.
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:5173',
        // Isolated admin E2E preview (Vite preview on loopback).
        'http://127.0.0.1:4173',
        env('FRONTEND_URL', 'http://localhost:3000'),
        env('APP_URL', 'http://localhost'),
        // Production origins
        'https://gestionemployerbackend.onrender.com',
        // Real vitrine origin (leopardo-hr.vercel.app returns 404 — the live
        // deployment is gestionemployer-backend.vercel.app, see
        // front/web/.env.local.example). See issue #1468.
        'https://gestionemployer-backend.vercel.app',
        'https://leopardo-hr.vercel.app',
        // Issue #2333 : le panneau admin est déployé sur Cloudflare Pages
        // (leo-admin.pages.dev — cf. PILOTAGE.md) ; ADMIN_DASHBOARD_URL
        // n'est pas renseignée sur Render, donc l'origine réelle est listée
        // en dur, et les previews Pages sont couvertes par le pattern
        // `https://*.pages.dev` ci-dessous.
        'https://leo-admin.pages.dev',
        // #8096 : marketplace Leopardo Marché (projet Vercel dédié
        // `leopardo-marche`, cf. .github/workflows/marketplace-deploy.yml) —
        // origine exacte exigée pour la session acheteur en cookie HttpOnly
        // cross-origin (`supports_credentials` interdit tout wildcard).
        'https://leopardo-marche.vercel.app',
        // #9349 (audit sécurité 2026-11-10, F7) — les deux entrées suivantes
        // venaient BRUTES de l'environnement : une valeur erronée en
        // production (`*`, origine en `http://`, chaîne vide mal collée)
        // élargissait immédiatement la surface cross-origin AVEC credentials.
        // Elles passent désormais par `cors_env_origin()`, qui n'accepte
        // qu'une origine absolue, sans wildcard, en `https` hors développement.
        cors_env_origin('ADMIN_DASHBOARD_URL'),
        cors_env_origin('CORS_EXTRA_ORIGIN'),
    ]),
    'allowed_origins_patterns' => [
        // #8096 : previews Vercel du marketplace — même discipline que
        // `leo-admin.pages.dev` ci-dessous : restreint au projet connu
        // (`leopardo-marche` et ses URLs de branche
        // `leopardo-marche-git-….vercel.app`), jamais `*.vercel.app` en
        // entier (tout attaquant peut créer un projet Vercel arbitraire).
        '#^https://leopardo-marche(-[a-z0-9-]+)?\\.vercel\\.app$#i',
        // Issue #2333 : previews Cloudflare Pages du dashboard admin.
        // Chaque preview reçoit un sous-domaine de la forme
        // `<hash-ou-branch>.leo-admin.pages.dev`.
        //
        // SÉCURITÉ (#5582) : le pattern précédent (`*.pages.dev`) était trop
        // large — tout attaquant peut créer un projet Cloudflare Pages avec un
        // nom arbitraire et obtenir un sous-domaine *.pages.dev valide.
        // On restreint désormais au projet connu (`leo-admin`).
        //
        // [a-z0-9][a-z0-9-]* : sous-domaine commençant par alphanumérique (tiret initial invalide DNS).
        // ATTENTION : fruitcake/php-cors passe ces entrées directement à
        // preg_match() — ce doit être une EXPRESSION RÉGULIÈRE COMPLÈTE,
        // pas un glob. Un glob crashe en 500 (preg_match delimiter).
        '#^https://[a-z0-9][a-z0-9-]*\.leo-admin\.pages\.dev$#i',
    ],

    // Explicit allow-list instead of '*': defence-in-depth so that a future
    // debug-time addition of '*' to allowed_origins (a classic CORS-debugging
    // mistake) doesn't immediately combine with supports_credentials=true
    // into a cross-origin credential theft vector. See
    // docs/security/AUDIT_API_2026-07-19.md, section 4.
    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'X-Requested-With',
        'X-Request-Id',
        'X-API-Version',
        'X-App-Context',
        // RTMX (#5277/#5446) : GET conditionnels (ETag/If-None-Match) et
        // rejeu idempotent des écritures (Idempotency-Key) depuis les clients
        // web cross-origin (admin-dashboard Vue).
        'If-None-Match',
        'Idempotency-Key',
    ],

    'exposed_headers' => [
        // RTMX : l'ETag et le marqueur de rejeu doivent être lisibles par le
        // JS des clients web cross-origin (sinon cache ETag inutilisable).
        'ETag',
        'Idempotent-Replayed',
    ],

    // #7996 : cache preflight de 10 minutes. `max_age => 0` interdisait
    // toute mise en cache des réponses OPTIONS par les navigateurs — chaque
    // requête cross-origin était donc précédée d'un preflight (un aller-
    // retour réseau pur perdu, à chaque appel, en prod comme en dev).
    // 600 s supprime ce coût tout en propageant vite une évolution des
    // origines/entêtes autorisées.
    'max_age' => 600,

    'supports_credentials' => true,

];
