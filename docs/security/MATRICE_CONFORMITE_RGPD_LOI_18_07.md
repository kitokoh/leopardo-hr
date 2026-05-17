# Matrice de conformite RGPD / Loi 18-07 DZ / Loi 09-08 MA

Version 1.0 | 2026-05-17

## 1. Objet

Cette matrice croise les exigences reglementaires applicables a une plateforme RH SaaS multi-tenant avec les mesures techniques et organisationnelles implementees dans Leopardo RH.

Reglementations couvertes :
- **RGPD** (Reglement UE 2016/679) — applicable aux clients europeens ou traitant des donnees de residents UE.
- **Loi 18-07 DZ** (Algerie, 10 juin 2018) — relative a la protection des personnes physiques dans le traitement des donnees a caractere personnel.
- **Loi 09-08 MA** (Maroc, 18 fevrier 2009) — relative a la protection des personnes physiques a l'egard du traitement des donnees a caractere personnel.

Documents connexes :
- `docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md` — registre des traitements
- `docs/dossierdeConception/07_securite_rbac/12_SECURITY_SPEC_COMPLETE.md` — specifications securite
- `docs/security/RBAC_ROUTE_MATRIX.md` — matrice RBAC
- `front/web/src/app/privacy/` et `front/web/src/app/terms/` — pages publiques

---

## 2. Matrice de conformite

### 2.1 Principes fondamentaux

| Exigence | RGPD | Loi 18-07 DZ | Loi 09-08 MA | Implementation Leopardo RH | Statut |
|---|---|---|---|---|---|
| Licite, loyaute, transparence | Art. 5(1)(a) | Art. 7 | Art. 3 | Pages `/privacy` et `/terms` publiques (FR/EN/TR/AR) ; consentement enregistre via `email_verified_at` ; politique affichee a la premiere connexion | CONFORME |
| Limitation des finalites | Art. 5(1)(b) | Art. 7 | Art. 3 | Donnees collectees uniquement pour workflows RH actives ; registre des traitements documente dans `REGISTRE_TRAITEMENTS_DONNEES_RH.md` | CONFORME |
| Minimisation des donnees | Art. 5(1)(c) | Art. 7 | Art. 3 | Formulaires RH ne collectent que les champs necessaires ; `FormRequest` Laravel avec validation stricte par action | CONFORME |
| Exactitude | Art. 5(1)(d) | Art. 7 | Art. 3 | Self-service `/me/*` pour mise a jour par l'employe ; workflow rectification via RH/admin | CONFORME |
| Limitation de conservation | Art. 5(1)(e) | Art. 12 | Art. 3 | Conservation indicative par traitement dans le registre ; commande `audit:purge --older-than=24months` ; politique retention documents a formaliser | PARTIEL |
| Integrite et confidentialite | Art. 5(1)(f) | Art. 38-40 | Art. 23 | Chiffrement AES-256-CBC (iban, bank_account, national_id) ; HTTPS TLS 1.2+ ; RBAC ; isolation multi-tenant PostgreSQL | CONFORME |
| Responsabilite (accountability) | Art. 5(2) | Art. 44 | Art. 52 | Registre des traitements ; audit logs ; cette matrice ; revue periodique trimestrielle | CONFORME |

### 2.2 Droits des personnes concernees

