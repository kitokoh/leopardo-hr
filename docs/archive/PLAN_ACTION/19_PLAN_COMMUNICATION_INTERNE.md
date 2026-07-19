# 19 - Plan modernisation communication interne

Date : 2026-05-22

## Objectif

Construire une couche de communication interne moderne, fiable et economique pour Leopardo RH : notifications web et mobile, emails, SMS et WhatsApp, avec audit, preferences utilisateur, templates multilingues et controles anti-abus.

Cette couche ne remplace pas les workflows RH existants. Elle les rend visibles, actionnables et tracables pour les employes, managers, RH et super-admins.

## Principes

- Un seul domaine metier `Communication` pour orchestrer les canaux.
- Aucune notification critique sans audit : qui, quoi, canal, statut, erreur, date.
- Preferences par utilisateur et par canal, sauf obligations legales ou securite.
- Templates multilingues FR/EN/TR/AR avec fallback clair.
- Envoi asynchrone via queue, retry et dead-letter.
- Provider interchangeable : gratuit/dev au depart, scalable ensuite.
- Respect privacy : pas de donnees paie completes dans SMS/WhatsApp, seulement liens securises.

## Lot 19.1 - Socle notifications applicatives

Priorite : critique

Statut : livre 2026-05-22.

Livrables :

- Table tenant `notification_preferences` par employe/utilisateur.
- Table tenant `communication_events` pour tracer tous les messages.
- API `GET /api/v1/notifications`, `PATCH /api/v1/notifications/{id}/read`, `PATCH /api/v1/notification-preferences`.
- Web client : centre de notifications lisible dans le dashboard.
- Mobile : contrat API stable pour badge, liste, lecture et action.
- Kiosque : affichage minimal des alertes appareil/synchro si associe a un site.

Tests :

- Isolation tenant.
- Preferences respectees.
- Lecture notification par proprietaire seulement.
- Badge non lu coherent.

Note 2026-05-22 : le backend possede deja la liste/lecture de notifications. Le lot 19.1 etend ce socle avec `notification_preferences`, `communication_events`, API preferences, centre de notifications visible dans le portail client web et page utilisateur `/settings/notifications`.

## Lot 19.2 - Emails transactionnels

Priorite : critique

Livrables :

- Templates metier versionnes dans `config/communication.php` pour absence approuvee/refusee, bulletin disponible, alerte securite et fallback generique.
- Choix provider : provider mail Laravel par defaut, Resend/Brevo/SES activables par configuration production.
- Queue `notifications` dediee via `COMMUNICATION_QUEUE`.
- Tracking technique : `communication_events` trace queued/sent/skipped/failed ; delivered/bounced reste a brancher quand un provider expose le webhook.
- Pages vitrine : formulaires demo/newsletter reliees a une notification interne et un email accuse reception.

Statut : socle livre 2026-05-22. Les emails transactionnels critiques passent maintenant par l'orchestrateur `CommunicationService`; les webhooks provider restent un lot ops/provider.

Tests :

- Snapshot HTML/text des templates.
- Envoi fake mail en CI.
- Anti-regression multilingue et fallback.

## Lot 19.3 - Push web et mobile

Priorite : haute

Livrables :

- Web Push pour le portail client avec consentement explicite.
- Mobile push via Firebase Cloud Messaging.
- Stockage des device tokens par tenant/utilisateur via `/api/v1/device-tokens`.
- Envoi test manager via `/api/v1/push-notifications/send`, desormais route par `CommunicationService`.
- Evenements cibles : absence approuvee/refusee, retard detecte, bulletin disponible, invitation, rappel onboarding.

Statut : socle livre 2026-05-22. Le registry device et FCM existaient deja ; le dispatch est maintenant audite et respecte les preferences utilisateur. Le consentement Web Push navigateur reste a finaliser cote PWA quand le worker public sera stabilise.

Outils :

