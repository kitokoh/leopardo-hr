# TERMES — lexique produit opposable (protocole P03)

Termes autorisés / interdits sur toutes les surfaces (vitrine, README, docs, pitchs).

| Contexte | À dire | À ne PAS dire |
|---|---|---|
| Nom produit | Leopardo RH | Leopardo HR (EN marketing ok : "Leopardo RH") |
| Site marketing | la vitrine | le site web (ambigu) |
| Espace client | portail client / dashboard | back-office |
| Super-admin | plateforme / admin plateforme | admin client |
| Pointage | pointage mobile/kiosk/biométrie | chronométrage |
| Paie pays | règles pays en cours de validation (statut pilot) | paie 100 % conforme |
| Statut produit | open-source, multi-tenant, mobile-first | « entreprise » tant que non prouvé |
| Apps | Leopardo Employee / Manager / RH / Platform Admin / Accounting / Marketing / Travel Agent | « l'app » (ambigu) |

Règle : toute nouvelle surface de présentation doit reprendre ce lexique — écart = bug de contenu (label `content`).

## Renvois — règles opposables qui ne sont pas du lexique

Ce fichier est un **lexique** (ce qu'on dit / ce qu'on ne dit pas). Certaines
règles opposables ne sont pas du vocabulaire : elles vivent dans leur document
de référence. Elles sont citées ici pour que les rédacteurs les trouvent — la
formulation opposable reste celle du document cité.

| Sujet | Règle | Référence |
|---|---|---|
| Notifications in-app | Le canal cible est `app_notifications` (via `NotificationDispatcher` / contrat Core `InAppNotifier`). Ne pas ouvrir de **nouvel** émetteur sur la table historique `notifications` — garde CI `notification-emitter-guard.yml`. | `docs/architecture/adr/0013-notifications-read-path-unification.md` |
| Chaîne vidéo | La vidéo tourne sur le **nœud Edge**, chez le client ; l'API cloud reste la source de vérité des droits. | `docs/architecture/adr/0021-chaine-video-topologie-edge.md` |
