# DEP-BC24 — Matrice d'audit Travel OS (module `TravelAgency`)

> **Périmètre :** mission « LEOPARDO BUSINESS OS — TRAVEL OS OPÉRATIONNEL ».
> **Base auditée :** `main` @ `4f23259bc` puis fusion `main` @ `c16252bdf`
> dans la branche `bc/bc-24-travel-os-qr-gps-concurrency`.
> **Méthode :** lecture du code réel + `grep` ciblés + exécution de tests.
> Aucune ligne de cette matrice n'est déduite d'une intention : chaque statut
> cite le fichier qui le prouve.
> **Statuts :** ✅ existant · 🟡 incomplet/partiel · 🔴 défectueux · ⛔ absent · ❓ à vérifier

## 1. Volumétrie auditée

| Élément | Valeur mesurée |
|---|---|
| Fichiers PHP du module | 378 (`api/app/Modules/TravelAgency/**`) |
| Tests Feature Travel | 115 fichiers (`api/tests/Feature/Travel/**`) |
| Migrations Travel (tenant) | 79 (`api/database/migrations/tenant/*travel*`) |
| Contrôleurs API | 49 (`.../Interfaces/Api/V1/Controllers/`) |
| Routes | `api/routes/modules/travelagency.php` (≈270 lignes) |
| App mobile agent | `front/mobile_apps/leopardo_travel_agent` (18 fichiers Dart) |
| Portail public | `front/travel-web` |

**Conclusion de cadrage :** le module TravelAgency est **déjà largement
opérationnel**. La mission ne consistait donc **pas** à le reconstruire, mais à
combler des lacunes vérifiables. C'est la règle appliquée : aucune table, aucun
modèle, aucune route existante n'a été renommé, réinitialisé ou remplacé.

## 2. Matrice fonctionnelle

