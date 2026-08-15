## Plan technique
1. `api/config/cors.php` : `allowed_origins` += `https://leo-admin.pages.dev` ; `allowed_origins_patterns` = `['https://*.pages.dev']`.
2. Test : `CorsAndTrustedProxyTest` — nouveau test `test_cors_whitelist_includes_cloudflare_pages_origin` (assertContains leo-admin.pages.dev, pattern pages.dev, aucun `*`).
3. CHANGELOG.md + PR.
