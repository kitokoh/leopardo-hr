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

## Statut de déploiement (#6595)

| Canal | Statut | Mécanisme |
|---|---|---|
| Nœud Edge local | ✅ Actif | Image `leopardo/edge-ui` construite depuis `front/web-offline/Dockerfile` (export statique) et publiée par `edge/publish.sh` ; service `edge-ui` du compose Edge (`7879:3000`). |
| Cloud (Vercel/Render) | ⏸️ Non déployé | Pas de `vercel.json` ni de service Render — décision actée : la PWA est une surface de secours **locale** (Edge), pas un service cloud. Le workflow `web-offline-ci.yml` couvre lint + tests + build (`npm run build`), pas de publication. |

L'URL de l'API est inlinée au build (`NEXT_PUBLIC_EDGE_API`, défaut
`http://leopardo.local:7878`) — un export statique ne relit pas les
variables d'environnement au runtime.
