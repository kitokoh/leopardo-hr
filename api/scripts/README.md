# API Scripts

Developer-facing scripts. Not part of the application runtime — do not require
these from `app/` code.

## start-local.ps1

Local dev bootstrap (Windows/PowerShell): copies `.env.example` → `.env`,
starts `docker compose`, installs composer deps, runs migrations, optionally
seeds demo data and runs the test suite.

```powershell
cd api
.\scripts\start-local.ps1 -SeedDemo
.\scripts\start-local.ps1 -SeedDemo -RunTests
```

Must be run with `api/` as the working directory (the script itself resolves
`../` internally to reach `.env` and `docker-compose.yml`, so invoking it via
`.\scripts\start-local.ps1` from `api/` works as shown above).

## test_script.php

Ad-hoc scratch script used to manually inspect a single Eloquent model/factory
behavior (`Company::factory()`) outside of PHPUnit/Pest. Not wired into CI or
`composer test`. Run manually via `php artisan tinker` equivalent:

```bash
php artisan tinker --execute="require 'scripts/test_script.php';"
```

Keep or delete freely — it is not referenced anywhere else in the codebase.