| Droit | RGPD | Loi 18-07 DZ | Loi 09-08 MA | Implementation Leopardo RH | Statut |
|---|---|---|---|---|---|
| Droit d'acces | Art. 15 | Art. 32 | Art. 7 | `GET /api/v1/privacy/export` — export personnel de l'utilisateur authentifie | CONFORME |
| Droit de rectification | Art. 16 | Art. 34 | Art. 8 | Self-service `/me/*` ; workflow support/RH pour champs restreints | CONFORME |
| Droit a l'effacement | Art. 17 | Art. 36 | Art. 9 | `POST /api/v1/privacy/deletion-request` — demande tracee, non destructive, revue humaine requise ; archivage (`status=archived`) + anonymisation | CONFORME |
| Droit a la limitation | Art. 18 | Art. 35 | Art. 9 | Workflow support/RH a formaliser ; statut `suspended` disponible | PARTIEL |
| Droit a la portabilite | Art. 20 | Art. 33 | — | `GET /api/v1/privacy/export` retourne bundle JSON des donnees personnelles | CONFORME |
| Droit d'opposition | Art. 21 | Art. 35 | Art. 9 | Ticket interne et decision responsable de traitement ; a formaliser dans l'admin | PARTIEL |
| Consentement biometrique | Art. 9 | Art. 18 | Art. 12 | `PATCH /api/v1/privacy/biometric-consent` — consentement explicite, reversible, suppression references templates | CONFORME |
| Information prealable | Art. 13-14 | Art. 30-31 | Art. 5 | Pages `/privacy` et `/terms` multilingues ; politique affichee a la premiere connexion | CONFORME |

### 2.3 Securite des traitements

| Exigence | RGPD | Loi 18-07 DZ | Loi 09-08 MA | Implementation Leopardo RH | Statut |
|---|---|---|---|---|---|
| Chiffrement donnees sensibles | Art. 32(1)(a) | Art. 38 | Art. 23 | `EncryptedCast` AES-256-CBC sur `iban`, `bank_account`, `national_id` via Eloquent ; `Hash::make()` bcrypt cout >= 12 pour mots de passe | CONFORME |
| Pseudonymisation | Art. 32(1)(a) | Art. 38 | — | Anonymisation a l'archivage employe ; `company_id` scope sans exposition croisee | PARTIEL |
| Resilience des systemes | Art. 32(1)(b) | Art. 39 | Art. 23 | Backups PostgreSQL quotidiens ; test restauration mensuel ; health endpoints `/api/health`, `/api/v1/health/live`, `/api/v1/health/ready` | CONFORME |
| Disponibilite et acces | Art. 32(1)(c) | Art. 39 | Art. 23 | Render auto-scaling ; Redis cache ; queue async ; monitoring Sentry + Slack alertes | CONFORME |
| Verification reguliere | Art. 32(1)(d) | Art. 40 | Art. 23 | CI/CD GitHub Actions (lint, tests, secret scan, dependency review, governance gates) ; audits periodiques documentes | CONFORME |
| Controle d'acces | Art. 32 | Art. 38 | Art. 23 | RBAC Laravel Policies par role (super_admin, owner, hr, manager, employee, finance) ; rate limiting par plan ; Sanctum tokens opaques revocables | CONFORME |
| Journalisation | Art. 30 | Art. 44 | Art. 52 | `AuditLogger` listener sur 8 domain events ; `audit_logs` avec acteur, tenant, cible, IP, user-agent ; retention 24 mois ; export CSV `GET /api/v1/audit-logs/export-csv` | CONFORME |

### 2.4 Isolation multi-tenant

| Exigence | RGPD | Loi 18-07 DZ | Loi 09-08 MA | Implementation Leopardo RH | Statut |
|---|---|---|---|---|---|
| Separation des donnees | Art. 32 | Art. 38 | Art. 23 | PostgreSQL schema par tenant + `company_id` applicatif ; `TenantMiddleware` sur toutes les routes authentifiees | CONFORME |
| Non-acces inter-tenant | Art. 32 | Art. 38 | Art. 23 | Policies Laravel verifient `company_id` ; tests d'isolation Feature en CI ; tokens Sanctum scopes par tenant | CONFORME |
| Sous-traitants identifies | Art. 28 | Art. 44 | Art. 20 | Registre sous-traitants dans `REGISTRE_TRAITEMENTS_DONNEES_RH.md` (Render, PostgreSQL, Vercel, SMTP, Sentry) | CONFORME |
| Transferts internationaux | Art. 44-49 | Art. 44-47 | Art. 43-44 | Region hebergement configurable par deploiement ; DPA fournisseur a formaliser | PARTIEL |

### 2.5 Notification des violations

