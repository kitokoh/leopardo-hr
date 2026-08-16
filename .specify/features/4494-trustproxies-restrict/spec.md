# Feature Specification: Restreindre trustProxies — rate-limits IP non contournables (Closes #4494)

**Feature Branch**: `fix/4494-trustproxies-restrict`
**Created**: 2026-08-16 | **Status**: In progress
**Issue**: #4494 (P2, api, security)

## Contexte

`api/bootstrap/app.php:75` configure `trustProxies(at: '*')` : tout proxy — ou
toute connexion directe — peut injecter/forcer `X-Forwarded-For` et faire
croire à une IP différente à chaque requête. Tous les limiteurs IP
(auth-sensitive 10/min, trial signup 5/15, onboarding 10/min, public-careers,
webhooks-inbound, kiosk-punch, web-login) deviennent contournables, alors que
ce sont la seule protection des endpoints publics sans auth.

Render est le seul proxy de bord. Ses connexions vers l'app arrivent depuis le
réseau privé de Render (RFC1918). Un client internet direct a un peer IP
public — hors liste de confiance → XFF ignoré.

## User Stories & Testing

### User Story 1 — Les limites par IP ne sont plus contournables (P1)

En tant qu'opérateur sécurité, je veux que l'IP utilisée pour le rate-limiting
soit l'IP réelle du client, pas un header qu'il contrôle.

**Acceptance Scenarios**:
1. Given une requête avec `REMOTE_ADDR` public + `X-Forwarded-For` forgé,
   When l'app calcule `$request->ip()`, Then le header forgé est ignoré.
2. Given N requêtes avec des `X-Forwarded-For` différents depuis la même IP,
   When le limiteur est atteint, Then 429 (bucket unique, pas par-XFF).
3. Given Render (peer RFC1918), When XFF est posé par le proxy, Then l'IP
   réelle du client est bien résolue (XFF toujours honoré depuis les proxies
   de confiance).

## Requirements

- **FR-001**: `trustProxies` restreint aux réseaux de confiance :
  loopback + RFC1918 privés + ULA IPv6.
- **FR-002**: Render.yaml documente les CIDR de sortie / le modèle de confiance.
- **FR-003**: test feature : XFF forgé depuis une IP publique n'augmente pas
  le bucket rate-limit.
- **FR-004**: PHPStan strict vert ; CHANGELOG.md mis à jour.

## Success Criteria

- **SC-001**: `trustProxies(at: '*')` absent du code (grep → 0).
- **SC-002**: test XFF forgé → 429 au même seuil qu'une IP unique.
- **SC-003**: suite Unit/Feature des zones touchées verte.

## Hors périmètre

- Le balayage des schémas tenant du password reset (#4495) — traité à part.
- Autres contournements de rate-limit (#4494 couvre uniquement trustProxies).
