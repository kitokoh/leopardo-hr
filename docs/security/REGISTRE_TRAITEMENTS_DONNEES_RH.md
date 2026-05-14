# Registre interne des traitements de donnees RH

Version 1.0 | 2026-05-14

## 1. Objet

Ce registre documente les traitements de donnees personnelles portes par Leopardo RH afin de soutenir les obligations de conformite RGPD, loi 18-07 DZ et appels d'offres enterprise.

Il complete :

- les pages publiques `/privacy` et `/terms` de la vitrine ;
- les endpoints `GET /api/v1/privacy/export`, `POST /api/v1/privacy/deletion-request` et `PATCH /api/v1/privacy/biometric-consent` ;
- la journalisation des acces RH sensibles via `audit_logs`.

## 2. Perimetre et roles

| Role | Responsabilite |
|---|---|
| Client employeur | Responsable principal du traitement pour ses salaries et candidats |
| Leopardo RH | Sous-traitant SaaS pour l'hebergement, la maintenance, le support et la securite de la plateforme |
| Super administrateurs Leopardo RH | Acces limite aux operations plateforme, support, provisioning, securite et conformite |
| Utilisateurs client | Acces selon roles : admin, RH, manager, employe, kiosque |
| Integrateurs autorises | Acces API limite par contrat, permissions, scopes et journaux |

## 3. Principes obligatoires

- Minimisation : ne collecter que les donnees necessaires aux workflows RH actives.
- Isolation : toute donnee metier client doit rester isolee par tenant.
- Tracabilite : tout acces sensible aux fiches RH, exports privacy et actions admin doit rester auditable.
- Droit d'acces : l'utilisateur doit pouvoir obtenir un export de ses donnees personnelles disponibles.
- Consentement biometrique : tout usage biometrique doit etre explicite, reversible et documente.
- Conservation limitee : chaque client doit definir ses durees de conservation selon son pays et ses obligations sociales, fiscales et RH.

## 4. Registre des traitements

| Traitement | Finalites | Donnees traitees | Personnes concernees | Base legale type | Conservation indicative | Mesures de protection |
|---|---|---|---|---|---|---|
| Authentification et sessions | Connexion, controle d'acces, securite compte | Identite, email, mot de passe hashe, roles, permissions, tokens, logs techniques | Utilisateurs client, super admins | Contrat, interet legitime securite | Duree du compte + logs 12 a 24 mois | Hash mots de passe, RBAC, rate limiting, audit logs |
| Gestion employes | Dossier RH, organisation, contacts, poste, statut | Identite, contact, matricule, poste, departement, manager, statut, pieces RH | Employes | Contrat de travail, obligation legale, interet legitime RH | Duree relation de travail + obligations legales locales | Isolation tenant, policies, audit logs, controles manager/self-service |
| Pointage et presence | Suivi temps, retards, anomalies, rapports mensuels | Horaires, check-in/out, device, geolocalisation si activee, anomalies | Employes terrain, managers | Obligation contractuelle, interet legitime, loi locale | Selon droit social local, souvent 3 a 5 ans | Permissions, geofence configurable, journaux, exports controles |
| Paie et bulletins | Calcul paie, validation, bulletins, exports bancaires | Salaire, variables, absences, heures, IBAN, exports banque, bulletins PDF | Employes, RH, finance | Obligation legale, contrat | Selon obligations fiscales/sociales locales | RBAC strict, logs, preparation chiffrement donnees sensibles |
| Absences et conges | Demandes, approbations, soldes, rapports | Type absence, dates, justification, approbations, solde | Employes, managers, RH | Contrat, obligation legale | Selon politique RH et droit local | Workflows d'approbation, isolation tenant, audit |
| Documents RH | Stockage et consultation de documents | Contrats, attestations, bulletins, justificatifs, fichiers RH | Employes, RH | Contrat, obligation legale | Selon type document et pays | Acces par role, stockage controle, future retention policy |
| Recrutement | Pipeline candidats, entretiens, decision | CV, contact, notes, poste, evaluation, statut candidat | Candidats, recruteurs | Consentement, mesures precontractuelles, interet legitime | 6 a 24 mois selon pays et consentement | Isolation tenant, limitation acces, purge planifiee a definir |
| Formation et evaluations | Suivi competence, sessions, resultats | Inscriptions, progression, evaluations, commentaires | Employes, managers, RH | Contrat, interet legitime RH | Duree relation + politique interne | RBAC, separation par tenant, journaux |
| Notifications et emails | Informer utilisateurs, rappels, approbations | Email, telephone si active, preferences, contenu notification | Utilisateurs | Contrat, interet legitime, consentement selon canal | Logs courts, contenu selon obligation | Templates centralises, preferences, limitation contenu sensible |
| Support et administration plateforme | Support client, provisioning, supervision, incidents | Donnees compte client, tickets, logs, metadata technique | Clients, admins, support | Contrat, interet legitime securite/support | 12 a 36 mois selon criticite | Super-admin RBAC, audit, principe moindre privilege |
| IA conversationnelle future | Assistance RH, recherche, recommandations, actions controlees | Prompts, contexte metier autorise, resultats, actions demandees | Utilisateurs autorises | Contrat, interet legitime, validation humaine selon action | A definir par politique IA et minimisation | Tool calling permissionne, audit, masquage donnees sensibles, validation humaine |
| Kiosque et biometrie | Pointage terrain, identification locale si activee | Identifiant employe, device, event pointage, consentement biometrique, reference template | Employes terrain | Consentement explicite ou cadre legal local | Tant que consentement actif + obligations de preuve | Consentement reversible, suppression references, logs |

