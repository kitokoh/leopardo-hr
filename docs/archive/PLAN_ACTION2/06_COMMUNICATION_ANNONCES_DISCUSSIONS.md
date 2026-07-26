# Scope communication, annonces et discussions

Version: 1.0  
Date: 2026-06-13

## Objectif

Leopardo doit devenir le centre de communication operationnelle de l'entreprise terrain, sans devenir une messagerie lourde. La communication doit rester liee aux workflows: pointage, taches, avances, absences, paie, documents, annonces et support.

## Canaux

| Canal | Usage | Priorite |
|---|---|---|
| In-app | source de verite, historique, lu/non lu | P0 |
| Push FCM | rappels temps reel mobile | P0 |
| Email | onboarding, documents, recaps, alertes importantes | P1 |
| WhatsApp | annonces terrain et rappels sensibles avec opt-in | P1 |
| SMS | fallback zones faibles, quotas stricts | P2 |

## Espaces a livrer

1. Discussion employee-manager liee aux taches, salaire, pointage ou demande.
2. Commentaires sur taches et demandes de correction.
3. Annonces entreprise vers tous, departement, equipe ou employe.
4. Annonces plateforme vers clients, managers, superadmins.
5. Historique notifications par utilisateur.
6. Preferences utilisateur: canaux, quiet hours, opt-in WhatsApp/SMS.

## Regles de securite

- Toute discussion est tenant-scope.
- Les messages sensibles ne partent jamais en clair dans les metadonnees push.
- WhatsApp/SMS exigent consentement, opt-out et journalisation.
- Les annonces plateforme doivent distinguer information, maintenance, incident et action requise.
- Les envois externes passent par jobs, retries, failed jobs et quotas.

## Definition of Done technique

- `CommunicationService` reste l'orchestrateur central.
- Les providers externes sont abstraits par interface.
- Les templates sont versionnes et localisables.
- Les envois ecrivent un audit `communication_events`.
- Les apps mobiles affichent inbox, detail, lu/non lu et preferences.
- Les dashboards web exposent au moins creation annonce et suivi de diffusion.

