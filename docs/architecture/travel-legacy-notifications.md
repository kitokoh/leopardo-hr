# Notifications legacy gv-back → canaux plateforme (TRAVEL-910, issue #6113)

**Statut : livré (lot 2, PR `feat/travel-batch2-community`).**

## Décision

Le legacy gv-back disposait d'une file maison de notifications
(mail/SMS manuelles). **Cette table n'est PAS reproduite** : le besoin est
migré vers les canaux plateforme BC-13, conformément à la spec §8.5
(« Notifications (mail/SMS/WhatsApp) → BC-13 COMMS, canal configuré +
consentement »).

## Mapping gv-back → plateforme

| Besoin legacy | Événement Travel (outbox) | Consommateur plateforme |
|---|---|---|
| Confirmation de réservation | `travel.booking.confirmed.v1` | `TravelNotificationConsumer` → canaux BC-13 (mail/SMS/WhatsApp) |
| Annulation | `travel.booking.cancelled.v1` | idem |
| Paiement confirmé | `travel.payment.confirmed.v1` | idem |
| Remboursement | `travel.payment.refunded.v1` | idem |
| Billet émis | `travel.ticket.issued.v1` | idem |
| Trajet annulé (massif) | `travel.trip.cancelled.v1` | idem (notification massive) |
| Rappels / expiration | `travel.booking.expired.v1` | idem |

## Règles (critère d'acceptation)

1. **Aucune table « notifications » maison** : pas de `travel_notifications`,
   pas de file mail locale — vérifié par le test
   `TravelLegacyNotificationContractTest` (aucune migration legacy).
2. **Canaux plateforme uniquement** : tout envoi passe par
   `TravelNotificationService` (issue #6067, TRAVEL-415) qui n'émet
   **jamais** sans canal configuré ET consentement actif
   (`travel_notification_consents`).
3. **WhatsApp sans données financières** (spec §8.5).

## Références

- Spec : `docs/specifications/SOLUTION_TRAVEL_AGENCY.md` §3 (fonctionnalités
  legacy), §8.5 (intégrations transversales).
- Implémentation : `api/app/Modules/TravelAgency/Infrastructure/Services/TravelNotificationService.php`,
  `TravelNotificationConsumer.php`.
