# 📊 Rapport de bilan — 60 jours de consolidation (issue #5159)

**Version** : 1.0 · **Date** : 2026-08-20 · **Auteur** : Agent PM
**Périmètre** : période J31→J60 du plan post-audit (ROADMAP_60J) + entrée du plan 60 jours actuel (J1, 19/08/2026 → gate J60, 17/10/2026).
**Sources** : `CHANGELOG.md`, API GitHub (issues/PRs), `docs/qa/INVENTAIRE_CI_2026-08-19.md`, `docs/GOUVERNANCE/FREEZE_SCOPE_60J.md`.

---

## 1. Synthèse exécutive

- **Version** : `v4.24.0` (release 2026-08-11) — dernière release taguée.
- **Activité issue/PR (60 derniers jours, vérifié API GitHub)** :
  - ~1 962 issues créées, **22 issues ouvertes** (2 P0, 7 P1, 7 P2, 0 P3) au 2026-08-20 ;
  - ~446 issues closes sur les 5 derniers jours (campagne de remédiation intense) ;
  - 24 branches fusionnées en une seule vague le 2026-08-20 (docs, fixes P0/P1, dependabot ×11, kiosk, i18n).
- **Ce qui a changé la donne** : passage à une exécution **spec-driven** (Spec Kit), gouvernance durcie (freeze scope #5147, budget agents #5148, protocole anti-doublon #2400), campagne « 5 jours verts » CI (#5145).
- **Verdict** : la consolidation fonctionnelle est largement livrée ; les **2 P0 ouverts** (Google OAuth prod #5170/#5171) sont des blocages d'acquisition, **pas des blocages produit** — le fond du produit (paie DZ validée, kiosque, i18n) est en place.

## 2. Livraisons majeures (60 jours)

| Domaine | Livraison | Référence |
|---|---|---|
| **Paie DZ (wedge)** | Moteur paie algérien : IRG (barème LF, abattement 40 %), CNAS 9 %/26 %, SMIG 20 000 DZD — **validé expert-comptable DZ (2026-08-08)** | `docs/payroll/DZ_COMPLIANCE.md` |
| **Paie SN** | Validation règles Sénégal (SMIG 64 305, IPRES, IR 7 tranches, TRIMF) — fiche signée | CHANGELOG #1912 |
| **Golden tests** | 158 golden tests calculés à la main (24 fichiers, toutes zones) ; 31 tests DZ → objectif ≥ 40 (#5149) | `api/tests/Feature/Payroll/Golden/` |
| **Kiosque ZKTeco** | Punch-methods configurables par device, enforcement à la sync, badge employé (#5120-#5124, épic #5119) | `docs/kiosk/punch-methods-config.md` |
| **Onboarding/trial** | Fix `ProvisionGuidedTrial` P0 (#5161) ; fix envoi OTP (#5162 en cours) ; sweep des provisionings bloqués (#4948) | CHANGELOG |
| **Mail** | Bascule SMTP → **API HTTP Mailgun** (egress Render bloque SMTP, #5139) ; timeout borné 15 s | CHANGELOG |
| **Infra/deploy** | Déploiements path-aware (Vercel/Render ne brûlent plus le quota), workers de queue documentés (#5172) | `render.yaml`, CHANGELOG |
| **i18n** | Lot i18n enterprise (mobile + web), préservation des alias legacy (#4978) | CHANGELOG |
| **CI** | Inventaire 43 workflows (#5145), gardes gouvernance (Closes #, anti-ghost-close), coverage gate | `docs/qa/INVENTAIRE_CI_2026-08-19.md` |

## 3. État de la roadmap 60 jours (post-audit, 15 items)

| # | Item roadmap | Statut au bilan | Preuve |
|---|---|---|---|
| 1 | Solde employé temps réel (`/me/balance`) | ⚠️ **Non vérifié** (route absente des routes actuelles) | — |
| 2 | PDF bulletins de paie async | ✅ Livré (queue `pdf`, GeneratePaySlipPdf, liens différés) | `PaySlipController`, `PayrollClosingService` |
| 3 | Paiements en masse | ✅ Livré (`ProcessBulkPaymentJob`) | `BulkPaymentController` |
| 4 | GPS geofence douce | ✅ Livré (geofence configurable, onboarding step `activate_geofence`) | `AttendanceService` |
| 5 | Onboarding QR Code | ✅ Livré | `OnboardingQrService` |
| 6 | Notifications push fiabilisées | ✅ Livré (retry, files) | `CommunicationService` |
| 7 | Tests de charge k6 | ✅ Livré (baseline) | `dev-hub/k6/stress-test.js`, `k6-load-smoke.yml` |
| 8 | Audit trail complet | ✅ Livré (`AuditLog`) | `AuditLogController` |
| 9 | Branding tenant premium | ❌ **Non livré** (aucune trace de branding par tenant) | — |
| 10 | Dashboard manager | ⚠️ Partiel (dashboard de présence côté SmartAttendance) | `GeoSessionController` |
| 11 | Swagger/OpenAPI complet | ✅ Livré (`dev-hub/openapi/v1.yaml`, `openapi-ci.yml`) | `dev-hub/openapi/` |
| 12 | Premier client pilote intégré | 🔄 **En cours** — objectif 3 pilotes DZ (issues #5151-#5156) | `docs/pilotes/` |
| 13 | Multilingue FR+AR validé | ✅ Livré (4 catalogues fr/en/tr/ar) | `api/lang/` |
| 14 | Monitoring SLA Redis | ⚠️ Partiel (runbooks ops présents) | `RUNBOOK_ALERTING.md` |
| 15 | Documentation déploiement à jour | ✅ Livré (RUNBOOK_DEPLOY, RENDER_SETUP, RUNBOOK_RENDER_WORKERS #5172) | `docs/GESTION_PROJET/` |

**Lecture** : 10/15 livrés, 2 partiels, 1 en cours (pilotes), 1 non vérifié (#1), 1 non livré (#9 branding — **hors freeze scope** #5147).

## 4. Critères de sortie J60 (post-audit) — état

| Critère | État |
|---|---|
| 1 client pilote réel (10+ employés actifs) | 🔄 Objectif 3 pilotes DZ — signature en cours (kit #5154) |
| PDF bulletin généré et téléchargeable < 60 s | ✅ (worker `pdf`, vérif à refaire en prod) |
| Solde employé visible avec cycle de paie | ⚠️ À confirmer (item #1 non vérifié) |
| k6 : P99 < 500 ms / 100 utilisateurs | ⚠️ Baseline livrée, résultats à publier |
| Audit trail actions paie/avances | ✅ |
| Swagger accessible sur l'instance Render | ⚠️ Fichier livré, déploiement public à confirmer |
| Push notifications Android + iOS | ⚠️ FCM configuré, TestFlight à confirmer |

## 5. Points de décision ouverts (pour le gate)

1. **#5171 (P0)** — Création de compte Google : acter invitation-first **(a)** ou activer le self-service sécurisé **(b)**. *Recommandation : (a) invitation-first documenté + UX claire, (b) à l'étude après le gate J16.*
2. **#5170 (P0)** — Renseigner `GOOGLE_*` sur Render (action ops, runbook livré) — prérequis de tout l'onboarding Google.
3. **#3452 (P1)** — Restaurer le DNS `leopardo-rh.com` (domaine vitrine NXDOMAIN — acquisition à 0).
4. **#5150/#5149 (paie DZ)** — Poursuivre clôture DZ + benchmark 10k + golden ≥ 40 (wedge du plan).
5. **#5146 (E2E funnel)** — Spec posée, implémentation Playwright à lancer.

## 6. Recommandations pour le gate J60 (17/10/2026)

1. **Fermer les 2 P0 d'onboarding d'abord** (#5170 ops 30 min ; #5171 décision produit) — c'est le funnel d'acquisition.
2. **Campagne « 5 jours verts »** : traiter les 15 workflows rouges listés dans `docs/qa/INVENTAIRE_CI_2026-08-19.md`.
3. **Paie DZ** : golden ≥ 40 (#5149), clôture de paie 2 étapes + benchmark 10 000 employés (#5150).
4. **Pilotes DZ** : signature des 3 fiches (#5154), onboarding chronométré < 30 min (#5151), carnets (#5152), SLA (#5155), suivi usage (#5156).
5. **Revue du freeze scope** au gate : sortir le branding tenant (#9) et réévaluer la dette i18n (#2755) et la déduplication Flutter (#2601).

---

*Rapport généré au 2026-08-20 depuis les sources du repo. Les statuts marqués « à confirmer » exigent une vérification live avant le gate.*
