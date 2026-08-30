# Audit sécurité & RGPD — TravelAgency avant pilote (TRAVEL-1013, issue #6126)

> **Statut : livré (lot 3b).** Périmètre : PII passagers, paiements, billets, exports, notifications.

## 1. Données personnelles (PII)

| Donnée | Stockage | Exposition API |
|---|---|---|
| Nom passager | `travel_passengers.full_name` | Oui (nécessaire) |
| N° de pièce d'identité | `document_number_encrypted` (SensitiveDataEncryptor) + hash SHA-256 | **Jamais** (colonnes `$hidden`, Resource sans le champ) |
| Date de naissance | `birth_date` | Oui (minimisé) |
| Email/téléphone contact | `travel_bookings.contact_email/contact_phone` | Oui (contact), consentement tracé |
| Coordonnées géo sites | `travel_tourist_sites` (publiques) | Oui (annuaire) |

## 2. Paiements

- Secrets en config/env (`travel.payments.*`, `TWILIO_*`, `WHATSAPP_*`) — jamais en dur.
- Callback **signé HMAC-SHA256**, fail-closed sans secret configuré (TRAVEL-409).
- Vérification du montant au callback (anti-fraude) ; payloads redacted
  (`callback_payload_redacted`, jamais de signature/secret).
- Montants en unités mineures (pas de flottants) ; devise de référence canonique.

## 3. Billets

- Code de validation **haché** au repos (SHA-256) — le code en clair n'est
  retourné qu'à l'émission (QR) et n'est jamais persisté.
- PDF : URL signée temporaire (30 min), révocation (`void` → 410).
- Accès public au PDF uniquement avec code de validation valide.

## 4. Exports & rapports

- Export CSV : colonnes **allowlistées** (aucune PII non nécessaire),
  URL signée 30 min, historique borné (50/tenant).
- Rapports : agrégats uniquement (jamais de passagers nominatifs).

## 5. Notifications & consentement

- **Aucune notification sans canal configuré ET consentement actif**
  (`travel_notification_consents`, révocable — TRAVEL-415).
- WhatsApp : jamais de données financières (spec §8.5).
- Journal d'audit `travel_notification_logs` (payload redacted).

## 6. Multi-tenant

- `company_id` non nullable + `BelongsToCompany` fail-closed
  (`tenant_scope_required`) ; 404 sûr cross-tenant (jamais 403).
- Boutique publique : jeton tenant signé, tests cross-tenant négatifs.
- Import legacy : tenant-scoped, dry-run avant écriture.

## 7. Traçabilité & conformité

- Auditable : événements outbox `travel.*.v1` (idempotents), transitions
  d'état horodatées avec opérateur.
- Droit d'effacement : suppression des réservations (cascade passagers) ;
  journal de notifications conservé sans PII inutile.
- Retention : politique documentée par tenant ; billets PDF révocables.

## 8. Recommandations avant pilote

1. Configurer les secrets réels (callbacks, Twilio/WhatsApp) hors sandbox.
2. Activer le hook anti-bot de la boutique publique (`captcha_secret`).
3. Réaliser un test d'intrusion léger sur le tunnel public (rate limiting,
   rotation de jeton).
4. Verrouiller `BACKEND_COVERAGE_MIN` et les gates CI avant GO.
