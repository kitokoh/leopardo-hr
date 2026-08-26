# Runbook — Configuration de l'environnement démo sur Render (issue #2336)

> **Quand utiliser ce runbook** : l'environnement Render live sert des tenants
> démo (TechCorp Algérie, PharmaPlus Maroc, DigitalFlow Tunisie) et doit
> exposer les comptes démo documentés (`docs/DEMO_ACCOUNTS.md`) : super-admin
> `admin@leopardo-rh.com` / `password123`, `GET /api/v1/demo-users`, et CORS
> autorisé pour le panneau admin Cloudflare Pages.
>
> Action 100 % côté dashboard Render — aucun changement de code requis pour
> les 3 points ci-dessous (le code est déjà prêt : `SuperAdminSeeder`,
> `DemoUserController`, `config/cors.php`).

## Symptômes constatés (2026-08-14, QA plateforme)

| Symptôme | Cause | Correctif (dashboard Render) |
|----------|-------|------------------------------|
| `POST /api/v1/platform/auth/login` → `INVALID_CREDENTIALS` avec admin@leopardo-rh.com / password123 | `SUPER_ADMIN_PASSWORD` non défini → `SuperAdminSeeder` génère un mot de passe aléatoire (`CHANGER_EN_PROD_*`) | Définir `SUPER_ADMIN_PASSWORD=password123` **et** `FORCE_SUPER_ADMIN_PASSWORD_RESET=true`, puis redéployer (le seeder resynchronise le hash — comportement sécurisé conservé en prod) |
| `GET /api/v1/demo-users` → 404 | `DEMO_MODE_ENABLED=false` (défaut sécurisé) | `DEMO_MODE_ENABLED=true` sur l'environnement démo/staging (contrat QA documenté AGENTS.md v4.16.128). **Ne pas** toucher à `DISABLE_DEMO_SEEDING` (il bloque la création des démos, pas leur exposition) |
| CORS panneau admin cassé | `ADMIN_DASHBOARD_URL` non renseigné | Désormais **non bloquant** depuis #2333 : `leo-admin.pages.dev` + les previews `https://*.leo-admin.pages.dev` sont dans la allowlist en dur (#5582 : le pattern est restreint à la zone `leo-admin`, plus aucun `*.pages.dev` tiers). Définir quand même `ADMIN_DASHBOARD_URL=https://leo-admin.pages.dev` par hygiène (origine explicite dans les logs/audits) |

## Procédure (5 min)

1. Dashboard Render → service **leopardo-api** → onglet **Environment**.
2. Ajouter/modifier :
   - `SUPER_ADMIN_PASSWORD` = `password123` (ou secret dédié démo — jamais le mot de passe prod)
   - `FORCE_SUPER_ADMIN_PASSWORD_RESET` = `true`
   - `DEMO_MODE_ENABLED` = `true`
   - `ADMIN_DASHBOARD_URL` = `https://leo-admin.pages.dev`
   - (optionnel) `DEMO_MODE` = `true` si le flag legacy est lu quelque part
3. **Save changes** → Render redéploie automatiquement (`autoDeploy: true`).
4. Vérifier (smoke) :
   ```bash
   # 1. Login super-admin démo
   curl -s -X POST https://gestionemployerbackend.onrender.com/api/v1/platform/auth/login \
     -H 'Accept: application/json' -H 'Content-Type: application/json' \
     -d '{"email":"admin@leopardo-rh.com","password":"password123"}' | head -c 200
   # 2. Contrat demo-users public
   curl -s https://gestionemployerbackend.onrender.com/api/v1/demo-users -H 'Accept: application/json' | head -c 200
   # 3. CORS panneau admin
   curl -s -I -X OPTIONS https://gestionemployerbackend.onrender.com/api/v1/platform/auth/login \
     -H 'Origin: https://leo-admin.pages.dev' -H 'Access-Control-Request-Method: POST' \
     | grep -i 'access-control-allow-origin'
   ```
5. Si `INVALID_CREDENTIALS` persiste : vérifier que le seeder a bien tourné dans les logs du deploy (rechercher `Super Admin` dans les logs Render) — `DemoCompanyOnceSeeder` doit aussi être passé (resynchronise `admin@leopardo-rh.com` / `password123` et retire le 2FA démo, AGENTS.md v4.16.234).

## Rappels sécurité

- `SUPER_ADMIN_PASSWORD` doit rester un secret GitHub/Render, jamais commité.
- En **production réelle** (hors démo), laisser `SUPER_ADMIN_PASSWORD` vide : le seeder génère un mot de passe aléatoire affiché dans les logs (comportement sécurisé par défaut).
- `FORCE_SUPER_ADMIN_PASSWORD_RESET=true` écrase le hash à **chaque** deploy : ne l'activer que sur démo/staging.
- Références : `api/database/seeders/SuperAdminSeeder.php`, `docs/DEMO_ACCOUNTS.md`, `docs/GUIDES/GUIDE_SUPER_ADMIN.md`, `.env.example` (clés 89-94, 133).
