# Threat models — uploads, OCR, WhatsApp, POS, devices

> **Issue :** [MAT-017 #5875](https://github.com/kitokoh/leopardo-hr/issues/5875)
> **Registre :** `dev-hub/tools/security-threat-models.json`
> **Garde CI :** `dev-hub/tools/check-security-threat-models.sh` (job Hygiene Guards)
> **Références existantes :** `docs/security/` (AUDIT_SECURITE_2026-08-23, ENDPOINTS_SENSIBLES, SECURITY.md, DATA_AT_REST, REGISTRE_TRAITEMENTS)

## Catalogue de contrôles

| Contrôle | Définition |
|---|---|
| `type_taille_mime` | Validation type MIME, taille et extension avant traitement (Requests `mimes`/`max`, liste blanche) |
| `secrets` | Aucun secret/PII en clair dans logs, fixtures, réponses d'erreur ou commits (garde `secret-scan` + TruffleHog) |
| `signatures` | Signatures des callbacks vérifiées cryptographiquement avant traitement (fail-closed) |
| `replay` | Idempotence et anti-rejeu (clés d'idempotence, événements dédupliqués) |
| `permissions` | Autorisation explicite (Policies/RBAC) — jamais le `company_id` client comme preuve d'autorité |
| `audit` | Traçabilité (`audit_logs`, `webhook_deliveries`, registres) sans données sensibles |

## Surfaces

### 1. Uploads de fichiers (documents RH, preuves, pièces jointes)
- **Menaces :** exécution de contenu hostile, déni de stockage, exfiltration de PII, path traversal.
- **Contrôles :** validation Requests (type/taille/MIME), stockage privé, nom de fichier assaini, retention documentée (`docs/security/POLITIQUE_RETENTION_DOCUMENTS.md`), accès Policies, traçabilité `audit_logs`.
- **Tests :** négatifs type/taille/MIME dans les tests Feature des endpoints concernés ; cross-tenant 404.

### 2. OCR (photos compteurs FuelStation, documents)
- **Menaces :** injection via image malveillante, fuite de PII des documents scannés dans les logs/métadonnées, rejeu d'images.
- **Contrôles :** fichiers entrants validés avant OCR, métadonnées sans données sensibles, idempotence du traitement, journal d'audit.
- **Tests :** taille/MIME négatifs, absence de PII dans les logs d'erreur OCR.

### 3. WhatsApp / canaux externes
- **Menaces :** usurpation de provider (webhook non signé), exfiltration de données vers un canal non consenti, secrets provider en clair.
- **Contrôles :** provider **audit-only** tant que non activé (règle plateforme), signatures vérifiées, préférences utilisateur (`notification_preferences`), heures calmes, quotas.
- **Tests :** webhook à signature invalide rejeté (fail-closed), consentement requis avant envoi.

### 4. Paiements et POS (Stripe, Chargily, Accounting)
- **Menaces :** webhook forgé (rejeu/forgery), montant modifié, secret gateway en clair.
- **Contrôles :** signatures vérifiées (fail-closed), idempotence des événements, clés jamais loguées, journal `webhook_deliveries`, audit des écritures.
- **Tests :** signature invalide → 401, rejeu → effet unique, secret absent des logs.

### 5. Devices, kiosques et edge (ZKTeco, device_code)
- **Menaces :** device usurpé, token volé, sync edge falsifiée, rejeu de pointage.
- **Contrôles :** auth par `device_code` (dérivé haché au repos, #5588), rotation des tokens, signatures de sync, permissions par device, audit des punches.
- **Tests :** device_code invalide → 401, hash au repos vérifié, cross-tenant 404.

### 6. Webhooks entrants/sortants
- **Menaces :** callbacks forgés, rejeu, fuite de payload via logs.
- **Contrôles :** signatures provider vérifiées avant traitement, idempotence (clés, `webhook_deliveries`), secrets jamais logués, replay contrôlé (DLQ).
- **Tests :** signature invalide → rejet, rejeu → réponse mémorisée, dead-letter.

## Règles

1. Toute nouvelle surface sensible ajoute une entrée au registre AVEC son document
   et ses contrôles — le garde exige `secrets`, `permissions` et `audit` partout.
2. Un contrôle cité doit exister dans le catalogue (aucun contrôle fantôme).
3. Les tests négatifs (type/taille/MIME, signatures, cross-tenant) sont exigés
   pour chaque surface lors de son implémentation.

## Exécution locale

```bash
bash dev-hub/tools/check-security-threat-models.sh
bash dev-hub/tools/tests/check-security-threat-models.test.sh
```

## Rollback

- Revert du commit ; registre + script autonomes sans état.
