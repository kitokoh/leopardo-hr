# ADR 0021 - Topologie de la chaîne vidéo : nœud Edge

## Statut

Acceptee.

**Date** : 2026-09-14
**Décideurs** : Équipe technique Leopardo HR (proposition agent, issue #7424)

## Contexte

Le module Caméras (BC-19 DEVICE) est livré côté API : CRUD, jetons de flux
JWT, permissions, journal d'accès, 22 tests verts, contrat OpenAPI. Son
contrat **référence** une chaîne vidéo :

- `api/routes/modules/cameras.php` — `GET /internal/camera-token/verify`,
  « appelé par MediaMTX, auth par Bearer secret dédié » ;
- `api/config/cameras.php` — secret partagé, `stream_base_url`, test RTSP ffprobe ;
- `CameraStreamTokenService` — JWT HS256 autorisant la lecture d'un flux.

Or **rien de tout cela n'existait** : aucun service MediaMTX dans
`docker-compose.yml` ni `render.yaml`, aucun fichier de configuration
versionné, aucun code d'enregistrement ni de snapshot. Le `stream_url` remis
au client pointait vers un hôte inexistant, et l'entrée « Surveillance Vidéo »
de l'admin plateforme ne menait à aucune surface.

Trois topologies étaient envisageables pour l'ingestion RTSP → WebRTC/HLS :
(a) un service dédié chez l'hébergeur, (b) un nœud **Edge** installé chez le
client, (c) un agent on-premise autonome.

## Décision

Retenir **(b) le nœud Edge** : la chaîne vidéo tourne chez le client, à côté
des caméras, et c'est le nœud Edge qui parle au cloud.

## Justification

1. **Un flux RTSP ne doit pas traverser l'Internet public depuis le site du
   client.** Avec (a), chaque caméra publierait vers l'hébergeur : bande
   passante sortante payée par le client, latence, et surface d'exposition.
2. **Le nœud Edge existe déjà** (`edge/` : stack Docker installée chez le
   client, sync offline-first, licence). Ajouter MediaMTX à cette stack
   réutilise un composant déployé, supervisé et déjà documenté — au lieu
   d'introduire une troisième cible d'exploitation.
3. **Les caméras sont sur le réseau local du client** : le nœud Edge les
   joint directement, sans NAT ni redirection de ports.
4. **Cohérence de responsabilité** : le cloud reste la source de vérité des
   droits (jetons, permissions, journal d'accès) ; l'Edge ne fait que
   relayer une décision qu'il n'a pas prise.

## Conséquences

**Positives**

- Le flux ne sort pas du site du client ; seul le *contrôle* (vérification de
  jeton, permissions) remonte au cloud.
- Une seule source de vérité pour les droits : l'endpoint de vérification.
- Pas de nouvelle cible d'exploitation : MediaMTX est un service de plus dans
  `edge/docker-compose.yml`, profil `cameras`.

**Négatives / à assumer**

- **Dépendance à l'Edge** : sans nœud Edge installé, le module Caméras n'a
  pas de chaîne vidéo. Un client qui refuserait l'Edge ne peut pas utiliser
  la vidéo — c'est un choix assumé, pas un défaut.
- MediaMTX devient un composant à versionner et à mettre à jour chez le
  client : même régime que le reste de la stack Edge (images taggées, pas de
  `latest` flottant).
- La rétention (7 jours par défaut) vit chez le client : elle doit être
  arbitrée contractuellement, car c'est une donnée personnelle.

## Alternatives écartées

- **(a) Service d'ingestion chez l'hébergeur** : simple à déployer, mais fait
  sortir les flux du site du client et impose de payer la bande passante
  entrante. Écartée pour cette raison.
- **(c) Agent on-premise dédié, distinct de l'Edge** : duplique l'installation,
  la supervision et la mise à jour d'un composant qui existe déjà. Écartée.

## Références

- Issue #7424 (constat et proposition DevOps)
- `edge/mediamtx/` (configuration versionnée) et `edge/mediamtx/README.md`
- `api/config/cameras.php`, `api/routes/modules/cameras.php`
- Protocole P07 (architecture dev/prod) — `docs/PROTOCOLES/`
