# Tasks: SeedDefaultSteps — clés step_key/title (Closes #4188)

**Spec**: `.specify/features/4188-seed-default-steps-keys/spec.md`

- [x] T1. Claim : issue #4188 self-assignée + branche `fix/4188-seed-default-steps-keys`
- [x] T2. `SeedDefaultSteps` : create() → `step_key`/`title` ; dédup → `pluck('step_key')`
- [x] T3. Test `SeedDefaultStepsTest` (3 scénarios : non-NULL, idempotence, pas de doublon)
- [ ] T4. Vérifs : PHPStan strict + Pint + suite Onboarding
- [ ] T5. CHANGELOG + `docs/specifications/ISSUE_4188.md`
- [ ] T6. PR → CI verte → merge → suppression branche
