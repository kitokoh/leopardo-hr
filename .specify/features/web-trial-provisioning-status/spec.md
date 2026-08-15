# Feature Specification: Guided trial — volet vitrine de suivi du provisioning

**Feature Branch**: `feat/web-trial-provisioning-status`
**Created**: 2026-08-15 | **Status**: Draft → Implemented
**Issue**: #2469 (suite #2437)

## Problème

Le backend (PR #2445, issue #2437) expose désormais :
- `POST /api/v1/trial/signup` → `data.provisioning_token` (guided_trial)
- `GET /api/v1/trial/status?token=<64>` → `{status: pending|ready|failed, provisioned_at?, login_url? (ready), message? (failed)}`

La vitrine `front/web` :
1. jette `provisioning_token` dans `/api/forms/signup` (seuls `id`, `email`, `status` sont transmis) ;
2. n'offre aucun suivi de provisioning — le prospect sans email OTP/magic link (mailer down, spam, cold start) n'a aucun chemin vers son lien d'accès.

## User Stories & Testing

### User Story 1 — Le token transite (P2)
**Acceptance Scenarios**:
1. Given un signup guided_trial réussi, When `/api/forms/signup` répond, Then `data.provisioning_token` est présent (64 chars) quand le backend en fournit un.
2. Given un signup sans token (fallback OTP down), Then la réponse reste identique à l'existant (`provisioned: false`, pas de token).

### User Story 2 — Suivi du provisioning (P2)
**Acceptance Scenarios**:
1. Given un `provisioning_token` valide, When le client interroge `/api/forms/trial-status?token=…`, Then la réponse backend est passée telle quelle (status + `login_url` si ready).
2. Given un token invalide/absent, Then 404 `PROVISIONING_TOKEN_INVALID` est relayé.
3. Given un écran de suivi actif en `pending`, When le statut passe `ready`, Then l'écran affiche le lien `login_url` avec bouton + copie.
4. Given `failed`, Then message générique + contact (jamais l'erreur brute).

### User Story 3 — Persistance session (P2)
**Acceptance Scenarios**:
1. Given un token reçu au signup, When la page est rechargée, Then le lien « Suivre l'état de mon espace » reste disponible (sessionStorage).

## Non-Goals
- Ne pas modifier le flux OTP existant.
- Ne pas exposer l'email ni le token dans l'URL.
- Pas de régression i18n : la copie du composant suit le pattern existant (FR durci du formulaire), sans mojibake.
