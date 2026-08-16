# Feature Specification: OIDC — DER SPKI valide pour OpenSSL 3 (issue #4096)

**Feature Branch**: `fix/4096-oidc-der-spki`

**Created**: 2026-08-15

**Status**: Draft → Implemented

**Input**: Constat QA qa-expert14 2026-08-15 (PHP 8.4.24 / OpenSSL 3) — `OidcIdTokenValidator::rsaPublicKeyDer()` produit une clé publique SPKI dont le DER est **invalide** : `openssl_verify` échoue « Supplied key param cannot be coerced » → SSO OIDC + Google Sign-In (#3941) inopérants sur OpenSSL 3 (ubuntu-latest, Render).

## Problème

Deux défauts d'encodage X.690 :

1. **INTEGER non signé** : `n` (et potentiellement `e`) n'étaient pas préfixés de `0x00` quand le bit de poids fort est à 1. Pour une clé RSA 2048 bits, le MSB de `n` est **TOUJOURS** à 1 (2^2047 ≤ n < 2^2048) → préfixe systématiquement requis. OpenSSL 3 rejette l'INTEGER ambigu ; OpenSSL 1.1 l'acceptait (permissif) — d'où le déploiement en prod cassé.
2. **Longueurs SEQUENCE à constantes magiques** : `strlen($n) + strlen($e) + 12` / `+4` ne tiennent pas compte du champ-longueur variable (1-3 octets) de `n` → SEQUENCE tronquée dès que `n` dépasse 255 octets.

## Décision

Encodage DER conforme RFC 8017 A.1.1 + X.690 :

- `positiveInteger()` : préfixe `0x00` si MSB à 1 (X.690 8.3.2).
- Longueurs calculées sur les encodages réels : chaque INTEGER = tag (0x02) + champ-longueur + contenu ; la SEQUENCE RSAPublicKey additionne les octets effectifs (plus aucune constante magique).

## User Scenarios & Testing

### User Story 1 — Le SSO OIDC vérifie les signatures JWKS sur OpenSSL 3 (Priority: P1)

**Independent Test**: `OidcIdTokenValidatorDerTest` (3 scénarios) + `SSOOidcFlowTest` + `UserAuthGoogleSignInSecurityTest` verts en CI (OpenSSL 3).

**Acceptance Scenarios**:

1. **Given** une clé RSA 2048 bits, **When** `rsaPublicKeyDer()` construit le PEM, **Then** `openssl_pkey_get_public()` l'accepte et `openssl_verify` valide une signature (test unitaire).
2. **Given** un exposant au MSB à 1 (0x81), **When** la clé est construite, **Then** le préfixe 0x00 est appliqué (test unitaire).
3. **Given** le flux OIDC complet, **When** l'id_token est signé par le JWKS de l'IdP, **Then** la vérification passe (SSOOidcFlowTest).

## Validation locale (OpenSSL 3.0.2)

- Ancien code : **rejeté** (« Supplied key param cannot be coerced ») — preuve de la régression.
- Nouveau code : **accepté + signature vérifiée** sur 512/1024/2048/3072/4096 bits (5/5).
- `php -l` vert sur les 2 fichiers modifiés.

## Edge Cases

- Exposant `e` MSB=1 (rare mais possible) : couvert par `positiveInteger()` appliqué aux deux INTEGER.
- Clés > 2048 bits (3072/4096) : champ-longueur 3 octets — couvert par les longueurs réelles.
- Aucun autre site de construction DER manuelle dans le code (vérifié par grep).
