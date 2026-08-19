# Tasks — Kiosque punch-methods (Epic #5119)

> Référence croisée des issues filles. Ordre d'exécution : T5 (spec/docs) → T1 (API) → T2 (enforcement) → T3 (employé) → T4 (kiosk web).

## T1 — API data-model `punch_methods` (#5120)
- [ ] Migration : `punch_methods` jsonb nullable sur `zkteco_devices`.
- [ ] Modèle `ZktecoDevice` : `$fillable` + cast `array` + constantes `PUNCH_METHOD_FINGERPRINT/FACE/CARD`.
- [ ] Règle de validation (store/update) : `array` + `in:fingerprint,face,card` + `distinct` (champ optionnel).
- [ ] Exposition dans `index`/`show` (ressource device).
- [ ] Défaut entreprise : lecture `company_settings` `kiosk.punch_methods.default` (helper de résolution).
- [ ] Tests Feature : CRUD valide/invalide/absent, isolation tenant.

## T2 — Enforcement sync-attendance (#5121)
- [ ] Helper `methodAllowed(ZktecoDevice $device, string $method): bool` (config device → défaut entreprise → toutes).
- [ ] Mapping enrollment employé : `fingerprint`→`biometric_fingerprint_enabled`, `face`→`biometric_face_enabled`, `card`→`badge_number` non vide.
- [ ] Intégration dans `KioskAttendanceService::syncPunches` / `ZktecoIntegrationService` : refus record (`skip`) ou lot selon la gravité.
- [ ] Codes erreur `PUNCH_METHOD_NOT_ALLOWED` / `EMPLOYEE_METHOD_NOT_ENROLLED` ×4 catalogues `errors.*` (fr/en/tr/ar) + `localized_message`.
- [ ] Log canal `audit` pour chaque refus.
- [ ] Rétro-compat : payload sans `method` ⇒ `fingerprint`.
- [ ] Tests Feature : méthode non autorisée, employé non enrôlé, rétro-compat.

## T3 — Employé badge + enrollment (#5122)
- [ ] Migration additive `badge_number` nullable + index unique partiel scoped company.
- [ ] `Employee` : fillable + validation PATCH (`nullable|string|max:50`, unicité scoped company).
- [ ] `EmployeeResource` : `badge_number` (si non vide) + bloc `enrollment: {fingerprint, face, card}`.
- [ ] Lookup kiosk : `badge_number` ajouté à la résolution employé (à côté de `zkteco_id`/`matricule`).
- [ ] Tests Feature : PATCH badge, unicité, exposition enrollment, lookup.

## T4 — Kiosk web (#5123)
- [ ] Chargement de `punch_methods` au boot (bridge/config endpoint).
- [ ] Filtrage du sélecteur de méthode (`biometricType`) + état « aucune méthode ».
- [ ] Écran/flux carte : saisie badge → POST punch local avec `method: 'card'` + `badge_number`.
- [ ] Payload `method` pour empreinte/visage (`fingerprint`/`face`).
- [ ] i18n ×4 dans `front/zkteco-kiosk/i18n.js` (3 méthodes + écran carte + erreurs).
- [ ] Vérification manuelle documentée / tests unitaires si le setup le permet.

## T5 — Spec-kit + docs (#5124)
- [ ] `.specify/features/5119-kiosk-punch-methods/spec.md` (livré).
- [ ] `.specify/features/5119-kiosk-punch-methods/tasks.md` (livré).
- [ ] MAJ `docs/dossierdeConception/` (multitenancy ou section kiosque) : méthodes par tenant.
- [ ] MAJ guide device / `docs/kiosk/` : paramétrage `punch_methods` + flux carte.
- [ ] CHANGELOG à la livraison (gouvernance).

## Ordre et dépendances
1. **T5** (spec livrée, les issues #5120-#5124 existent déjà).
2. **T1** (data-model) → **T3** (badge/enrollment, indépendant mais requis pour `card`) → **T2** (enforcement, dépend de T1+T3) → **T4** (UI kiosk, dépend de T1).
