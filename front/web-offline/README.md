# Leopardo HR — Web Offline (surface de secours PWA)

Surface PWA de secours pour le fonctionnement offline-first (kiosk / edge).

## Variables d'environnement

### `NEXT_PUBLIC_EDGE_API`

Base URL de l'API Edge interrogée par la PWA. Deux cibles possibles :

| Cible | Valeur | Quand l'utiliser |
|---|---|---|
| Nœud Edge local (recommandé) | `http://leopardo.local:7878` | La PWA est servie sur le réseau local d'un client équipé d'un nœud Leopardo Edge (défaut). |
| Backend cloud Render | `https://gestionemployerbackend.onrender.com` | Aucun nœud Edge local n'est installé : la PWA sonde l'API cloud. |

> ⚠️ Ne jamais utiliser les domaines réservés `*.leopardo-rh.com` / `*.leopardo.app` :
> ils sont NXDOMAIN tant que le DNS de production n'est pas provisionné
> (source de vérité : `docs/ops/DOMAINS.md`, issues #3706/#3452).

### Contrat API

La PWA sonde `GET {EDGE_API}/api/v1/edge/health` (endpoint versionné, issue
#3719/#3772). Aucun appel non versionné `/api/edge/*` ne doit être
réintroduit. La synchronisation reste disponible depuis le nœud Edge
authentifié ; la PWA affiche un état honnête (hors-ligne / erreur) sans faux
bouton actif.