## 5. Droits des personnes

| Droit | Mecanisme actuel | Point de controle |
|---|---|---|
| Acces | `GET /api/v1/privacy/export` | Export personnel de l'utilisateur authentifie |
| Rectification | Workflow support/RH client a formaliser | Correction par RH/admin tenant selon role |
| Suppression | `POST /api/v1/privacy/deletion-request` | Demande tracee, non destructive tant que revue humaine non faite |
| Retrait consentement biometrique | `PATCH /api/v1/privacy/biometric-consent` | Retrait consentement + nettoyage references templates |
| Limitation/opposition | Workflow support/RH client a formaliser | Ticket interne et decision responsable de traitement |

## 6. Mesures techniques et organisationnelles

- Multi-tenant PostgreSQL par schema et controles applicatifs par `company_id`.
- RBAC et policies Laravel sur les modules sensibles.
- Audit trail pour acces fiches employes et exports privacy.
- Tests d'isolation tenant sur modules critiques.
- CI/CD avec lint/build/tests, governance gate, secret scan et dependency review.
- Backups et restore drills documentes dans `docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`.
- Journalisation des incidents et runbooks dans `docs/GESTION_PROJET/`.
- Preparation chiffrement applicatif pour IBAN, comptes bancaires et donnees salariales sensibles.

## 7. Sous-traitants et transferts

Les sous-traitants exacts dependent de l'environnement deploye par le client ou l'operateur Leopardo RH.

| Categorie | Exemples actuels | Donnees exposees | Controle attendu |
|---|---|---|---|
| Hebergement API | Render / fournisseur equivalent | Donnees applicatives et logs techniques | Region, chiffrement, acces restreint |
| Base de donnees | PostgreSQL manage ou self-hosted | Donnees RH tenant | Backups, chiffrement disque, acces restreint |
| Frontend public/admin | Vercel, Cloudflare Pages ou equivalent | Assets, telemetry frontend, variables publiques | Pas de secrets cote client |
| Email / notifications | Fournisseur SMTP ou push futur | Email, message, metadata notification | Minimisation contenu, DPA fournisseur |
| Observabilite | Sentry / logs | Erreurs, traces, metadata technique | Scrubbing PII, retention limitee |

## 8. Revue periodique

Ce registre doit etre revu :

- a chaque nouveau module RH collectant une nouvelle categorie de donnees ;
- a chaque integration externe ;
- a chaque activation d'un traitement IA ou biometrique ;
- au minimum une fois par trimestre avant les comites produit/securite.

## 9. Gaps suivis

| Gap | Statut | Priorite |
|---|---|---|
| Chiffrement applicatif IBAN / salaire | A implementer | Haute |
| Politique de retention automatique par type de document | A concevoir | Haute |
| Workflow rectification / limitation dans l'admin | A concevoir | Moyenne |
| DPA fournisseur et cartographie regions | A formaliser | Haute |
| Politique IA avec validation humaine et audit action | A formaliser avant activation IA conversationnelle | Haute |
