# ISSUE_3775 — Super-admin démo : alignement email par défaut

> Spec Kit : `.specify/features/3775-demo-super-admin-email/spec.md` · Tâches : `tasks.md`
> Branche : `fix/3775-demo-super-admin-email` · Issue : #3775 (volet code) · racine #2646

## Cause racine (code, pas déploiement)

| Élément | Avant | Après |
|---|---|---|
| `SuperAdminSeeder` (défaut email) | `admin@leopardo-rh.com` | inchangé |
| `config/demo.php` (`super_admin_email`) | **`admin@example.com`** ❌ | `admin@leopardo-rh.com` ✅ |
| `syncDemoSuperAdmin` compte absent | no-op silencieux | warning explicite |

Sans `SUPER_ADMIN_EMAIL` dans l'environnement (cas nominal), le sync démo
ciblait `admin@example.com` (inexistant) → le vrai compte démo gardait un mot
de passe aléatoire → `INVALID_CREDENTIALS` pour `admin@leopardo-rh.com`.

## Changements

- `api/config/demo.php` : défaut `super_admin_email` → `admin@leopardo-rh.com`.
- `api/database/seeders/DemoCompanyOnceSeeder.php` : warning si compte cible absent.
- `api/tests/Feature/Demo/DemoSuperAdminSyncTest.php` : 3 scénarios (défaut aligné,
  surcharge config, mode démo off → non-touch).

## Hors périmètre

Déploiement Render (#3767), variables Render, DNS — volet ops de #3775/#2646.
