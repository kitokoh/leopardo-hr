# Mini-spec — Issue #3745

## Intention
Limiter les abus locaux contre le bridge kiosk avant qu’ils ne consomment la mémoire ou la file offline.

## Contrat de sécurité

| Contrôle | Règle |
|---|---|
| Body JSON | Maximum 64 KiB ; dépassement → HTTP 413 `REQUEST_BODY_TOO_LARGE` |
| `/local/punch` | Maximum 60 requêtes par IP sur une fenêtre glissante de 60 secondes |
| Dépassement du rate-limit | HTTP 429 `LOCAL_PUNCH_RATE_LIMITED` |
| Concurrence | Bucket protégé par verrou thread-safe |
| Autres endpoints POST | La borne body s’applique également aux payloads JSON lus par le bridge |

Le limiteur est une défense locale complémentaire au throttle distant ; il ne remplace ni le token de session ni la protection CSRF existants.

## Validation

Les 27 tests bridge existants passent avec `unittest`. Des contrôles ciblés confirment le rejet après 60 punches dans la fenêtre, la réouverture après expiration, la limite de 65536 octets et la compilation Python.
