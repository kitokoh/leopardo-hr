# Tasks: Super-admin démo — alignement email par défaut (Closes #3775)

**Spec**: `.specify/features/3775-demo-super-admin-email/spec.md`

- [x] T1. Claim : issue #3775 self-assignée + branche `fix/3775-demo-super-admin-email` (protocole #2400)
- [x] T2. `api/config/demo.php` — défaut `super_admin_email` aligné sur SuperAdminSeeder (`admin@leopardo-rh.com`)
- [x] T3. `DemoCompanyOnceSeeder::syncDemoSuperAdmin` — warning explicite quand le compte cible n'existe pas
- [x] T4. Test `DemoSuperAdminSyncTest` (3 scénarios : défaut, surcharge, mode off) — vert localement (PostgreSQL)
- [ ] T5. Vérifs locales : PHPStan strict + Pint + suite `Demo*` complète
- [ ] T6. CHANGELOG (`### Fixed`) + `docs/specifications/ISSUE_3775.md` + `docs/DEMO_ACCOUNTS.md` (URLs canoniques)
- [ ] T7. PR `fix(api): super-admin démo — email par défaut aligné, warning si compte absent (Closes #3775)` → CI verte → merge → suppression branche