| # | Domaine | Statut | Preuve (fichier / commande) | Écart traité ? |
|---|---|---|---|---|
| 1 | **Entreprise & agences multiples** — bureaux de vente multi-villes | ✅ | `Domain/Models/TravelOffice.php`, routes `GET|POST /travel/offices`, `TravelOfficeCrudTest` | — (existant) |
| 2 | **Isolation des données par entreprise** | ✅ | `App\Shared\Traits\BelongsToCompany`, `company_id` sur tous les modèles, cross-tenant → 404, `TravelIsolationTest` | — (existant) |
| 3 | **Employés, rôles, affectations** | ✅ | `Domain/Models/TravelStaffAssignment.php` (`office_id` **ou** `trip_id`), routes `/travel/staff-assignments`, `TravelStaffAssignmentTest` | — (existant) |
| 4 | **Matrice de permissions** | ✅ | `docs/security/TRAVEL_RBAC_MATRIX.md`, `TravelAgencyManifest::permissions()`, `TravelRbacMatrixTest` | — (existant) |
| 5 | **Réseau** — gares, compagnies, classes, villes/pays | ✅ | `TravelStation*`, `TravelCarrier*`, `TravelClass*`, `TravelCountry/City` + CRUD tests | — (existant) |
| 6 | **Trajets** — routes, étapes, trajets datés, tarifs par classe | ✅ | `TravelRoute*`, `TravelTrip*`, `TravelTripPrice*`, génération transactionnelle des sièges | — (existant) |
| 7 | **Guichet** — vente au comptoir | ✅ | `POST /travel/bookings` (`booking_source=office`), `TravelBookingApiTest` ; **app agent** `leopardo_travel_agent` (`booking_flow_screen.dart`, `trip_search_screen.dart`, `bookings_screen.dart`) | — (existant) |
| 8 | **Caisse / PDV** — session ouverte, reçu | ✅ | `TravelPdvController` (`/travel/pdv/session/open|current|close`, `/pdv/receipts/{booking}`), `TravelPdvApiTest`, `pdv_screen.dart` | — (existant) |
| 9 | **Réservations en ligne** | ✅ | `/travel/shop/*` + surface publique passager, `TravelShopTest`, `TravelPublicShopPassengerPortalTest` | — (existant) |
| 10 | **Paiements** — initiation, callback HMAC, vérification, remboursement | ✅ | `TravelPaymentController`, callbacks signés, idempotence ; `TravelPaymentTest`, `TravelPaymentReconcileTest` | — (existant) |
| 11 | **Bookings sans double attribution de siège** | ✅ | verrouillage transactionnel des sièges, test « 2 réservations concurrentes → 1 seule obtient le siège » (`SCENARIOS_TEST_API_GITHUB_ACTIONS.md` §BC-24) | — (existant) |
| 12 | **Billet — PDF + référence unique** | ✅ | `TravelTicketPdfGenerator`, `resources/views/travel/ticket.blade.php`, `TravelTicketPdfTest` | — (existant) |
| 13 | **Billet — QR code réel** | ✅ **corrigé** | **Avant :** bloc `.qrcode` du blade sans image, aucune lib QR dans `composer.json`. **Après :** `TravelTicketQrCode` (`bacon/bacon-qr-code`, `GDLibRenderer`, PNG en mémoire → `data:` URI), `TravelTicketPdfGenerator` + blade mis à jour, test `test_qr_png_is_a_valid_image` / `test_pdf_embeds_qr_data_uri` | **#8538** |
| 14 | **Vérification d'authenticité du billet** | ✅ **ajouté** | **Avant :** aucun endpoint. **Après :** `POST /travel/tickets/verify` → `VerifyTicketAction`, lecture seule, statut `valid/void/expired/already_used`, code invalide **et** référence inconnue → même `404`, cross-tenant → `404`, identité passager seulement si `checkIn` autorisé | **#8538** |
| 15 | **QR sans données personnelles** | ✅ **ajouté** | Charge utile `GV1|<référence>|<code de contrôle>` — HMAC-SHA256 (`canonicalValidationCode`), jamais persisté en clair ; test `test_qr_payload_carries_no_personal_data` | **#8538** |
| 16 | **Check-in / embarquement concurrent-safe** | ✅ **corrigé** | **Avant :** statut lu **hors** transaction, sans verrou → deux contrôles simultanés pouvaient écrire tous les deux. **Après :** relecture **dans** la transaction sous `lockForUpdate()`, second appel idempotent sans écraser `checked_in_by_user_id`/`checked_in_at`, événement outbox publié une seule fois | **#8539** |
| 17 | **Dernière position connue des véhicules** | ✅ **ajouté** | **Avant :** `TravelVehicle` sans aucun champ de localisation. **Après :** migration `2026_10_16_000001_8540` (colonnes nullables + `CHECK` bornes/source), `TravelVehiclePositionSource` (`gps`\|`app`\|`estimated`, `isMeasured()`), `POST /travel/vehicles/{id}/position` | **#8540** |
| 18 | **Aucune position inventée** | ✅ respecté | Sans dispositif → `position: null` dans `TravelVehicleResource` ; `estimated` exposé `is_measured: false` | **#8540** |
| 19 | **Scan caméra du QR (mobile)** | ⛔ **absent** | `grep mobile_scanner\|qr_code_scanner\|barcode front/mobile_apps/*/pubspec.yaml` → **0 résultat**. L'app embarque `qr_flutter` (génération/affichage uniquement, `booking_flow_screen.dart:687`) | issue ouverte |
| 20 | **Réimpression d'un billet avec trace** | ⛔ **absent** | `grep reprint\|reimpress\|audit` dans `TravelTicketPdfStorage.php` et `TravelTicketController.php` → **0 résultat**. `GET /travel/tickets/{ticket}/pdf` régénère et re-signe sans historiser l'opération | **issue principale** |
| 21 | **Impression thermique / ESC-POS** | ⛔ **absent** | `grep escpos\|esc_pos\|thermal\|receipt_printer\|bluetooth.*print` sur `api/app` + `front/mobile_apps` → **0 résultat**. L'impression réelle repose sur le PDF dompdf (navigateur ou partage système) | issue ouverte |
| 22 | **Vue consolidée par agence** | 🔴 **absent** | `TravelOffice` existe, mais `travel_bookings` **n'a pas** de colonne `office_id` (`grep office_id database/migrations/tenant/*booking*` → 0) et `TravelReportService` **ne connaît pas** l'office (`grep office` → 0). Les rapports sont donc consolidés au niveau entreprise uniquement, sans ventilation par agence | issue ouverte |
| 23 | **Suivi temps réel GPS / télémétrie / fournisseur de flotte** | ⛔ **absent** | Aucun adaptateur fournisseur (pas de `Telemetry`, pas de client GPS) dans le module ; la mission interdit explicitement de le simuler | hors périmètre (assumé) |
| 24 | **Historique de trajet / détection de retard** | ⛔ **absent** | Aucune table d'historique de positions ; `position_recorded_at` ne conserve que le **dernier** point | hors périmètre (assumé) |
| 25 | **Position estimée produite par le trajet** | 🟡 non branché | L'enum `TravelVehiclePositionSource::ESTIMATED` existe et est validée en entrée, mais **aucune action ne calcule** d'estimation (`grep ESTIMATED` → seulement l'enum). Volontaire : présenter une estimation comme un GPS serait un mensonge | assumé |
| 26 | **Notifications de confirmation** | ✅ | Outbox `travel_outbox_events` + consommateurs notifications, `TravelNotificationTest`, `TravelNotificationConsumerTest` | — (existant) |
| 27 | **Historisation des opérations** | 🟡 | Outbox événementielle présente (14 types `travel.*.v1`) ; **pas** de journal d'audit sur les consultations/réimpressions de billet | voir #20 |

## 3. Chevauchement avec les travaux d'autres agents

Conformément à la règle A de la mission, l'état distant a été inspecté **avant**
toute modification :

- **Branches distantes hors mission :** `bc/bc-36-trade-os`,
  `bos/device-enrollment-screen`, et 9 branches `dependabot/*`. **Aucune ne
  touche `api/app/Modules/TravelAgency`** → pas de fichier partagé en conflit.
- **Branches `dependabot/composer/api/*`** : touchent `api/composer.json` /
  `composer.lock` — **fichiers partagés** avec ce lot (qui ajoute
  `bacon/bacon-qr-code`). Aucune fusion dependabot n'est intervenue sur ces
  fichiers depuis : chevauchement documenté, non conflictuel à ce jour.
- **PR #8541** (ce lot) : ouverte, `Closes #8538 #8539 #8540`.
- **Aucun reset, aucun écrasement** de travail d'autrui : la branche a été
  mise à jour par **fusion** de `main` (jamais par rebase forcé ni force-push).

## 4. Dette préexistante sur `main` (à ne pas imputer à ce lot)

Ces échecs sont **présents sur `main` avant** tout changement de ce lot :

| Workflow | État sur `main` |
|---|---|
| `Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)` | `failure` |
| `Backend Security (Composer Audit)` | `failure` |
| `Golden tests paie DZ` | `failure` |
| `validate-and-sync` | `failure` |
| `Rapport d'état des checks` | `failure` |
| `Notify Result` | `failure` |

Chacun est signalé comme **préexistant**. Aucun contournement de protection de
branche n'a été utilisé pour obtenir artificiellement une CI verte.

## 5. Exigences des checks requis (`main`)

Protection de `main` = 4 checks requis :

| Check requis | Workflow |
|---|---|
| `PHPStan — Strict (Core/Modules/Shared, level 8)` | `architecture-check.yml:291` |
| `Module Structure Validator` | `architecture-check.yml:342` |
| `Frontend — ESLint + TypeScript` | `architecture-check.yml:609` |
| `actionlint (+ shellcheck)` | `actionlint.yml:50` |

## 6. Lacunes retenues et priorisées

| Priorité | Lacune | Preuve | Statut |
|---|---|---|---|
| **P1** | Réimpression d'un billet **avec trace** | #20 — `grep` vide | à implémenter |
| **P2** | Vue consolidée **par agence** (ventilation des ventes/réservations par `TravelOffice`) | #22 — colonne et service absents | à implémenter |
| **P3** | Scan caméra du QR côté app agent | #19 — dépendance absente | appareil/OS requis |
| **P4** | Impression thermique ESC-POS | #21 — aucun pilote | appareil/OS requis |
| **P5** | Suivi GPS temps réel / historique / retards | #23/#24 | fournisseur externe requis |
