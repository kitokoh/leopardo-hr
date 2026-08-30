# Classification PII et cycle de vie — MAT-011

> **Issue :** [MAT-011 #5869](https://github.com/kitokoh/leopardo-hr/issues/5869)
> **Contexte :** BC-01 — Platform Core (gouvernance RGPD transverse)
> **Date :** 2026-08-30
> **Statut :** actif — catalogue + garde CI + preuves de cycle de vie

## 1. Objectif

Chaque champ sensible de la plateforme possède une **politique** : catégorie,
classification, conservation, anonymisation, chiffrement et accès. Les droits
RGPD (export, effacement, rétention) sont **testés et audités**.

## 2. Catalogue machine-readable

`dev-hub/tools/pii-classification.json` — source de vérité :

- `fields[]` : une entrée par champ sensible, avec `key`, `context` (BC),
  `category`, `classification`, `retention`, `anonymization`, `encryption`,
  `access` ;
- `contexts` : déclaration des champs par bounded context (anti déclaration
  morte) ;
- listes de référence : `categories`, `classifications`, `retentions`,
  `anonymizations`, `encryptions` (toute valeur hors liste = CI rouge).

Classifications : `public` < `internal` < `confidential` < `restricted`.
Exemples : IBAN/national_id/biométrie → `restricted` + chiffrement au repos ;
nom/email → `confidential` ; genre/nationalité → `internal`.

## 3. Garde CI

`dev-hub/tools/check-pii-classification.sh` (branchee dans
`architecture-check.yml`, job Hygiene Guards) + auto-tests
`dev-hub/tools/tests/check-pii-classification.test.sh` (6 scénarios) :

- JSON valide + `schema_version`/`purpose` ;
- politique COMPLÈTE par champ (7 attributs obligatoires) ;
- valeurs dans les listes de référence ;
- contexte référencé déclaré ;
- clés uniques ;
- tout champ déclaré dans un contexte existe dans `fields[]`.

## 4. Cycle de vie — preuves testées

| Droit | Implémentation | Preuve |
|---|---|---|
| Export (art. 15) | `GET /api/v1/privacy/export` (`PrivacyController`) | `PiiLifecycleTest` : champs du bundle catalogués, export audité (`hr_data.privacy_exported`), scoped tenant |
| Effacement (art. 17) | `gdpr:anonymize-employee` | `GdprAnonymizeEmployeeTest` + `PiiLifecycleTest` : PII remplacées, historique paie conservé (10 ans), audit |
| Consentement biométrique | `PATCH /api/v1/privacy/biometric-consent` | `PrivacyControllerTest` |
| Demande de suppression | `POST /api/v1/privacy/deletion-request` | `PrivacyControllerTest` |
| Rétention | commandes `*:purge-*` (biométrie, audit, documents, TTS) | suites de purge dédiées |

## 5. Règles développeurs

- Un nouveau champ PII dans un modèle → ajout dans le catalogue AVANT merge
  (garde CI : toute politique manquante bloque).
- Un champ `restricted` n'est jamais exposé dans un export self-service
  (ex. IBAN — testé).
- Redaction des logs : `PiiRedactionProcessor` (MAT-009) masque les clés
  sensibles ; les secrets (password_hash, 2FA) sont `hidden` des modèles.
- Accès sensible en lecture → `DataAccessAuditLogger::recordSensitive`.
