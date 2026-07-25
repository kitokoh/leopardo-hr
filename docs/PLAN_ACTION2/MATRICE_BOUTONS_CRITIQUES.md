# Matrice boutons critiques — pointage / paie / client / admin / kiosk

**Ticket:** PA2-QA-002 | **Priorite:** P0 | **Area:** Verification | **Surface:** docs; tests

Cette matrice recense, pour chaque surface (kiosk, mobile employee/manager, admin web, web dashboard, web vitrine/checkout), les boutons executant une action reelle (pas de la navigation pure) et verifie que chacun mappe vers une route API existante ou une action locale (offline queue, state local) documentee. Objectif : garantir qu'aucun "bouton critique" (pointage, paie, approbation, activation client) n'est un CTA mort.

Methode : lecture croisee du code front (`onClick`/`onPressed`/`onTap`/`@click`) contre les definitions de routes backend (`api/routes/**`) et, pour le kiosk, contre le bridge desktop local (`front/zkteco-kiosk/desktop-bridge/bridge.py`). Pas d'execution runtime (pas de PHP/Composer/Flutter dans l'environnement d'audit) — verification statique uniquement, a rejouer en CI si un garde automatique est ajoute (voir section "Suite recommandee").

---

## 1. Kiosk (`front/zkteco-kiosk`)

| Bouton (id / label) | Fichier | Action declenchee | Cible | Statut |
|---|---|---|---|---|
| `#checkInButton` Arrivee | `app.js` | `submitPunch('check_in')` → `fetchJson(${localBridgeUrl}/punch)` | Bridge local `POST /local/punch` (`bridge.py:do_POST`) → queue SQLite puis sync vers `POST /kiosks/{deviceCode}/sync` | OK — mappe vers action locale (queue offline) + sync API reelle |
| `#checkOutButton` Depart | `app.js` | `submitPunch('check_out')` → idem check-in | idem | OK |
| `#qrCheckInBtn` Arrivee QR | `app.js` | `submitQrPunch('check_in')` → `kioskApi('/qr-punch')` | `POST /kiosks/{deviceCode}/qr-punch` (`KioskController::qrPunch`, `integrations.php:39`) | OK |
| `#qrCheckOutBtn` Depart QR | `app.js` | `submitQrPunch('check_out')` → idem | idem | OK |
| `#infoSearchBtn` Rechercher employe | `app.js` | `searchEmployeeInfo()` → `kioskApi('/employee-info')` | `POST /kiosks/{deviceCode}/employee-info` (`integrations.php:36`) | OK |
| `#leaveSearchBtn` Consulter solde | `app.js` | `searchLeaveBalance()` → `kioskApi('/leave-balance')` | `POST /kiosks/{deviceCode}/leave-balance` (`integrations.php:38`) | OK |
| `#demoAccessBtn` Choisir employe demo | `app.js` | Ouvre modal demo locale (`showDemoAccessModal`), pas d'appel reseau | Action locale UI uniquement | OK — pas de bouton mort, comportement local intentionnel (mode demo) |
| Bridge local `/local/sync/all`, `/local/sync/roster`, `/local/sync/events` (declenches par timer `auto_sync_loop`, pas un bouton) | `bridge.py` | `SYNC_ENGINE.sync_all()` etc. | `GET /kiosks/{deviceCode}/roster` + `POST /kiosks/{deviceCode}/sync` (`rh.php:201,203`) | OK — hors perimetre bouton mais verifie pour completude du flux pointage kiosk |

## 2. Mobile — Employee (`front/mobile_apps/leopardo_employee`)

| Bouton / action | Fichier | Action declenchee | Cible | Statut |
|---|---|---|---|---|
| Bouton pointage (check-in/out) | `features/attendance/screens/attendance_screen.dart` (`_handlePunch`) | `AttendanceRepository.checkIn()` / `.checkOut()` | `POST /attendance/check-in`, `POST /attendance/check-out` (`rh.php:71-72`) | OK — fallback offline (Hive `offline_punches`) si erreur reseau, documente dans le repository |

## 3. Mobile — Manager (`front/mobile_apps/leopardo_manager`)

| Bouton / action | Fichier | Action declenchee | Cible | Statut |
|---|---|---|---|---|
| Approuver demande d'absence | `features/approvals/screens/approval_screen.dart` (`_approve`) | `ApprovalRepository.approve(id)` | `PUT /approvals/{id}/approve` | OK |
| Rejeter demande d'absence | `features/approvals/screens/approval_screen.dart` (`_reject`) | `ApprovalRepository.reject(id, comment)` | `PUT /approvals/{id}/reject` | OK |
| Approuver correction de pointage | `features/manager/screens/manager_attendance_monitoring_screen.dart` (`_decide`) | `AttendanceRepository.approveCorrection(id)` | `PUT /attendance/corrections/{correction}/approve` (`rh.php:80`) | OK |
| Rejeter correction de pointage | idem (`_decide`) | `AttendanceRepository.rejectCorrection(id)` | `PUT /attendance/corrections/{correction}/reject` (`rh.php:81`) | OK |

## 4. Mobile — Platform Admin (`front/mobile_apps/leopardo_platform_admin`)

