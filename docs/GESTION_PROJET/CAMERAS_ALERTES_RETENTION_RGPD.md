# Caméras — alertes, snapshots : rétention et accès (RGPD)

Issue #7427 (BC-19 DEVICE) — critère d'acceptation 5 : « Aucun flux d'images
n'est enregistré sans que la rétention et l'accès soient documentés (RGPD). »

Ce document décrit ce que la tranche #7427 **fait** et ce qu'elle **ne fait
pas**. Aucune capacité biométrique n'est activée ni réutilisée ici.

## 1. Ce qui est stocké par l'API

| Donnée | Table | Contenu | PII |
| --- | --- | --- | --- |
| Événement détecté | `camera_events` | `company_id`, `camera_id`, `type` (`motion\|person\|vehicle\|line_crossing\|tamper`), `severity`, `detected_at`, `snapshot_path` (nullable), `metadata` (nullable), `timestamps` | **aucune** : pas d'image, pas de visage, pas de plaque, pas de coordonnées de personne |
| Alerte manager | `camera_alerts` | `alert_key` (dédoublonnage), `severity`, `status` (`open\|acknowledged\|resolved`), `camera_id`, `camera_event_id`, `acknowledged_by/at`, `resolved_by/at`, `payload` (identifiants + horodatage) | **aucune** : la traçabilité porte sur l'employé qui acquitte (identifiant interne), pas sur une personne filmée |

Le `type` `person` est une **classe de détection** (présence d'une forme
humaine) : ce n'est ni une identification, ni une reconnaissance faciale, ni un
gabarit biométrique. Le dépôt porte par ailleurs des capacités biométriques
distinctes (pointage) — elles ne sont **pas** touchées, pas confondues et pas
activées par ce lot.

## 2. Snapshots (images)

- Cette tranche **n'enregistre aucune image** : `camera_events.snapshot_path`
  est un simple champ optionnel renseigné par la chaîne vidéo si celle-ci a
  déjà écrit un fichier **hors API** (nœud Edge MediaMTX).
- L'API ne sert **jamais** le chemin de stockage aux clients : `GET
  /cameras/events` expose uniquement `has_snapshot` (booléen, défense en
  profondeur contre la fuite de chemins internes).
- La rétention des enregistrements vidéo est portée par la chaîne vidéo
  (ADR-0021) : segments de 1 h, **rétention 7 jours** par défaut, sur le nœud
  Edge du client. Cette rétention n'est pas dupliquée ici.

## 3. Qui peut voir quoi

- `GET /cameras/events`, `GET /cameras/alerts` : **manager** authentifié, borné
  à son tenant (`CameraAlertPolicy::viewAny`, scope global `company_id`).
- `POST /cameras/alerts/{alert}/acknowledge|resolve` : **manager** de la même
  société ; une alerte d'un autre tenant est **invisible et non acquittable**
  (404).
- `POST /internal/camera-events` : **machine-à-machine uniquement**, protégé par
  le secret partagé MediaMTX (`Authorization: Bearer
  <CAMERAS_MEDIAMTX_SECRET>`). Aucun utilisateur (ni manager, ni employé) ne
  peut appeler cette surface ; hors `local`/`testing`, un secret absent ou
  invalide vaut **401** (fail-closed).
- Les accès en lecture au flux restent journalisés par le module Caméras
  (`camera_access_logs`, jetons tiers, permissions par caméra) — inchangé par
  ce lot.

## 4. Rétention des événements et alertes

- Aucune purge automatique n'est livrée dans cette tranche : les lignes
  `camera_events` / `camera_alerts` restent dans le schéma tenant jusqu'à
  suppression de la caméra (`ON DELETE CASCADE` sur `camera_id`) ou demande
  d'effacement du tenant.
- **Recommandation opérationnelle** : aligner la purge des événements sur la
  rétention vidéo (7 jours) via une tâche planifiée dédiée, et conserver les
  alertes (acquittées/clôturées) plus longtemps — elles constituent la piste
  d'audit des accès. Cette purge est **à planifier** (hors périmètre #7427,
  à tracer si le propriétaire la veut).
- Les métadonnées d'alerte ne doivent contenir **aucune** donnée personnelle :
  la règle est celle de `FuelAlert` (#5813), le payload alimenté par l'API ne
  porte que des identifiants internes et un horodatage.

## 5. Audit et visibilité des échecs

Chaque envoi (app / push) est audité par `CommunicationService` dans
`communication_events` (statut par canal, `error_message`, `template_key`
`camera_security_alert`). Un échec définitif est journalisé par
`DispatchCommunicationJob::failed()`. Sans manager actif joignable, le service
écrit une trace structurée `cameras.alert.no_manager` (sans PII) : une alerte
sans destinataire est un trou opérationnel visible, pas un échec silencieux.