- Gratuit : Firebase Cloud Messaging pour Android/iOS, Web Push natif.
- A evaluer plus tard : OneSignal si besoin d'un dashboard marketing/support plus riche.

Tests :

- Token device tenant-scope.
- Revocation device.
- Queue fake pour verifier payload sans envoyer a Firebase.

## Lot 19.4 - SMS et WhatsApp

Priorite : haute mais a activer progressivement

Livrables :

- Abstraction `MessageProviderInterface` pour SMS/WhatsApp.
- Provider audit-only par defaut pour developpement/CI sans cout externe.
- Templates courts sans donnees sensibles.
- Opt-in/opt-out explicite par canal.
- Webhook provider pour statuts d'envoi.
- Quotas par plan pour eviter explosion couts.

Statut : livre 2026-05-22. SMS/WhatsApp sont opt-in, audites et sans donnees sensibles ; les quotas mensuels par canal sont appliques via `COMMUNICATION_SMS_MONTHLY_QUOTA` et `COMMUNICATION_WHATSAPP_MONTHLY_QUOTA` (0 = illimite). Les providers reels et signatures webhook restent a activer au moment du choix fournisseur.

Outils :

- SMS gratuit/dev : aucun provider SMS gratuit fiable en production ; utiliser mode fake/sandbox en dev.
- SMS production : Twilio, Vonage, Infobip ou fournisseur local selon pays.
- WhatsApp gratuit/dev : Meta WhatsApp Cloud API test number.
- WhatsApp production : WhatsApp Cloud API ou partenaire BSP selon volume et support.

Tests :

- Aucun contenu sensible dans les payloads.
- Canal bloque si opt-out.
- Quota plan applique.
- Webhook signature verifiee.

## Lot 19.5 - Orchestration, analytics et IA-ready

Priorite : moyenne

Livrables :

- Moteur de regles : preferences canal/categorie, fallback app, provider audit-only, metadata allowlist.
- Analytics : base `communication_events` exploitable pour taux lu, taux echec, canaux les plus utilises par tenant/langue/module.
- Integration future IA : l'agent peut proposer un message, mais l'envoi actionnable exige permission et audit.
- Playbook support : relancer une invitation, prevenir un manager, notifier une equipe.

Statut : livre 2026-05-22. Les heures calmes sont maintenant appliquees par l'orchestrateur sur les canaux externes, avec bypass securite configurable. `GET /api/v1/communication/analytics` expose les volumes, echecs, statuts, canaux et templates aux managers `principal` et `rh`.

Tests :

- Regles de fallback.
- Permissions IA sur actions de communication.
- Export audit.

## Roadmap d'execution

1. Fait : implementer lot 19.1 + migration + API + tests.
2. Fait : brancher l'orchestrateur avec provider email configure par environnement.
3. Fait : relier push web/mobile au device registry existant et a l'audit communication.
4. Fait : integrer SMS/WhatsApp en mode provider audit-only.
5. Fait : analytics API tenant, quotas canaux coutants et application stricte des heures calmes.
6. Suite produit : visualiser ces analytics dans le super-admin, puis encadrer les commandes IA de communication avec validation humaine.

## Risques

- Cout SMS/WhatsApp si les quotas ne sont pas alignes avec les futurs plans commerciaux.
- Donnees sensibles dans les messages courts si les templates ne sont pas controles.
- Fatigue utilisateur si les preferences et heures calmes arrivent trop tard.
- Deliverability email si domaine, SPF, DKIM, DMARC ne sont pas configures.
- Fragmentation si chaque module envoie directement ses messages sans passer par l'orchestrateur.

## Definition of done

- Chaque workflow RH critique peut notifier via au moins un canal fiable.
- L'utilisateur peut regler ses preferences.
- Les envois sont queues, audites et retryables.
- Les canaux gratuits/dev sont utilisables sans bloquer CI.
- Les providers production sont interchangeables par configuration.
- Les canaux externes respectent les heures calmes et les quotas.
- Les managers autorises disposent d'une synthese analytics communication.
