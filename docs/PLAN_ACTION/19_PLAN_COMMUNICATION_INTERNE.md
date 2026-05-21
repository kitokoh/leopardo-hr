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

## Lot 19.2 - Emails transactionnels

Priorite : critique

Livrables :

- Templates email versionnes pour invitation, reset password, absence approuvee/refusee, bulletin disponible, paie validee, demo demandee.
- Choix provider : Resend/Brevo free tier en dev, Amazon SES ou Resend Pro en production.
- Queue `emails` separee.
- Tracking technique : delivered, bounced, failed si le provider l'expose.
- Pages vitrine : formulaires demo/newsletter reliees a une notification interne et un email accuse reception.

Tests :

- Snapshot HTML/text des templates.
- Envoi fake mail en CI.
- Anti-regression multilingue et fallback.

## Lot 19.3 - Push web et mobile

Priorite : haute

Livrables :

- Web Push pour le portail client avec consentement explicite.
- Mobile push via Firebase Cloud Messaging.
- Stockage des device tokens par tenant/utilisateur.
- Endpoint `POST /api/v1/devices` et `DELETE /api/v1/devices/{id}`.
- Evenements cibles : absence approuvee/refusee, retard detecte, bulletin disponible, invitation, rappel onboarding.

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
- Templates courts sans donnees sensibles.
- Opt-in/opt-out explicite par canal.
- Webhook provider pour statuts d'envoi.
- Quotas par plan pour eviter explosion couts.

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

- Moteur de regles : canal prefere, fallback, heures calmes, urgence.
- Analytics : taux lu, taux echec, canaux les plus utilises par tenant/langue/module.
- Integration future IA : l'agent peut proposer un message, mais l'envoi actionnable exige permission et audit.
- Playbook support : relancer une invitation, prevenir un manager, notifier une equipe.

Tests :

- Regles de fallback.
- Permissions IA sur actions de communication.
- Export audit.

## Roadmap d'execution

1. Implementer lot 19.1 + migration + API + tests.
2. Brancher lot 19.2 avec provider email configure par environnement.
3. Ajouter push web/mobile avec device registry.
4. Integrer SMS/WhatsApp en mode provider fake puis provider reel.
5. Ajouter analytics, quotas, dashboard super-admin et commandes IA encadrees.

## Risques

- Cout SMS/WhatsApp si les quotas ne sont pas poses des le depart.
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
