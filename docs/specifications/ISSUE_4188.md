# ISSUE_4188 — SeedDefaultSteps : clés step_key/title

> Spec Kit : `.specify/features/4188-seed-default-steps-keys/spec.md` · Issue : #4188
> Branche : `fix/4188-seed-default-steps-keys`

## Correctif

- `OnboardingStep::create` : `'step_key' => $step['key']`, `'title' => $step['label']`.
- Dédup : `pluck('step_key')` (attribut réel du modèle).
- Tests : `SeedDefaultStepsTest` (étapes non NULL, idempotence, pas de doublon après complétion manuelle).
