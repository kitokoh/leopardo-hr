# Chaîne vidéo Leopardo (MediaMTX) — nœud Edge

Ce dossier **versionne** la chaîne vidéo du module Caméras (issue **#7424**).
Avant lui, le dépôt déclarait le contrat (jeton de flux, secret partagé,
endpoint interne de vérification) sans qu'aucun service, aucune configuration
et aucun code d'enregistrement n'existent : le `stream_url` renvoyé au client
pointait vers un hôte inexistant.

## Contenu

| Fichier | Rôle |
|---|---|
| `mediamtx.yml` | Configuration MediaMTX : API de contrôle locale, WebRTC/HLS en lecture, authentification déléguée à Leopardo, politique d'enregistrement. |
| `README.md` | Ce document : montage, valeurs à remplacer, rétention, limites. |

Le service est déclaré dans `edge/docker-compose.yml` sous le profil
`cameras` — il ne démarre donc **pas** dans une installation Edge standard :

```bash
cd edge
docker compose --profile cameras up -d
```

## Les deux valeurs à remplacer

`mediamtx.yml` porte un hôte en `REMPLACER_API_HOST`. La configuration est
montée en lecture seule : l'opérateur (ou `edge/install.sh`) remplace :

1. **`authHTTPAddress`** → `https://<api-leopardo>/api/v1/internal/camera-token/verify`
2. le secret partagé, **jamais** dans ce fichier : il est fourni à MediaMTX par
   l'agent Edge au moment de la vérification (en-tête
   `Authorization: Bearer <CAMERAS_MEDIAMTX_SECRET>`, secret côté Leopardo dans
   `config/cameras.php` → `mediamtx_secret`).

> Le templating automatique de ce fichier par `install.sh` n'est **pas** fait
> ici : c'est un pas d'installation explicite, documenté, pas une invention de
> mécanisme.

## Pourquoi le nœud Edge

Un flux RTSP ne doit pas traverser l'Internet public depuis le site du client.
Le nœud Edge est déjà installé chez lui (`edge/`), il joint les caméras sur le
réseau local, et il est le seul point qui a besoin de parler à l'API cloud.
Décision et alternatives : `docs/architecture/adr/0021-chaine-video-topologie-edge.md`.

## Flux d'authentification

```
navigateur ──(stream_token JWT)──> MediaMTX (nœud Edge)
                                      │
                                      └──(Bearer mediamtx_secret)──> Leopardo
                                            GET /api/v1/internal/camera-token/verify
                                            → permissions caméra + journal d'accès
```

Une seule source de vérité pour les droits : Leopardo. MediaMTX ne duplique
aucune règle.

## Rétention et vie privée

- Enregistrements : `/var/leopardo/cameras/<chemin>/`, segments de 1 h,
  **rétention 7 jours** par défaut (`recordDeleteAfter`). À arbitrer avec le
  client : c'est une donnée personnelle.
- Les lectures passent par `camera_access_logs` (déjà présent côté Leopardo) —
  l'endpoint de vérification est le point où l'accès est journalisé.
- Aucun identifiant RTSP n'est écrit dans cette configuration ni journalisé
  côté MediaMTX.

## Ce qui reste à faire (assumé, non fait ici)

1. **Enregistrement à chaud des chemins** : l'agent Edge doit appeler l'API de
   contrôle de MediaMTX (`POST /v3/config/paths/add/cam_<id>`) à partir de la
   liste des caméras du tenant, avec l'URL RTSP déchiffrée. La configuration
   porte déjà le motif et la politique ; le câblage applicatif reste à écrire.
2. **Stockage des snapshots** (distinct de l'enregistrement continu).
3. **Activation du flag `cameras`** pour un tenant pilote — acte
   d'exploitation, pas de code (flag à `false` par défaut,
   `api/config/feature-flags.php`).