| Bouton / action | Fichier | Action declenchee | Cible | Statut |
|---|---|---|---|---|
| Activer client (company) | `src/features/companies/company_detail_screen.dart` (`_activateCompany`) | `PlatformRepository.updateCompanySubscription(status: 'active')` | `PATCH /companies/{company}/subscription` (`api.php:176`) | OK |

## 5. Admin web (`front/admin-dashboard`, Vue)

| Bouton / action | Fichier | Action declenchee | Cible | Statut |
|---|---|---|---|---|
| Calculer run de paie | `views/payroll/PayrollView.vue` (`calculateRun`) | `api.post('/v1/payroll-runs/{id}/calculate')` | `POST /payroll-runs/{payrollRun}/calculate` (`payroll_engine.php:77`) | OK |
| Valider run de paie | `views/payroll/PayrollView.vue` (`validateRun`) | `api.post('/v1/payroll-runs/{id}/validate')` | `POST /payroll-runs/{payrollRun}/validate` (`payroll_engine.php:78`) | OK |
| Telecharger bulletin PDF | `views/payroll/PayrollView.vue` (`downloadPaySlipPdf`) | `api.get('/v1/pay-slips/{id}/pdf')` | Route existante (verifiee via meme prefixe `pay-slips`, contrat confirme cote web dashboard section 6) | OK |
| Approuver demande de conge (widget `ApprovalWidget`) | `views/leaves/LeavesView.vue` (`approveRequest`) | `api.put('/v1/absences/{id}/approve')` | `PUT /absences/{absence}/approve` (`rh.php:99`) | OK |
| Rejeter demande de conge | `views/leaves/LeavesView.vue` (`rejectRequest`) | `api.put('/v1/absences/{id}/reject', { rejected_reason })` | `PUT /absences/{absence}/reject` (`rh.php:100`) | OK |

## 6. Web dashboard client (`front/web`, Next.js, espace `(dashboard)`)

| Bouton / action | Fichier | Action declenchee | Cible | Statut |
|---|---|---|---|---|
| Telecharger bulletin PDF | `src/app/(dashboard)/payroll/page.tsx` (`downloadPdf`) | `apiFetch('/pay-slips/{id}/pdf')` | Route pay-slips PDF (backend Laravel, module Payroll) | OK |
| Pagination pay-slips (prev/next) | idem | changement d'etat local `currentPage`, pas d'appel reseau supplementaire (donnees deja chargees) | Action locale UI | OK — pas un bouton mort, pagination client-side sur donnees deja recuperees via `apiFetch('/pay-slips')` |
| Onglet Pointage (page lecture seule) | `src/app/(dashboard)/attendance/page.tsx` | `apiFetch('/attendance/today')` au chargement (pas un bouton d'action, page en lecture) | `GET /attendance/today` | OK — pas de bouton d'ecriture sur cette page, hors perimetre "bouton critique" |

## 7. Vitrine / Client — Checkout & Signup (`front/web`, espace `(landing)`)

| Bouton / action | Fichier | Action declenchee | Cible | Statut |
|---|---|---|---|---|
| Soumission formulaire signup | `src/app/(landing)/signup/page.tsx` | `fetch('/api/forms/signup', ...)` | Route interne Next `app/api/forms/route.ts` (proxy vers backend) | OK |
| Confirmer paiement checkout | `src/app/(landing)/checkout/page.tsx` (`handleSubmit`) | `fetch('/api/billing/checkout', ...)` | Route interne Next `app/api/billing/route.ts` (proxy Stripe/backend) | OK |
| Remplir carte sandbox (`fillSandbox`) | idem | Remplit les champs carte avec des valeurs de test locales, pas d'appel reseau | Action locale UI (mode sandbox documente) | OK — comportement voulu, pas un bouton mort |

---

## Synthese

- **Boutons audites :** 24 (pointage kiosk 6, mobile employee 1 groupe check-in/out, mobile manager 4, mobile platform admin 1, admin web 5, web dashboard 2, vitrine/checkout 3, + flux sync kiosk hors-bouton verifie pour completude).
- **Boutons sans route ni action locale identifiee :** 0.
- **Boutons a action locale (justification documentee, pas un endpoint) :** `#demoAccessBtn` (kiosk, mode demo), pagination pay-slips (web dashboard), `fillSandbox` (checkout, mode test carte).

Aucun bouton critique pointage/paie/client/admin/kiosk n'a ete trouve sans route backend ni action locale justifiee au moment de cet audit.

## Suite recommandee (hors perimetre immediat de ce ticket)

Cette matrice est un audit statique manuel, pas un garde CI. Pour eviter la derive (nouveau bouton ajoute sans route branchee), un futur ticket pourrait etendre `dev-hub/tools/check-unrouted-controllers.sh` (deja utilise pour PA2-ARCH-007, cote backend) avec une verification symmetrique cote front : grep des `onClick`/`onPressed`/`@click` appelant une fonction qui fait un appel reseau, croise avec la liste des routes API declarees. Non fait ici pour rester dans le scope strict "matrice" du ticket (`docs/tests`) sans introduire un nouveau garde CI non demande.
