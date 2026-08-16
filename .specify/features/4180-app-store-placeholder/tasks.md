# Tasks: Invitations de rôle — plus de placeholder App Store (Closes #4180)

**Spec**: `.specify/features/4180-app-store-placeholder/spec.md`

- [x] T1. Claim : issue #4180 self-assignée + branche `fix/4180-app-store-placeholder`
- [x] T2. `config/mobile.php` — IDs iOS env-driven (null par défaut)
- [x] T3. `RoleInvitationService` — `ios => null` sans ID réel ; plus de placeholder
- [x] T4. Template e-mail `role-assignment` — lien iOS conditionnel
- [x] T5. Test `RoleInvitationServiceTest` (3 scénarios)
- [ ] T6. Vérifs : PHPStan strict + Pint + tests mail/rôle
- [ ] T7. CHANGELOG + `docs/specifications/ISSUE_4180.md`
- [ ] T8. PR → CI verte → merge → suppression branche