| Exigence | RGPD | Loi 18-07 DZ | Loi 09-08 MA | Implementation Leopardo RH | Statut |
|---|---|---|---|---|---|
| Detection incidents | Art. 33 | Art. 41 | Art. 24 | Sentry APM + `SentryContextMiddleware` ; `SlackAlertNotification` webhook ; `monitor:slow-queries` schedule 15min | CONFORME |
| Notification autorite (72h) | Art. 33 | Art. 42 | Art. 24 | Procedure documentee dans `RUNBOOK_OPERATIONS.md` ; Slack alertes immediates ; processus interne a formaliser | PARTIEL |
| Notification personnes | Art. 34 | Art. 43 | Art. 24 | Templates notifications existants ; processus specifique violation a formaliser | PARTIEL |

### 2.6 Donnees biometriques

| Exigence | RGPD | Loi 18-07 DZ | Loi 09-08 MA | Implementation Leopardo RH | Statut |
|---|---|---|---|---|---|
| Consentement explicite | Art. 9(2)(a) | Art. 18 | Art. 12 | `PATCH /api/v1/privacy/biometric-consent` avec flag explicite | CONFORME |
| Non-stockage biometrique | Art. 9 | Art. 18 | Art. 12 | Leopardo RH ne stocke AUCUNE donnee biometrique ; le lecteur ZKTeco stocke les empreintes localement ; seuls `employee_id + timestamp + direction` sont transmis | CONFORME |
| Reversibilite consentement | Art. 7(3) | Art. 18 | Art. 12 | Retrait via `PATCH /api/v1/privacy/biometric-consent` + nettoyage references templates | CONFORME |

---

## 3. Specificites loi 18-07 Algerie

La loi 18-07 du 10 juin 2018 introduit des obligations specifiques pour le contexte algerien :

| Article | Obligation | Implementation | Statut |
|---|---|---|---|
| Art. 7 | Consentement prealable ou base legale | Consentement a l'inscription + base contrat travail pour traitements RH | CONFORME |
| Art. 12 | Duree de conservation proportionnee | Registre avec durees indicatives ; commande purge audit logs | PARTIEL |
| Art. 18 | Donnees biometriques — autorisation prealable | Consentement explicite via endpoint API ; pas de stockage biometrique | CONFORME |
| Art. 30-31 | Information des personnes concernees | Pages `/privacy` et `/terms` multilingues ; politique premieres connexion | CONFORME |
| Art. 32-36 | Droits d'acces, rectification, opposition, effacement | Endpoints privacy self-service implementes | CONFORME |
| Art. 38-40 | Mesures de securite techniques | Chiffrement, RBAC, isolation tenant, audit, TLS | CONFORME |
| Art. 41-43 | Notification violation a l'ANPDP | Procedure interne + Slack alertes ; formalisation processus ANPDP a completer | PARTIEL |
| Art. 44 | Registre des traitements | `REGISTRE_TRAITEMENTS_DONNEES_RH.md` maintenu | CONFORME |
| Art. 44-47 | Transferts internationaux — autorisation ANPDP | Region configurable ; DPA a formaliser selon deploiement | PARTIEL |

---

## 4. Specificites loi 09-08 Maroc

La loi 09-08 du 18 fevrier 2009 et ses decrets d'application introduisent des obligations specifiques pour le contexte marocain :

| Article | Obligation | Implementation | Statut |
|---|---|---|---|
| Art. 3 | Principes de loyaute, finalite, proportionnalite | Registre des traitements ; minimisation des champs collectes | CONFORME |
| Art. 5 | Information prealable | Pages legales multilingues ; politique premieres connexion | CONFORME |
| Art. 7-9 | Droits d'acces, rectification, opposition | Endpoints privacy self-service implementes | CONFORME |
| Art. 12 | Donnees sensibles — consentement explicite | Consentement biometrique explicite via API | CONFORME |
| Art. 20 | Sous-traitance — contrat ecrit | Registre sous-traitants ; DPA a formaliser | PARTIEL |
| Art. 23 | Mesures de securite | Chiffrement, RBAC, isolation, audit, TLS | CONFORME |
| Art. 24 | Notification violation a la CNDP | Procedure interne ; formalisation processus CNDP a completer | PARTIEL |
| Art. 43-44 | Transferts internationaux — autorisation CNDP | Region configurable ; DPA a formaliser selon deploiement | PARTIEL |
| Art. 52 | Declaration a la CNDP | Responsabilite du client employeur ; documentation fournie pour faciliter la declaration | CONFORME |

