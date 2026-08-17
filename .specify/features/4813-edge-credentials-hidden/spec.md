# Feature Specification: EdgeNode/EdgeLicense — credentials cachés (Closes #4813)

**Branch**: `fix/4813-edge-credentials-hidden` | **Created**: 2026-08-17 | **Issue**: #4813 (P2, api, security)

## Contexte
#4687 clôturée sans correctif. `license_key`/`signed_payload` dans `$fillable`, aucun `$hidden` → exposés en clair dans les sérialisations. Branche `fix/4687-edge-credentials-hidden` jamais mergée.

## Requirements
- **FR-001**: `$hidden = ['license_key', 'signed_payload']` sur EdgeNode + EdgeLicense.
- **FR-002**: flux Edge (lecture attributs bruts) inchangé — `$hidden` ne bloque que la sérialisation.
- **FR-003**: test régression `assertJsonMissing('license_key')` sur une ressource Edge.

## Success Criteria
- **SC-001**: réponses API sans license_key/signed_payload ; **SC-002**: handshake Edge OK ; **SC-003**: suites vertes.
