# Feature Specification: Trial signup — dédup des provisionings pending (issue #3951)

**Feature Branch**: `fix/3951-trial-provisionings-dedup`

**Created**: 2026-08-15

**Status**: Draft → Implemented

**Input**: Constat QA qa-expert14 2026-08-15 — `SelfServiceTrialController.php` (parcours `guided_trial`) insère une ligne `trial_provisionings` + dispatche `ProvisionDemoTenantJob` à chaque POST, sans dédup : un double POST (retry réseau, double clic, onglet dupliqué) crée **2 lignes pending + 2 jobs → 2 tenants sandbox**.

## Problème

- Le check `findExistingManager` (RequestTrialSignup) ne voit pas les lignes du chemin guidé (`trial_provisionings`) — une 2ᵉ requête passe et insère un doublon.
- Aucune contrainte base n'empêche 2 lignes `pending` pour le même email.
- Issue #3945 (ouverte, complémentaire) couvre le cas « email déjà manager » (réponse uniforme anti-énumération) mais pas la dédup des lignes pending.

## Décision

1. **Contrôleur** : dans la branche `guided_trial`, chercher une ligne `pending` existante pour l'email → la réutiliser (réponse idempotente avec le **même** token, aucun insert, aucun job). Sinon insérer + dispatcher, avec catch `QueryException` 23505 (course check-then-create, pattern #3238) → récupérer la ligne gagnante.
2. **Base** : index unique partiel PostgreSQL `trial_provisionings_pending_email_unique ON public.trial_provisionings (email) WHERE status = 'pending'` (migration `2026_08_15_000012`) — les lignes `ready`/`failed` permettent un nouveau cycle.
3. **Tests** : `tests/Feature/TrialProvisioningDedupTest.php` — double POST → même token, 1 ligne, 1 job ; `ready` → nouveau cycle ; `failed` → retry possible.

## User Scenarios & Testing

### User Story 1 — Double POST guided_trial = une seule provision (Priority: P1)

**Independent Test**: `php artisan test --filter=TrialProvisioningDedupTest` → 3/3 verts.

**Acceptance Scenarios**:

1. **Given** un premier POST `guided_trial` (email E), **When** un second POST identique arrive avant la fin du provisioning, **Then** même `provisioning_token`, 1 ligne pending, 1 `ProvisionDemoTenantJob`.
2. **Given** la ligne passée à `ready`, **When** un nouveau POST arrive, **Then** nouveau token (nouveau cycle possible).
3. **Given** la ligne passée à `failed`, **When** un retry arrive, **Then** nouvelle tentative propre.
4. **Given** deux POST concurrents (course), **When** l'insert viole l'index partiel, **Then** 200 avec le token de la ligne gagnante (jamais de 500).

## Edge Cases

- L'index partiel ne couvre que `status='pending'` : les cycles suivants (après ready/failed) restent possibles — c'est la sémantique voulue (pas un unique global sur email).
- Le catch 23505 ne s'applique qu'au parcours `guided_trial` (l'insert est le seul site concerné).
- Compatible avec #3945 (anti-énumération) : si le cas manager est géré en amont de la branche, la dédup pending s'applique aux emails sans manager.