---

## 5. Plan de remediation des gaps

| # | Gap identifie | Statut actuel | Priorite | Action requise | Responsable |
|---|---|---|---|---|---|
| G1 | Politique de retention automatique par type de document | Non implemente | HAUTE | Implementer `retention_policy` configurable par tenant avec purge automatique | Equipe backend |
| G2 | DPA fournisseur et cartographie regions | Non formalise | HAUTE | Rediger DPA template ; cartographier regions Render/Vercel/SMTP par deploiement | Equipe juridique + ops |
| G3 | Processus notification violation ANPDP/CNDP | Partiellement documente | HAUTE | Formaliser procedure complete avec delais, templates, contacts autorites | Equipe juridique + DPO |
| G4 | Workflow limitation/opposition dans l'admin | Non implemente | MOYENNE | Ajouter ecran admin pour gerer demandes limitation/opposition | Equipe frontend |
| G5 | Pseudonymisation etendue | Partiel (archivage uniquement) | MOYENNE | Etendre pseudonymisation aux exports et environnements de test | Equipe backend |
| G6 | Politique IA avec validation humaine | Documentee, non formalisee | HAUTE | Finaliser politique IA avant activation IA conversationnelle en production | Equipe produit + juridique |
| G7 | Chiffrement etendu au-dela des 3 champs Employee | Partiel | MOYENNE | Evaluer extension `EncryptedCast` a d'autres tables sensibles (pay_slips, contracts) | Equipe backend + securite |

---

## 6. References code

| Composant | Fichier(s) | Description |
|---|---|---|
| Chiffrement Employee | `api/app/Models/Tenant/Employee.php` | Casts `encrypted` sur `iban`, `bank_account`, `national_id` |
| Privacy export | `api/app/Http/Controllers/Api/V1/PrivacyController.php` | `GET /privacy/export` |
| Deletion request | `api/app/Http/Controllers/Api/V1/PrivacyController.php` | `POST /privacy/deletion-request` |
| Biometric consent | `api/app/Http/Controllers/Api/V1/PrivacyController.php` | `PATCH /privacy/biometric-consent` |
| Audit logger | `api/app/Listeners/AuditLogger.php` | Ecoute 8 domain events |
| Audit export CSV | `api/routes/modules/hr_extended.php` | `GET /audit-logs/export-csv` |
| RBAC policies | `api/app/Policies/` | EmployeePolicy, AttendancePolicy, AbsencePolicy, PayrollPolicy |
| Tenant middleware | `api/app/Http/Middleware/TenantMiddleware.php` | Isolation `company_id` |
| Sentry context | `api/app/Http/Middleware/SentryContextMiddleware.php` | Enrichissement tenant/user/role |
| Slack alertes | `api/app/Notifications/SlackAlertNotification.php` | Webhook monitoring |
| Slow queries | `api/app/Console/Commands/MonitorSlowQueries.php` | Detection pg_stat_statements |
| Rate limiting | `api/config/security.php` | Limiters `auth-sensitive`, `privacy-sensitive`, `api-plan` |
| Pages legales | `front/web/src/app/privacy/`, `front/web/src/app/terms/` | FR/EN/TR/AR |

---

## 7. Revue et gouvernance

- Cette matrice doit etre revue **trimestriellement** ou a chaque changement reglementaire significatif.
- Toute nouvelle fonctionnalite collectant des donnees personnelles doit mettre a jour cette matrice et le registre des traitements.
- Le DPO ou responsable securite valide les changements de statut des gaps.
- Version historique conservee dans le controle de version Git.
