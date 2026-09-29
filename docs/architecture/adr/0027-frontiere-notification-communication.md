# ADR 0027 — Frontière Notification / Communication : responsabilités, déplacement de `CommunicationEvent`, direction de dépendance

## Statut

Proposée — **validation owner requise** (critère d'acceptation de l'issue #8219 / BOS-025).

**Date** : 2026-09-28
**Décideurs** : Équipe technique Leopardo (proposition agent, issue #8219 / BOS-025, lot Z6 ; programme Business OS, `docs/architecture/business-os/09_EXECUTION_READINESS_REVIEW.md` PR #8138)

## Contexte

La frontière entre les modules `Notification` et `Communication` n'a jamais été
tranchée (#8219) :

- **Threads « dupliqués »** : `Notification.ConversationThread/ConversationMessage`
  (PA2-COMM-002 — discussions **internes** employé ↔ manager, ancrées sur un sujet
  RH : avance sur salaire, correction de pointage, absence) cohabitent avec
  `Communication.CommunicationThread/CommunicationMessage` (threads de **canaux
  externes** — boîtes mail connectées Gmail, relances commerciales). C'est une
  confusion de nommage plus qu'une vraie duplication fonctionnelle, mais chaque
  nouveau canal aggrave l'ambiguïté.
- **`CommunicationEvent` vit dans `Notification`**
  (`api/app/Modules/Notification/Domain/Models/CommunicationEvent.php`) alors
  qu'il est le **journal des événements de livraison multi-canal** (`sent`,
  `skipped`, `failed`, `bounced`, provider, template, `occurred_at`) — une
  donnée de messagerie externe, consommée par le module Platform
  (`CommunicationAnalyticsController`, observabilité), le webhook de bounce
  email, Cameras et EduManager.
- À l'inverse, `CommunicationService` (dans `Notification/Infrastructure`) est,
  malgré son nom, le **dispatcher de notifications employé** (préférences,
  heures calmes, quotas, canaux app/push/SMS/WhatsApp/email) — il implémente
  `App\Shared\Contracts\Notification\EmployeeNotifier` et sert une dizaine de
  modules métier. `config/communication.php` et `App\Contracts\Communication\*`
  relèvent de la même dette de nommage historique.

Sans décision, chaque ajout de canal (SMS marketing, WhatsApp campagnes, push)
renforce le couplage et rend les gardes d'isolation de modules muettes sur un
couplage réel.

## Décision

1. **Responsabilités canoniques.**
   - **Notification** = préférences de notification, push, annonces internes,
     notifications in-app, digests manager, et **discussions internes**
     (`ConversationThread/ConversationMessage` y restent — discussions RH
     internes, sans canal externe).
   - **Communication** = **canaux externes + threads externes** : intégrations
     boîtes mail (Gmail), threads/messages externes, relances (follow-ups),
     réponses (pending replies, politiques de réponse), propositions de
     contacts, et le **journal des événements de livraison**.
2. **`CommunicationEvent` est déplacé** vers
   `App\Modules\Communication\Domain\Models\CommunicationEvent` (classe
   canonique). La table `communication_events` est **inchangée** (aucun
   renommage de tables ; le nom de table par convention Eloquent est identique
   pour les deux FQCN). Un **alias de compatibilité déprécié** est conservé
   sous l'ancien FQCN `App\Modules\Notification\Domain\Models\CommunicationEvent`
   pour **1 release**, puis retiré (issue de suivi).
3. **Direction de dépendance : `Notification → Communication` autorisée,
   `Communication → Notification` interdite** (gardée par
   `check-module-isolation.sh` — tout nouvel import croisé fait échouer la CI).
   Le dispatcher de notifications *écrit* le journal de livraison ; le module
   Communication ne dépend de rien dans Notification. La relation historique
   `notification()` (jamais lue en production) reste disponible **uniquement
   sur l'alias**, pas sur la classe canonique.
4. **Pas de fusion des threads** (hors périmètre de #8219 — « fusion non
   décidée ») : le nommage est clarifié par la règle 1. Toute nouvelle table de
   thread doit être justifiée dans le module propriétaire selon cette frontière.
5. **`CommunicationService` (dispatcher) reste dans Notification** : c'est le
   moteur de notification employé (point 1), non la messagerie externe. Sa
   dette de nommage (`Communication*`, `App\Contracts\Communication\*`,
   `config/communication.php`) est **documentée ici** ; son renommage fait
   l'objet d'une issue de suivi — aucun renommage « au passage » (règle
   anti-drive-by du programme).
6. **Règle d'accueil des nouveaux canaux** : tout canal de **messagerie
   externe** (campagnes, boîtes connectées, SMS marketing) naît dans
   Communication ; tout canal de **notification employé** (push, in-app,
   digest, annonce interne) naît dans Notification.

## Conséquences

- **Positif** : frontière explicite et opposable ; le journal de livraison vit
  chez le module messagerie ; direction de dépendance acyclique vérifiable en
  CI ; migration sans rupture (alias + table inchangée + aucun changement
  d'API).
- **Coûts** : alias à retirer à la release suivante (suivi) ; la dette de
  nommage du dispatcher est conservée à court terme (choix assumé, point 5).
- **Consommateurs migrés** à l'import canonique dans la même PR (21 fichiers :
  services/controllers Notification, Platform, Cameras, EduManager,
  `app/Console`, et les suites de tests associées) — touches hors zone Z6
  minimales (lignes d'import uniquement) et signalées dans la PR.

## Règles opérationnelles

- Interdiction d'importer `App\Modules\Notification\*` depuis
  `App\Modules\Communication\*` (nouvelle violation = CI rouge).
- L'alias `@deprecated` ne doit plus être importé par du code nouveau ;
  son retrait est planifié à la release suivante (issue de suivi à créer au
  merge).
- Toute écriture dans le journal de livraison passe par la classe canonique
  `App\Modules\Communication\Domain\Models\CommunicationEvent`.
- Les événements cross-module concernés (enregistrement des événements de
  dispatch, webhook de bounce, analytics Platform) sont couverts par les
  suites existantes `tests/Feature/*Notification*`, `CommunicationServiceTest`,
  `EmailBounceWebhookControllerTest`, `CommunicationAnalyticsControllerTest` —
  elles doivent rester vertes sans modification de comportement.
