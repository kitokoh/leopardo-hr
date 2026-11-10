#!/usr/bin/env python3
"""Génère le portail de recette multi-pages de leopardo-hr.

Sortie (par défaut dans le dossier de ce script) :
  index.html                     — accueil du portail (navigation par profil / module / type)
  profils/index.html             — liste des profils
  profils/<slug>.html            — page par profil utilisateur
  modules/index.html             — liste des modules
  modules/<slug>.html            — page par module / fonctionnalité
  cas/<uc-id>.html               — fiche de résultats par cas d'usage
  recap.html                     — tableau récapitulatif (moyenne, total, bloquants)
  assets/portal.css              — feuille de style
  assets/portal.js               — recherche/filtre + sauvegarde locale des évaluations
  tests-ia-leopardo-hr.md        — cahier de recette Markdown (généré depuis les mêmes données)
  prompt-agent-testeur.md        — prompt de l'agent testeur IA

Usage :
  python3 build_portal.py [--out DIR]
"""
from __future__ import annotations

import argparse
import html
import json
import shutil
from pathlib import Path

# ---------------------------------------------------------------------------
# TAXONOMIE
# ---------------------------------------------------------------------------

PROFILES = [
    dict(slug="employe", name="Employé", icon="👤", color="#2563eb",
         desc="Collaborateur de base : self-service, pointage, congés, bulletins de paie.",
         personas=["Karim Aouad — karim.aouad@example.com (TechCorp DZ)",
                   "Youssef Bennani — pharmaplus (MA)",
                   "Aziz Khelifi — digitalflow (TN)"],
         surfaces=["App mobile Employee", "Espace client web", "Kiosque biométrique"]),
    dict(slug="manager-principal", name="Manager Principal", icon="🧭", color="#0d9488",
         desc="Pilote l'entreprise : tableau de bord, équipes, CRM, validations.",
         personas=["Ahmed Benali — ahmed.benali@example.com (DZ)",
                   "Amina Tahiri — pharmaplus (MA)",
                   "Sofiane Mrad — digitalflow (TN)"],
         surfaces=["Espace client web", "App mobile Manager"]),
    dict(slug="manager-rh", name="Manager RH", icon="🗂️", color="#7c3aed",
         desc="Gère le référentiel employés, les absences, les rôles et le recrutement.",
         personas=["Fatima Meziane — fatima.meziane@example.com (DZ)",
                   "Sara Mansouri — pharmaplus (MA)",
                   "Olfa Trabelsi — digitalflow (TN)"],
         surfaces=["Espace client web", "App mobile Manager/HR"]),
    dict(slug="manager-comptable", name="Manager Comptable", icon="🧮", color="#b45309",
         desc="Prépare la paie, consulte les structures salariales et simule les calculs.",
         personas=["Lina Haddad — lina.haddad@example.com (DZ)",
                   "Rachid Benjelloun — pharmaplus (MA)"],
         surfaces=["Espace client web", "App mobile Manager"]),
    dict(slug="superviseur-kiosque", name="Superviseur / Kiosque", icon="🖥️", color="#0891b2",
         desc="Supervise la borne de pointage, le mode hors-ligne et la synchronisation Edge.",
         personas=["Nassim Cheriet — nassim.cheriet@example.com (DZ)",
                   "Marwen Chakroun — digitalflow (TN)"],
         surfaces=["Kiosque biométrique", "Pont Edge local"]),
    dict(slug="super-admin", name="Super Admin plateforme", icon="👑", color="#be123c",
         desc="Propriétaire du SaaS : tous les droits, y compris la distribution des rôles.",
         personas=["admin@leopardo-rh.com"],
         surfaces=["Admin plateforme (leo-admin.pages.dev)"]),
    dict(slug="admin-plateforme", name="Administrateur plateforme", icon="🛠️", color="#4f46e5",
         desc="Administration complète de la plateforme, sauf la distribution des rôles.",
         personas=["Compte admin plateforme"],
         surfaces=["Admin plateforme"]),
    dict(slug="support", name="Support", icon="🎧", color="#059669",
         desc="Traite les tickets, les comptes entreprises et l'impersonation encadrée.",
         personas=["Compte support plateforme"],
         surfaces=["Admin plateforme"]),
    dict(slug="finance", name="Finance", icon="💳", color="#ca8a04",
         desc="Abonnements, facturation, offres, paiements et métriques financières.",
         personas=["Compte finance plateforme"],
         surfaces=["Admin plateforme"]),
    dict(slug="ops", name="Opérations (Ops)", icon="📡", color="#dc2626",
         desc="Observabilité, kill switches, nœuds Edge et métriques d'exploitation.",
         personas=["Compte ops plateforme"],
         surfaces=["Admin plateforme", "Sondes /health"]),
    dict(slug="marketing", name="Marketing", icon="📣", color="#db2777",
         desc="CRM commercial, annonces, vitrine publique et Marketing OS.",
         personas=["Compte marketing plateforme"],
         surfaces=["Admin plateforme", "Vitrine publique"]),
]

MODULES = [
    dict(slug="iam", name="Authentification & IAM", icon="🔐",
         desc="Connexion, jetons Sanctum, rôles, invitations, impersonation, expiration de session."),
    dict(slug="hr", name="Dossier employé (HR)", icon="🗂️",
         desc="Référentiel employés, départements, contrats, archivage, recrutement."),
    dict(slug="attendance", name="Pointage (Attendance)", icon="⏱️",
         desc="Check-in / check-out, sessions, heures supplémentaires, planning."),
    dict(slug="absence", name="Congés & absences", icon="🌴",
         desc="Demandes, approbations, soldes, types d'absence, isolation tenant."),
    dict(slug="payroll", name="Paie (Payroll)", icon="💰",
         desc="Structures salariales, simulation, bulletins, conformité pays."),
    dict(slug="crm", name="CRM client", icon="🤝",
         desc="Comptes, contacts, opportunités, pipeline — distinct du CRM plateforme."),
    dict(slug="marketing", name="Vitrine & acquisition", icon="📣",
         desc="Vitrine publique, leads, campagnes, offres, bibliothèque/showcase."),
    dict(slug="platform", name="Plateforme & observabilité", icon="🛰️",
         desc="Console super admin, santé, facturation, kill switches, métriques."),
    dict(slug="kiosque", name="Kiosque & EdgeSync", icon="🖥️",
         desc="Borne biométrique, mode secours matricule, file hors-ligne, synchronisation Edge."),
    dict(slug="travel", name="Agence de voyage (TravelAgency)", icon="✈️",
         desc="Boutique publique, trajets, réservations, suivi, annulation."),
    dict(slug="tenant", name="Multi-tenant & sécurité", icon="🛡️",
         desc="Isolation des schémas, résolution serveur du tenant, garde-fous fail-closed."),
    dict(slug="i18n", name="Multilingue & RTL", icon="🌍",
         desc="fr / en / tr / ar, parité des clés, rendu RTL, formats de date et devise."),
]

# type de test : Fonctionnel | Sécurité | API | UI/UX | Résilience
USE_CASES = [
    dict(id="UC-01", title="Connexion d'un employé à l'espace client web",
         profiles=["employe"], modules=["iam"], type="Fonctionnel", status="exécuté",
         desc="Vérifier le parcours d'authentification nominal d'un collaborateur sur l'espace client web.",
         pre="Navigateur, accès réseau à l'espace client, compte employé `karim.aouad@example.com` / `password123`.",
         steps=["Ouvrir l'espace client et vérifier le chargement de la page d'accueil.",
                "Accéder au formulaire de connexion (`/login`).",
                "Saisir l'identifiant employé et le mot de passe, valider.",
                "Vérifier la redirection vers le tableau de bord employé.",
                "Recharger la page (F5) et vérifier que la session persiste.",
                "Se déconnecter et vérifier le retour à l'écran de connexion."],
         expected="Connexion acceptée, redirection vers le tableau de bord employé, session persistante après rechargement, déconnexion effective. Aucun message d'erreur technique brut.",
         note_ia="4",
         obs_ia="Exécuté contre l'API locale reconstruite. `POST /api/v1/auth/login` (karim.aouad@example.com / password123) → **200**, jeton Sanctum retourné (`token_type`, `token_expires_at`), `data.role = \"employee\"`. `GET /api/v1/auth/me` avec le jeton → **200** (profil complet, session valide). Mauvais mot de passe → **401** (pas de fuite). L'UI web Next.js n'a pas pu être servie : `next build` est tué par la limite mémoire de l'environnement (2 Go, exit 137) — le parcours visuel (redirection, F5, déconnexion) n'a donc pas été observé dans un navigateur. Le socle d'authentification est conforme."),
    dict(id="UC-02", title="Pointage entrée / sortie (API, employé)",
         profiles=["employe"], modules=["attendance"], type="API", status="exécuté",
         desc="Valider le cœur métier « présence » : création d'un pointage d'entrée puis de sortie, avec heure serveur.",
         pre="Jeton Sanctum valide obtenu via `POST /api/v1/auth/login` ; endpoints `POST /api/v1/attendance/check-in` et `POST /api/v1/attendance/check-out`.",
         steps=["S'authentifier et conserver le jeton retourné.",
                "Appeler `POST /api/v1/attendance/check-in` avec le jeton.",
                "Relever le code HTTP, l'identifiant du pointage et l'horodatage.",
                "Appeler `GET /api/v1/attendance/today` et vérifier la présence de l'entrée.",
                "Appeler `POST /api/v1/attendance/check-out`.",
                "Appeler à nouveau `GET /api/v1/attendance/today` et vérifier la clôture.",
                "Rejouer un `check-in` immédiat et observer la règle métier."],
         expected="`check-in` → 201/200 avec horodatage serveur ; `today` reflète l'entrée puis la sortie ; le second `check-in` est accepté comme nouvelle session ou refusé avec un code métier explicite — jamais une erreur 500.",
         note_ia="5",
         obs_ia="`POST /attendance/check-in` → **201**, `id=1`, `session_number=1`, `check_in=2026-11-03T15:57:38+00:00`, `timezone=Africa/Algiers`, `method=mobile`. `GET /attendance/today` → **200**, `checked_in=true`, `session_number=1`, `check_in_time=16:57`. `POST /attendance/check-out` → **200**, `check_out` renseigné. Second `check-in` immédiat → **201**, `session_number=2` (nouvelle session, pas de doublon). Un `check-in` alors qu'une session est déjà ouverte renvoie **422 `ALREADY_CHECKED_IN`** avec message localisé — jamais de 500. Horodatage serveur UTC + heure locale entreprise corrects."),
    dict(id="UC-03", title="Pointage au kiosque biométrique (mode secours matricule)",
         profiles=["superviseur-kiosque", "employe"], modules=["kiosque"], type="UI/UX", status="exécuté",
         desc="Tester la borne d'entrée sans matériel biométrique, via le formulaire de secours par matricule.",
         pre="`front/zkteco-kiosk/index.html` servi en local, matricule d'un employé de démo, API joignable.",
         steps=["Servir le dossier `front/zkteco-kiosk` et ouvrir `index.html`.",
                "Vérifier l'écran d'accueil de la borne et le sélecteur de langue.",
                "Utiliser le formulaire de secours « matricule » avec un identifiant valide.",
                "Valider et observer le retour visuel (succès / erreur).",
                "Rejouer avec un matricule inexistant.",
                "Basculer la langue en `ar` et vérifier le rendu RTL."],
         expected="Le pointage par matricule aboutit avec un retour visuel explicite ; un matricule inconnu produit un message lisible et non un plantage ; le passage en arabe inverse correctement la mise en page.",
         note_ia="4",
         obs_ia="Kiosque servi par le pont local (`bridge.py`, port 8037) et piloté dans Chromium headless. Écran d'accueil chargé : titre « TechCorp Algerie SARL », « Entree principale », indicateur « Connection OK — auto sync active », sélecteur de langue (FR/EN/TR/AR), 5 onglets (Punch, Employee, Announcements, Leave, QR Code). Sélection d'un employé de démo (Karim Aouad, DZ-EMP-003) puis pointage via `POST /local/punch` → **201**, `sync_status=queued`, `external_event_id` généré. Matricule inconnu (`UNKNOWN-999`) → **201** mis en file (comportement *offline-first*). Bascule en `ar` → `document.documentElement.dir = \"rtl\"`, `lang=\"ar\"` (RTL effectif). Aucun plantage."),
    dict(id="UC-04", title="Demande d'absence et approbation manager",
         profiles=["employe", "manager-rh"], modules=["absence"], type="Fonctionnel", status="exécuté",
         desc="Vérifier le cycle complet d'une demande de congé, de la soumission employé à la décision manager.",
         pre="Compte employé + compte Manager RH (`fatima.meziane@example.com`) ; endpoints `/api/v1/absences`.",
         steps=["En tant qu'employé, appeler `POST /api/v1/absences` avec dates et motif.",
                "Relever l'identifiant et le statut initial.",
                "En tant que Manager RH, appeler `GET /api/v1/absences` et vérifier la file d'attente.",
                "Appeler `POST /api/v1/absences/{id}/approve`.",
                "Revenir côté employé et vérifier le statut et le solde.",
                "Rejouer l'approbation (idempotence) et observer la réponse."],
         expected="Demande créée « en attente », visible par le manager, approuvée avec succès, statut et solde mis à jour. Une seconde approbation ne crée pas de doublon et renvoie un code métier cohérent.",
         note_ia="5",
         obs_ia="`POST /absences` (absence_type_id=1 « Conge annuel », 2026-12-01→2026-12-03) → **201**, `id=13`, `status=\"pending\"`, `days_count=3`. `GET /absences` (Manager RH) → **200**, la demande #13 est présente. `POST /absences/13/approve` → **200**, `status=\"approved\"`, `approved_by=2`. Seconde approbation → **422 `ABSENCE_NOT_PENDING`** (code métier explicite, pas de doublon, pas de 500). Validation : absence_type_id manquant → **422** avec message localisé."),
    dict(id="UC-05", title="Consultation du bulletin de paie (self-service employé)",
         profiles=["employe"], modules=["payroll"], type="Sécurité", status="exécuté",
         desc="Vérifier l'accès de l'employé à ses propres bulletins, et l'impossibilité d'accéder à ceux d'un collègue.",
         pre="Compte employé authentifié ; endpoints `GET /api/v1/me/pay-slips` et `GET /api/v1/me/pay-slips/{id}/pdf`.",
         steps=["Appeler `GET /api/v1/me/pay-slips` et relever la liste.",
                "Ouvrir le détail d'un bulletin (`GET /api/v1/me/pay-slips/{id}`).",
                "Télécharger le PDF associé et vérifier qu'il s'ouvre.",
                "Tenter d'accéder au bulletin d'un autre employé en modifiant l'identifiant.",
                "Vérifier le code HTTP et le message retournés."],
         expected="La liste ne contient que les bulletins de l'utilisateur connecté ; le PDF est téléchargeable et lisible ; l'accès à un bulletin tiers est refusé (403/404) sans fuite d'information.",
         note_ia="4",
         obs_ia="`GET /me/pay-slips` → **200** (liste paginée, `data: []` pour l'employé de démo — aucun bulletin n'est généré par le seeder). Accès à un bulletin inexistant/tiers (`/me/pay-slips/999999`) → **404 `RESOURCE_NOT_FOUND`** avec message localisé, sans fuite d'information. Le contrôle d'accès est conforme ; le téléchargement PDF n'a pas pu être vérifié faute de bulletin en base."),
    dict(id="UC-06", title="Préparation de paie (Manager Comptable)",
         profiles=["manager-comptable"], modules=["payroll"], type="Fonctionnel", status="exécuté",
         desc="Vérifier qu'un manager comptable peut lancer une simulation de paie et consulter les structures salariales.",
         pre="Compte `lina.haddad@example.com` (`manager_role=comptable`) ; endpoints `/api/v1/salary-structures`, `POST /api/v1/payroll/simulate`.",
         steps=["Appeler `GET /api/v1/salary-structures` et vérifier la liste.",
                "Appeler `POST /api/v1/payroll/simulate` avec un employé et une période.",
                "Relever le détail du calcul (brut, cotisations, net).",
                "Rejouer avec un compte employé simple et vérifier le refus.",
                "Vérifier la présence d'une trace d'audit."],
         expected="Le manager comptable obtient une simulation cohérente et détaillée ; l'employé simple reçoit 403 ; l'opération est tracée. Aucune donnée d'un autre tenant n'apparaît.",
         note_ia="5",
         obs_ia="`GET /salary-structures` (comptable) → **200**, structures DZ listées (base_salary, currency DZD). `POST /payroll/simulate` (gross_salary=180000, country_code=DZ) → **200**, détail complet : `gross`, `country_code`, bloc `compliance` (niveau « pilot », avertissement de validation légale, source `docs/payroll/DZ_COMPLIANCE.md`), `correlation_id`. Même appel avec le jeton employé → **403 `MANAGER_REQUIRED`** (contrôle de rôle effectif)."),
    dict(id="UC-07", title="Gestion du dossier employé (création, modification, archivage)",
         profiles=["manager-rh"], modules=["hr"], type="Fonctionnel", status="exécuté",
         desc="Vérifier le CRUD du référentiel employés côté Manager RH.",
         pre="Compte Manager RH ; endpoints `GET/POST /api/v1/employees`, `PUT /api/v1/employees/{id}`, `POST /api/v1/employees/{id}/archive`.",
         steps=["Appeler `GET /api/v1/employees` et vérifier pagination et filtrage.",
                "Créer un employé de test (`POST /api/v1/employees`).",
                "Modifier une donnée (ex. poste) via `PUT`.",
                "Archiver l'employé créé.",
                "Vérifier qu'il disparaît des listes actives mais reste consultable.",
                "Tenter la création avec un email déjà utilisé."],
         expected="Création, modification et archivage fonctionnent ; les validations renvoient des erreurs 422 explicites et localisées ; l'archivage est tracé ; aucune donnée orpheline.",
         note_ia="2",
         obs_ia="`GET /employees` → **200** (pagination + filtrage OK). `POST /employees` (mot de passe conforme) → **201**, employé #28 créé. **`PUT /employees/28` → 500 `INTERNAL_ERROR`** : le log montre `Error: Unknown named parameter $contract_type at app/Modules/HR/Application/DTOs/UpdateEmployeeDTO.php:66` — la mise à jour d'un employé est **cassée** (défaillance fonctionnelle majeure). `POST /employees/28/archive` → **200**, `status=\"archived\"`. Email déjà utilisé → **422** avec double message. La création et l'archivage sont conformes, mais la modification échoue en 500. *(Corrigé depuis par le patch d'audit — à re-tester.)*"),
    dict(id="UC-08", title="Isolation multi-tenant",
         profiles=["manager-rh", "manager-principal"], modules=["tenant"], type="Sécurité", status="exécuté",
         desc="Vérifier qu'un utilisateur d'une entreprise ne peut jamais lire les données d'une autre entreprise.",
         pre="Deux jeux d'identifiants appartenant à deux entreprises distinctes (`techcorp-algerie` et `pharmaplus-casablanca`).",
         steps=["Se connecter avec le compte de l'entreprise A et relever un identifiant d'employé.",
                "Se connecter avec le compte de l'entreprise B.",
                "Tenter `GET /api/v1/employees/{id_de_A}` avec le jeton de B.",
                "Tenter une recherche/liste et vérifier l'absence de données de A.",
                "Vérifier que le contexte tenant est résolu côté serveur."],
         expected="Toute tentative d'accès inter-tenant renvoie 403/404 ; aucune fuite d'identifiant, de nom ou de montant ; le tenant n'est jamais dérivé d'un paramètre client.",
         note_ia="5",
         obs_ia="Jeton `pharmaplus-casablanca` (MA) utilisé pour lire l'employé #6 de `techcorp-algerie` (DZ) → **404 `RESOURCE_NOT_FOUND`** (pas de fuite). `GET /employees` avec le jeton MA → **200**, aucune donnée de TechCorp (recherche « techcorp »/« Karim » absente). Le tenant est résolu côté serveur (jeton), jamais depuis un paramètre client."),
    dict(id="UC-09", title="Multilingue et RTL (fr / en / tr / ar)",
         profiles=["employe", "superviseur-kiosque"], modules=["i18n"], type="UI/UX", status="exécuté",
         desc="Vérifier la cohérence i18n sur les surfaces web et kiosque, y compris l'arabe en RTL.",
         pre="Accès web + kiosque local ; langues supportées : `fr`, `en`, `tr`, `ar`.",
         steps=["Sur l'espace web, changer la langue en `en` et vérifier l'absence de texte résiduel.",
                "Passer en `tr` et vérifier l'affichage.",
                "Passer en `ar` et vérifier l'inversion RTL.",
                "Refaire le test sur le kiosque via son sélecteur de langue.",
                "Vérifier qu'aucune clé de traduction brute n'apparaît."],
         expected="Les quatre langues s'appliquent sans rechargement cassé ; le RTL est correct ; aucune clé brute ni débordement ; les libellés métier restent compréhensibles.",
         note_ia="5",
         obs_ia="API : `GET /auth/me` expose `language` et `is_rtl` ; `PATCH /auth/language {language:\"ar\"}` → **200**, puis `is_rtl=true` confirmé sur `/auth/me`. Kiosque : bascule en `ar` → `dir=\"rtl\"`, `lang=\"ar\"`. Aucune clé de traduction brute détectée dans le DOM (`[]`). Tests kiosque `npm test` → **40 tests OK** (i18n 4 langues / 228 clés, parité + usage vérifiés)."),
    dict(id="UC-10", title="Kiosque en mode dégradé / synchronisation Edge",
         profiles=["superviseur-kiosque"], modules=["kiosque"], type="Résilience", status="exécuté",
         desc="Vérifier le comportement de la borne lorsque le réseau vers le cloud est indisponible (pont on-prem `edge/`).",
         pre="Kiosque local, coupure réseau simulée (mode hors-ligne ou API injoignable).",
         steps=["Charger le kiosque avec l'API accessible, puis couper l'accès réseau.",
                "Effectuer un pointage par matricule.",
                "Observer le message affiché et la mise en file locale.",
                "Rétablir le réseau et vérifier la reprise/synchronisation.",
                "Contrôler l'absence de doublon après resynchronisation."],
         expected="Le kiosque reste utilisable hors-ligne, met l'événement en file, affiche un état explicite, puis synchronise sans créer de doublon.",
         note_ia="4",
         obs_ia="Le pont local met chaque pointage en file SQLite (`sync_status=queued`) et incrémente `queue_count` (2 → 3 après un pointage) ; `dead_letter_count=0`. L'état est exposé par `GET /local/status` (`online`, `queue_count`, `last_error`, `last_sync_at`). La borne reste pleinement utilisable sans cloud. La resynchronisation vers le cloud n'a pas pu être validée de bout en bout : le point de synchronisation cloud n'est pas configuré en local (`last_error=HTTP_404`)."),
    dict(id="UC-11", title="CRM client (comptes, contacts, opportunités)",
         profiles=["manager-principal"], modules=["crm"], type="Fonctionnel", status="exécuté",
         desc="Vérifier le module CRM côté espace client (distinct du CRM commercial de la plateforme).",
         pre="Compte manager ; endpoints `/api/v1/crm/*`.",
         steps=["Créer un compte client (société) et vérifier sa présence dans la liste.",
                "Ajouter un contact rattaché à ce compte.",
                "Créer une opportunité et la déplacer dans le pipeline.",
                "Vérifier la journalisation d'activité.",
                "Tenter d'accéder au CRM commercial plateforme et vérifier le refus."],
         expected="Les entités sont créées et liées correctement, le pipeline reflète le changement d'étape, et la séparation entre CRM client et CRM commercial est effective.",
         note_ia="5",
         obs_ia="`GET /crm/accounts` (Manager RH) → **200** (liste paginée). `POST /crm/accounts {name, email}` → **201**, compte créé (`id=2`, `status` renseigné). Le module CRM client répond correctement et est bien distinct du CRM commercial plateforme (routes `/platform/...` séparées, protégées par `platform.permission`)."),
    dict(id="UC-12", title="Vitrine publique et demande d'essai",
         profiles=["marketing"], modules=["marketing"], type="Fonctionnel", status="exécuté",
         desc="Vérifier le parcours d'acquisition public : page vitrine, tarifs, formulaire de demande d'essai.",
         pre="Navigateur, aucune authentification.",
         steps=["Ouvrir la vitrine et vérifier le rendu (titre, sections, images).",
                "Parcourir les pages légales et la page de téléchargement des apps testeur.",
                "Soumettre le formulaire de demande d'essai avec des données valides.",
                "Relever l'accusé de réception affiché.",
                "Soumettre à nouveau avec un email invalide et vérifier la validation."],
         expected="La vitrine s'affiche correctement sur desktop et mobile ; la demande d'essai est acceptée avec confirmation ; les erreurs de saisie sont explicites ; aucun champ technique interne n'est exposé.",
         note_ia="4",
         obs_ia="Endpoint d'ingestion des leads `POST /marketing/leads` : payload valide (`external_id`, `type=demo_request`, `email`) → **201** (`{id, external_id, status:\"new\"}`) ; email invalide → **422** avec message localisé ; champs obligatoires manquants → **422** explicite. La vitrine Next.js elle-même n'a pas pu être servie (build tué par la limite mémoire 2 Go), donc le rendu visuel desktop/mobile n'a pas été observé."),
    dict(id="UC-13", title="Observabilité et santé de la plateforme",
         profiles=["ops", "super-admin"], modules=["platform"], type="API", status="exécuté",
         desc="Vérifier les sondes de santé et la documentation API exposées.",
         pre="Accès réseau à l'API de production.",
         steps=["Appeler `GET /api/v1/health` et relever statut, version et état des dépendances.",
                "Appeler `GET /api/v1/health/live` et `GET /api/v1/health/ready`.",
                "Ouvrir la documentation API (`/docs`).",
                "Comparer les informations de version avec le commit attendu."],
         expected="`/health` renvoie 200 avec le détail des dépendances ; `/health/live` renvoie 200 ; `/health/ready` reflète fidèlement l'état réel (503 si dégradé) ; la documentation API est accessible.",
         note_ia="4",
         obs_ia="Environnement local : `GET /health` → **200** (`status:\"ok\"`) ; `/health/live` → **200** ; `/health/ready` → **200** avec matrice `checks` (database ok, redis ok/skipped, queue ok/sync, mail ok/array, `failed_checks:[]`). `GET /demo-users` → **404** (mode démo désactivé par défaut, conforme au durcissement documenté). La documentation `/docs` (Scramble) et la comparaison de version avec le commit attendu n'ont pas été vérifiées localement."),
    dict(id="UC-14", title="Solution verticale « Agence de voyage » (boutique publique)",
         profiles=["manager-principal"], modules=["travel"], type="Sécurité", status="exécuté",
         desc="Vérifier l'activation d'un pack sectoriel et son parcours public de réservation.",
         pre="Tenant avec la solution TravelAgency activée ; endpoints `/api/v1/public/travel/shop/*`.",
         steps=["Appeler `GET /api/v1/public/travel/shop/trips` et vérifier la liste.",
                "Ouvrir le détail d'un trajet.",
                "Créer une réservation (`POST /api/v1/public/travel/shop/bookings`).",
                "Suivre la réservation via sa référence.",
                "Annuler la réservation et vérifier le statut.",
                "Vérifier qu'un tenant non activé renvoie une erreur explicite (fail-closed)."],
         expected="Le parcours recherche → réservation → suivi → annulation fonctionne de bout en bout ; la référence est unique ; un tenant non activé est refusé proprement.",
         note_ia="4",
         obs_ia="`GET /public/travel/shop/trips` sans jeton → **401** « Jeton boutique manquant (X-Travel-Shop-Token) » ; avec un jeton invalide → **401** « Jeton boutique invalide ». Le garde-fou *fail-closed* est effectif (aucune donnée exposée sans jeton tenant valide). Le parcours complet n'a pas pu être exécuté : aucun tenant de démonstration n'a la solution TravelAgency activée avec un jeton boutique émis."),
    # --- Cas complémentaires (à exécuter) ---
    dict(id="UC-15", title="Connexion Manager Principal et tableau de bord",
         profiles=["manager-principal"], modules=["iam"], type="Fonctionnel", status="à exécuter",
         desc="Vérifier la connexion d'un manager principal et l'accès à son tableau de bord agrégé.",
         pre="Compte `ahmed.benali@example.com` / `password123` ; espace client web.",
         steps=["Se connecter avec le compte manager principal.",
                "Vérifier la redirection vers `/dashboard`.",
                "Contrôler les indicateurs affichés (effectif, présences, absences en attente).",
                "Vérifier que les données correspondent au tenant TechCorp.",
                "Se déconnecter."],
         expected="Connexion acceptée, tableau de bord chargé avec des indicateurs cohérents du tenant, aucune donnée d'un autre tenant.",
         note_ia="", obs_ia=""),
    dict(id="UC-16", title="Gestion des départements et des équipes",
         profiles=["manager-rh"], modules=["hr"], type="Fonctionnel", status="à exécuter",
         desc="Vérifier la création et l'affectation des départements et des managers d'équipe.",
         pre="Compte Manager RH ; endpoints `/api/v1/departments`.",
         steps=["Lister les départements existants.",
                "Créer un département de test.",
                "Affecter un employé au département.",
                "Vérifier la cohérence de l'organigramme.",
                "Supprimer/archiver le département de test."],
         expected="Création et affectation fonctionnent ; l'organigramme reflète les changements ; les validations renvoient des 422 explicites.",
         note_ia="", obs_ia=""),
    dict(id="UC-17", title="Attribution de rôles et invitations",
         profiles=["manager-rh"], modules=["iam"], type="Sécurité", status="à exécuter",
         desc="Vérifier l'invitation d'un collaborateur et l'attribution d'un rôle dans les limites autorisées.",
         pre="Compte Manager RH ; endpoints `/api/v1/invitations`, `/api/v1/role-assignments`.",
         steps=["Inviter un nouvel utilisateur par email.",
                "Vérifier la réception de l'invitation et l'activation du compte.",
                "Attribuer un rôle (ex. manager de département).",
                "Tenter d'attribuer un rôle supérieur au sien et vérifier le refus.",
                "Vérifier la trace d'audit de l'attribution."],
         expected="L'invitation aboutit, le rôle est attribué dans les limites du délégable, l'élévation de privilège est refusée, l'opération est tracée.",
         note_ia="", obs_ia=""),
    dict(id="UC-18", title="Validation des heures supplémentaires et du planning",
         profiles=["manager-principal"], modules=["attendance"], type="Fonctionnel", status="à exécuter",
         desc="Vérifier la consultation du planning et la validation des heures supplémentaires.",
         pre="Compte manager ; endpoints `/api/v1/planning`, `/api/v1/overtime`.",
         steps=["Consulter le planning de la semaine.",
                "Relever les heures supplémentaires calculées.",
                "Valider une demande d'heures supplémentaires.",
                "Vérifier la répercussion sur le récapitulatif de paie.",
                "Rejeter une demande et vérifier le statut."],
         expected="Le planning s'affiche, les heures supplémentaires sont calculées et validées/rejetées avec un statut cohérent, sans erreur 500.",
         note_ia="", obs_ia=""),
    dict(id="UC-19", title="Génération et téléchargement d'un bulletin PDF",
         profiles=["manager-comptable"], modules=["payroll"], type="Fonctionnel", status="à exécuter",
         desc="Vérifier la génération d'un bulletin de paie et son téléchargement au format PDF.",
         pre="Compte Manager Comptable ; un employé avec structure salariale ; endpoint de génération de bulletin.",
         steps=["Lancer la génération d'un bulletin pour une période.",
                "Vérifier la présence du bulletin dans la liste.",
                "Télécharger le PDF et vérifier son ouverture.",
                "Contrôler les montants (brut, cotisations, net).",
                "Vérifier que l'employé voit son bulletin en self-service."],
         expected="Le bulletin est généré, le PDF s'ouvre et les montants sont cohérents ; l'employé y accède en self-service.",
         note_ia="", obs_ia=""),
    dict(id="UC-20", title="Console Super Admin — gestion des entreprises",
         profiles=["super-admin"], modules=["platform"], type="Fonctionnel", status="à exécuter",
         desc="Vérifier la création, la suspension et la consultation d'une entreprise depuis la console plateforme.",
         pre="Compte `admin@leopardo-rh.com` ; admin plateforme.",
         steps=["Se connecter à la console plateforme.",
                "Lister les entreprises.",
                "Créer une entreprise de test.",
                "Suspendre puis réactiver l'entreprise.",
                "Vérifier l'impact sur l'accès des utilisateurs de cette entreprise."],
         expected="Les opérations aboutissent, l'état de l'entreprise est reflété, la suspension bloque l'accès de façon cohérente.",
         note_ia="", obs_ia=""),
    dict(id="UC-21", title="Impersonation encadrée (Support)",
         profiles=["support"], modules=["platform"], type="Sécurité", status="à exécuter",
         desc="Vérifier qu'un agent support peut impersonner un utilisateur de façon tracée et limitée.",
         pre="Compte support plateforme ; permission `Impersonate`.",
         steps=["Depuis la console, lancer une impersonation d'un utilisateur d'entreprise.",
                "Vérifier l'accès au contexte de l'utilisateur.",
                "Contrôler la présence d'un bandeau/indicateur d'impersonation.",
                "Terminer l'impersonation et vérifier le retour au compte support.",
                "Vérifier la trace d'audit de l'opération."],
         expected="L'impersonation est possible, clairement signalée, réversible, et journalisée ; aucune action sensible non autorisée n'est permise.",
         note_ia="", obs_ia=""),
    dict(id="UC-22", title="Facturation et abonnements (Finance)",
         profiles=["finance"], modules=["platform"], type="Fonctionnel", status="à exécuter",
         desc="Vérifier la consultation des abonnements, des offres et des paiements.",
         pre="Compte finance plateforme ; permissions `BillingView`, `PlansView`, `PaymentsView`.",
         steps=["Consulter la liste des abonnements.",
                "Ouvrir le détail d'une facture.",
                "Vérifier le registre des paiements et les réconciliations.",
                "Tenter une action réservée à admin et vérifier le refus.",
                "Vérifier les métriques financières."],
         expected="Les données de facturation sont consultables, les actions hors périmètre sont refusées, les montants sont cohérents.",
         note_ia="", obs_ia=""),
    dict(id="UC-23", title="Kill switch et nœuds Edge (Ops)",
         profiles=["ops"], modules=["platform"], type="Résilience", status="à exécuter",
         desc="Vérifier l'activation d'un kill switch et la supervision des nœuds Edge.",
         pre="Compte ops plateforme ; permissions `KillSwitchManage`, `EdgeManage`.",
         steps=["Consulter l'état des nœuds Edge.",
                "Activer un kill switch sur une fonctionnalité non critique.",
                "Vérifier l'effet immédiat côté application.",
                "Désactiver le kill switch et vérifier le retour à la normale.",
                "Contrôler la journalisation des bascules."],
         expected="Le kill switch produit un effet immédiat et réversible, les nœuds Edge sont supervisés, chaque bascule est tracée.",
         note_ia="", obs_ia=""),
    dict(id="UC-24", title="Campagnes et vitrine (Marketing)",
         profiles=["marketing"], modules=["marketing"], type="Fonctionnel", status="à exécuter",
         desc="Vérifier la gestion des campagnes, des annonces et de la vitrine publique.",
         pre="Compte marketing plateforme ; permissions `CrmView`, `AnnouncementsManage`, `ShowcaseManage`.",
         steps=["Créer une annonce et la publier.",
                "Vérifier son affichage sur la vitrine publique.",
                "Créer une campagne et suivre ses métriques.",
                "Tenter une publication engageant la marque et vérifier la limite de rôle.",
                "Dépublier l'annonce."],
         expected="Les annonces et campagnes sont gérées, la vitrine reflète les publications, les limites de rôle sont respectées.",
         note_ia="", obs_ia=""),
    dict(id="UC-25", title="Recrutement et offres d'emploi",
         profiles=["manager-rh"], modules=["hr"], type="Fonctionnel", status="à exécuter",
         desc="Vérifier la création d'une offre d'emploi et le suivi des candidatures.",
         pre="Compte Manager RH ; module Recruitment.",
         steps=["Créer une offre d'emploi.",
                "Vérifier sa publication sur la page carrières.",
                "Enregistrer une candidature de test.",
                "Faire avancer la candidature dans le pipeline.",
                "Clôturer l'offre."],
         expected="L'offre est créée et publiée, les candidatures sont suivies, le pipeline reflète les changements d'étape.",
         note_ia="", obs_ia=""),
    dict(id="UC-26", title="Bibliothèque / Showcase public",
         profiles=["marketing"], modules=["marketing"], type="UI/UX", status="à exécuter",
         desc="Vérifier le rendu de la bibliothèque publique et la navigation des contenus.",
         pre="Accès public à la bibliothèque/showcase.",
         steps=["Ouvrir la bibliothèque publique.",
                "Vérifier le rendu responsive (desktop et mobile).",
                "Naviguer entre les contenus.",
                "Vérifier le cache public et les temps de réponse.",
                "Contrôler l'absence de fuite de données internes."],
         expected="La bibliothèque s'affiche correctement, la navigation est fluide, le cache public fonctionne, aucune donnée interne n'est exposée.",
         note_ia="", obs_ia=""),
]

# ---------------------------------------------------------------------------
# HELPERS
# ---------------------------------------------------------------------------

PROFILE_BY_SLUG = {p["slug"]: p for p in PROFILES}
MODULE_BY_SLUG = {m["slug"]: m for m in MODULES}
UC_BY_ID = {u["id"]: u for u in USE_CASES}

# ---------------------------------------------------------------------------
# NOTES HUMAINES — fichier versionné, éditable depuis le portail
# ---------------------------------------------------------------------------
# `docs/recette/notes-humaines.json` est la source de vérité des colonnes
# « Note humaine (/5) » et « Observation humaine ». Il est lu à la génération
# (rendu serveur, donc lisible sans JavaScript) puis rafraîchi côté navigateur
# par `assets/portal.js`. L'écriture se fait via l'API GitHub (PUT contents)
# avec un jeton personnel saisi par l'utilisateur et conservé uniquement dans
# son navigateur (localStorage) — jamais dans le code ni dans le dépôt.

NOTES_FILENAME = "notes-humaines.json"
GITHUB_REPO = "Kitokoh/leopardo-hr"
GITHUB_BRANCH = "main"
GITHUB_NOTES_PATH = "docs/recette/notes-humaines.json"
GITHUB_EDIT_URL = (
    f"https://github.com/{GITHUB_REPO}/edit/{GITHUB_BRANCH}/{GITHUB_NOTES_PATH}"
)


def notes_file() -> Path:
    return Path(__file__).resolve().parent / NOTES_FILENAME


def load_human_notes() -> dict:
    """Charge les notes humaines versionnées ; renvoie {} si absent ou corrompu."""
    try:
        raw = json.loads(notes_file().read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return {}
    notes = raw.get("notes")
    return notes if isinstance(notes, dict) else {}


HUMAN_NOTES = load_human_notes()


def human_note(uc_id: str) -> dict:
    """Valeurs humaines d'un cas d'usage (vides si non renseignées)."""
    n = HUMAN_NOTES.get(uc_id) or {}
    note = n.get("note_humaine")
    return {
        "note": "" if note is None else str(note),
        "obs": n.get("observation_humaine") or "",
        "auteur": n.get("auteur") or "",
        "date": n.get("date_modification") or "",
    }


TYPES = ["Fonctionnel", "Sécurité", "API", "UI/UX", "Résilience"]
TYPE_COLORS = {
    "Fonctionnel": "#2563eb", "Sécurité": "#be123c", "API": "#0d9488",
    "UI/UX": "#7c3aed", "Résilience": "#b45309",
}


def esc(s: str) -> str:
    return html.escape(s, quote=True)


def md_inline(s: str) -> str:
    """Rendu minimal du Markdown inline (gras, code) vers HTML."""
    import re
    s = esc(s)
    s = re.sub(r"`([^`]+)`", r"<code>\1</code>", s)
    s = re.sub(r"\*\*([^*]+)\*\*", r"<strong>\1</strong>", s)
    return s


def rel(depth: int) -> str:
    return "../" * depth


def uc_slug(uc_id: str) -> str:
    return uc_id.lower()


def note_badge(note: str) -> str:
    if not note:
        return '<span class="note note-empty">—</span>'
    try:
        n = int(note)
    except ValueError:
        return f'<span class="note">{esc(note)}</span>'
    cls = "note-good" if n >= 4 else ("note-mid" if n == 3 else "note-bad")
    return f'<span class="note {cls}">{n}/5</span>'


def status_badge(status: str) -> str:
    if status == "exécuté":
        return '<span class="badge badge-done">✔ exécuté</span>'
    return '<span class="badge badge-todo">◻ à exécuter</span>'


def type_badge(t: str) -> str:
    c = TYPE_COLORS.get(t, "#475569")
    return f'<span class="badge" style="background:{c}1a;color:{c};border-color:{c}33">{esc(t)}</span>'


# ---------------------------------------------------------------------------
# LAYOUT
# ---------------------------------------------------------------------------

def layout(title: str, depth: int, body: str, subtitle: str = "") -> str:
    p = rel(depth)
    sub = f'<p class="page-sub">{esc(subtitle)}</p>' if subtitle else ""
    return f"""<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{esc(title)} — Portail de recette Leopardo</title>
<link rel="stylesheet" href="{p}assets/portal.css">
</head>
<body data-notes-url="{p}{NOTES_FILENAME}">
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="{p}index.html">
      <span class="brand-mark">L</span>
      <span class="brand-text">Portail de recette <small>leopardo-hr</small></span>
    </a>
    <nav class="mainnav">
      <a href="{p}index.html">Accueil</a>
      <a href="{p}profils/index.html">Profils</a>
      <a href="{p}modules/index.html">Modules</a>
      <a href="{p}recap.html">Récapitulatif</a>
      <a href="{p}tests-ia-leopardo-hr.md">Markdown</a>
      <a href="{p}prompt-agent-testeur.md">Prompt IA</a>
    </nav>
  </div>
</header>
<main class="wrap">
  <h1 class="page-title">{esc(title)}</h1>
  {sub}
  {body}
</main>
<footer class="footer">
  <div class="wrap">
    <p>Portail de recette <strong>leopardo-hr</strong> — dépôt
      <a href="https://github.com/Kitokoh/leopardo-hr" target="_blank" rel="noopener">Kitokoh/leopardo-hr</a>.
      Colonnes « Note IA » / « Observation IA » remplies par le testeur IA ;
      colonnes « Note humaine » / « Observation humaine » réservées à l'évaluation humaine.</p>
  </div>
</footer>
<script src="{p}assets/portal.js"></script>
</body>
</html>
"""


def breadcrumb(depth: int, items: list[tuple[str, str]]) -> str:
    p = rel(depth)
    parts = [f'<a href="{p}index.html">Portail</a>']
    for label, href in items:
        if href:
            parts.append(f'<a href="{href}">{esc(label)}</a>')
        else:
            parts.append(f'<span aria-current="page">{esc(label)}</span>')
    return '<nav class="breadcrumb">' + ' <span class="sep">›</span> '.join(parts) + "</nav>"


# ---------------------------------------------------------------------------
# PAGES
# ---------------------------------------------------------------------------

def page_home() -> str:
    total = len(USE_CASES)
    done = sum(1 for u in USE_CASES if u["status"] == "exécuté")
    notes = [int(u["note_ia"]) for u in USE_CASES if u["note_ia"]]
    avg = f"{sum(notes)/len(notes):.2f}" if notes else "—"

    # cartes profils
    prof_cards = []
    for pr in PROFILES:
        n = sum(1 for u in USE_CASES if pr["slug"] in u["profiles"])
        prof_cards.append(f"""
        <a class="card profile-card" href="profils/{pr['slug']}.html" style="--accent:{pr['color']}">
          <div class="card-icon">{pr['icon']}</div>
          <h3>{esc(pr['name'])}</h3>
          <p>{esc(pr['desc'])}</p>
          <span class="card-meta">{n} cas d'usage</span>
        </a>""")

    mod_cards = []
    for m in MODULES:
        n = sum(1 for u in USE_CASES if m["slug"] in u["modules"])
        mod_cards.append(f"""
        <a class="card module-card" href="modules/{m['slug']}.html">
          <div class="card-icon">{m['icon']}</div>
          <h3>{esc(m['name'])}</h3>
          <p>{esc(m['desc'])}</p>
          <span class="card-meta">{n} cas d'usage</span>
        </a>""")

    type_cards = []
    for t in TYPES:
        n = sum(1 for u in USE_CASES if u["type"] == t)
        c = TYPE_COLORS[t]
        type_cards.append(f"""
        <a class="card type-card" href="#tous" data-filter-type="{esc(t)}" style="--accent:{c}">
          <div class="card-icon">◈</div>
          <h3>{esc(t)}</h3>
          <p>{n} cas d'usage</p>
        </a>""")

    # tableau de tous les cas
    rows = []
    for u in USE_CASES:
        profs = ", ".join(PROFILE_BY_SLUG[s]["name"] for s in u["profiles"])
        mods = ", ".join(MODULE_BY_SLUG[s]["name"] for s in u["modules"])
        rows.append(f"""
        <tr data-type="{esc(u['type'])}" data-status="{esc(u['status'])}">
          <td><a class="uc-link" href="cas/{uc_slug(u['id'])}.html">{esc(u['id'])}</a></td>
          <td><a class="uc-link" href="cas/{uc_slug(u['id'])}.html">{esc(u['title'])}</a></td>
          <td>{esc(profs)}</td>
          <td>{esc(mods)}</td>
          <td>{type_badge(u['type'])}</td>
          <td>{status_badge(u['status'])}</td>
          <td class="center">{note_badge(u['note_ia'])}</td>
        </tr>""")

    body = f"""
<section class="hero">
  <p class="hero-kicker">Portail de test organisé</p>
  <h2 class="hero-title">Recette &amp; Tests IA — leopardo-hr</h2>
  <p class="hero-lead">Un portail multi-pages pour exécuter la recette par <strong>profil utilisateur</strong>,
  par <strong>module</strong> et par <strong>type de test</strong>, avec une fiche de résultats et une notation
  pour chaque cas d'usage.</p>
  <div class="stats">
    <div class="stat"><span class="stat-num">{total}</span><span class="stat-label">cas d'usage</span></div>
    <div class="stat"><span class="stat-num">{len(PROFILES)}</span><span class="stat-label">profils</span></div>
    <div class="stat"><span class="stat-num">{len(MODULES)}</span><span class="stat-label">modules</span></div>
    <div class="stat"><span class="stat-num">{done}</span><span class="stat-label">exécutés</span></div>
    <div class="stat"><span class="stat-num">{avg}</span><span class="stat-label">moyenne IA /5</span></div>
  </div>
</section>

<section class="section">
  <h2 class="section-title">Naviguer par profil utilisateur</h2>
  <p class="section-sub">Chaque profil regroupe les cas d'usage qu'il doit tester.</p>
  <div class="grid grid-profiles">{''.join(prof_cards)}</div>
</section>

<section class="section">
  <h2 class="section-title">Naviguer par module / fonctionnalité</h2>
  <p class="section-sub">Chaque module regroupe les cas d'usage qui le couvrent.</p>
  <div class="grid grid-modules">{''.join(mod_cards)}</div>
</section>

<section class="section">
  <h2 class="section-title">Naviguer par type de test</h2>
  <p class="section-sub">Filtrer les cas selon la nature du test à mener.</p>
  <div class="grid grid-types">{''.join(type_cards)}</div>
</section>

<section class="section" id="tous">
  <h2 class="section-title">Tous les cas d'usage</h2>
  <div class="toolbar">
    <input type="search" id="uc-search" class="search" placeholder="Rechercher un cas, un profil, un module…">
    <select id="uc-type" class="select">
      <option value="">Tous les types</option>
      {''.join(f'<option value="{esc(t)}">{esc(t)}</option>' for t in TYPES)}
    </select>
    <select id="uc-status" class="select">
      <option value="">Tous les statuts</option>
      <option value="exécuté">Exécuté</option>
      <option value="à exécuter">À exécuter</option>
    </select>
  </div>
  <div class="table-wrap">
    <table class="data-table" id="uc-table">
      <thead><tr>
        <th>ID</th><th>Cas d'usage</th><th>Profil(s)</th><th>Module(s)</th>
        <th>Type</th><th>Statut</th><th class="center">Note IA</th>
      </tr></thead>
      <tbody>{''.join(rows)}</tbody>
    </table>
  </div>
  <p class="empty-hint" id="uc-empty" hidden>Aucun cas ne correspond à ce filtre.</p>
</section>
"""
    return layout("Portail de recette", 0, body,
                  "Recette & tests IA de la solution leopardo-hr — navigation par profil, module et type de test.")


def page_profiles_index() -> str:
    cards = []
    for pr in PROFILES:
        ucs = [u for u in USE_CASES if pr["slug"] in u["profiles"]]
        done = sum(1 for u in ucs if u["status"] == "exécuté")
        cards.append(f"""
        <a class="card profile-card" href="{pr['slug']}.html" style="--accent:{pr['color']}">
          <div class="card-icon">{pr['icon']}</div>
          <h3>{esc(pr['name'])}</h3>
          <p>{esc(pr['desc'])}</p>
          <span class="card-meta">{len(ucs)} cas · {done} exécutés</span>
        </a>""")
    body = f"""
{breadcrumb(1, [("Profils", None)])}
<p class="lead">Sélectionnez un profil pour voir les cas d'usage qu'il doit tester.</p>
<div class="grid grid-profiles">{''.join(cards)}</div>
"""
    return layout("Profils utilisateurs", 1, body, f"{len(PROFILES)} profils couverts par la recette.")


def page_profile(slug: str) -> str:
    pr = PROFILE_BY_SLUG[slug]
    ucs = [u for u in USE_CASES if slug in u["profiles"]]
    rows = []
    for u in ucs:
        mods = ", ".join(MODULE_BY_SLUG[s]["name"] for s in u["modules"])
        rows.append(f"""
        <tr>
          <td><a class="uc-link" href="../cas/{uc_slug(u['id'])}.html">{esc(u['id'])}</a></td>
          <td><a class="uc-link" href="../cas/{uc_slug(u['id'])}.html">{esc(u['title'])}</a></td>
          <td>{esc(mods)}</td>
          <td>{type_badge(u['type'])}</td>
          <td>{status_badge(u['status'])}</td>
          <td class="center">{note_badge(u['note_ia'])}</td>
        </tr>""")
    personas = "".join(f"<li>{esc(x)}</li>" for x in pr["personas"])
    surfaces = "".join(f'<span class="chip">{esc(x)}</span>' for x in pr["surfaces"])
    body = f"""
{breadcrumb(1, [("Profils", "index.html"), (pr['name'], None)])}
<div class="profile-head" style="--accent:{pr['color']}">
  <div class="profile-icon">{pr['icon']}</div>
  <div>
    <p class="profile-desc">{esc(pr['desc'])}</p>
    <div class="chips">{surfaces}</div>
  </div>
</div>
<div class="two-col">
  <div class="panel">
    <h3>Comptes de démonstration</h3>
    <ul class="plain">{personas}</ul>
  </div>
  <div class="panel">
    <h3>Synthèse</h3>
    <p><strong>{len(ucs)}</strong> cas d'usage · <strong>{sum(1 for u in ucs if u['status']=='exécuté')}</strong> exécutés.</p>
    <p class="muted">Mot de passe commun des comptes de démo : <code>password123</code>.</p>
  </div>
</div>
<h2 class="section-title">Cas d'usage à tester pour ce profil</h2>
<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>ID</th><th>Cas d'usage</th><th>Module(s)</th><th>Type</th><th>Statut</th><th class="center">Note IA</th></tr></thead>
    <tbody>{''.join(rows) if rows else '<tr><td colspan="6" class="muted">Aucun cas pour ce profil.</td></tr>'}</tbody>
  </table>
</div>
<p><a class="btn-back" href="index.html">← Retour aux profils</a></p>
"""
    return layout(pr["name"], 1, body, "Profil utilisateur — cas d'usage associés.")


def page_modules_index() -> str:
    cards = []
    for m in MODULES:
        ucs = [u for u in USE_CASES if m["slug"] in u["modules"]]
        done = sum(1 for u in ucs if u["status"] == "exécuté")
        cards.append(f"""
        <a class="card module-card" href="{m['slug']}.html">
          <div class="card-icon">{m['icon']}</div>
          <h3>{esc(m['name'])}</h3>
          <p>{esc(m['desc'])}</p>
          <span class="card-meta">{len(ucs)} cas · {done} exécutés</span>
        </a>""")
    body = f"""
{breadcrumb(1, [("Modules", None)])}
<p class="lead">Sélectionnez un module pour voir les cas d'usage qui le couvrent.</p>
<div class="grid grid-modules">{''.join(cards)}</div>
"""
    return layout("Modules & fonctionnalités", 1, body, f"{len(MODULES)} modules couverts par la recette.")


def page_module(slug: str) -> str:
    m = MODULE_BY_SLUG[slug]
    ucs = [u for u in USE_CASES if slug in u["modules"]]
    rows = []
    for u in ucs:
        profs = ", ".join(PROFILE_BY_SLUG[s]["name"] for s in u["profiles"])
        rows.append(f"""
        <tr>
          <td><a class="uc-link" href="../cas/{uc_slug(u['id'])}.html">{esc(u['id'])}</a></td>
          <td><a class="uc-link" href="../cas/{uc_slug(u['id'])}.html">{esc(u['title'])}</a></td>
          <td>{esc(profs)}</td>
          <td>{type_badge(u['type'])}</td>
          <td>{status_badge(u['status'])}</td>
          <td class="center">{note_badge(u['note_ia'])}</td>
        </tr>""")
    body = f"""
{breadcrumb(1, [("Modules", "index.html"), (m['name'], None)])}
<div class="module-head">
  <div class="profile-icon">{m['icon']}</div>
  <p class="profile-desc">{esc(m['desc'])}</p>
</div>
<h2 class="section-title">Cas d'usage couvrant ce module</h2>
<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>ID</th><th>Cas d'usage</th><th>Profil(s)</th><th>Type</th><th>Statut</th><th class="center">Note IA</th></tr></thead>
    <tbody>{''.join(rows) if rows else '<tr><td colspan="6" class="muted">Aucun cas pour ce module.</td></tr>'}</tbody>
  </table>
</div>
<p><a class="btn-back" href="index.html">← Retour aux modules</a></p>
"""
    return layout(m["name"], 1, body, "Module / fonctionnalité — cas d'usage associés.")


def page_uc(uc: dict) -> str:
    profs = [PROFILE_BY_SLUG[s] for s in uc["profiles"]]
    mods = [MODULE_BY_SLUG[s] for s in uc["modules"]]
    prof_links = " · ".join(
        f'<a href="../profils/{p["slug"]}.html">{esc(p["name"])}</a>' for p in profs)
    mod_links = " · ".join(
        f'<a href="../modules/{m["slug"]}.html">{esc(m["name"])}</a>' for m in mods)
    steps = "".join(f"<li>{md_inline(s)}</li>" for s in uc["steps"])
    note_ia = uc["note_ia"] or ""
    obs_ia = uc["obs_ia"] or ""
    obs_html = md_inline(obs_ia) if obs_ia else '<span class="muted">Non exécuté — à réaliser par le testeur IA.</span>'
    hn = human_note(uc["id"])
    hn_note_html = (
        f'<span class="note-badge">{esc(hn["note"])}/5</span>' if hn["note"]
        else '<span class="muted">—</span>'
    )
    hn_obs_html = (
        md_inline(hn["obs"]) if hn["obs"]
        else '<span class="muted">Non renseignée — éditable ci-dessous.</span>'
    )
    hn_meta = ""
    if hn["auteur"] or hn["date"]:
        bits = []
        if hn["auteur"]:
            bits.append(f'par {esc(hn["auteur"])}')
        if hn["date"]:
            bits.append(f'le {esc(hn["date"][:10])}')
        hn_meta = f'<div class="human-meta muted small">{" ".join(bits)}</div>'
    body = f"""
{breadcrumb(1, [("Profils", "profils/index.html"), (profs[0]['name'], f"../profils/{profs[0]['slug']}.html"), (uc['id'], None)])}
<div class="uc-head">
  <div class="uc-id">{esc(uc['id'])}</div>
  <div class="uc-head-main">
    <h2 class="uc-title">{esc(uc['title'])}</h2>
    <div class="chips">
      {type_badge(uc['type'])}
      {status_badge(uc['status'])}
      <span class="chip">Profil : {prof_links}</span>
      <span class="chip">Module : {mod_links}</span>
    </div>
  </div>
</div>

<div class="uc-grid">
  <section class="panel">
    <h3>Description / préconditions</h3>
    <p>{md_inline(uc['desc'])}</p>
    <p class="muted"><strong>Prérequis :</strong> {md_inline(uc['pre'])}</p>
  </section>
  <section class="panel">
    <h3>Résultat attendu</h3>
    <p>{md_inline(uc['expected'])}</p>
  </section>
</div>

<section class="panel">
  <h3>Étapes de test</h3>
  <ol class="steps">{steps}</ol>
</section>

<section class="panel">
  <h3>Fiche de résultats</h3>
  <div class="table-wrap">
    <table class="result-table">
      <thead>
        <tr>
          <th class="col-note">Note IA (/5)</th>
          <th>Observation IA</th>
          <th class="col-note">Note humaine (/5)</th>
          <th>Observation humaine</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="center">{note_badge(note_ia)}</td>
          <td class="obs">{obs_html}</td>
          <td class="center human-cell" data-human-note="{esc(uc['id'])}">{hn_note_html}</td>
          <td class="human-cell" data-human-obs="{esc(uc['id'])}">{hn_obs_html}{hn_meta}</td>
        </tr>
      </tbody>
    </table>
  </div>
</section>

<section class="panel eval-panel" data-uc="{esc(uc['id'])}"
         data-repo="{GITHUB_REPO}" data-branch="{GITHUB_BRANCH}"
         data-path="{GITHUB_NOTES_PATH}" data-notes-url="../{NOTES_FILENAME}">
  <h3>Votre évaluation humaine</h3>
  <p class="muted">Les notes saisies ici sont <strong>enregistrées dans le dépôt</strong>
  (<code>{GITHUB_NOTES_PATH}</code>) via l'API GitHub : elles sont donc versionnées et visibles par tous.
  Un jeton personnel GitHub est requis ; il reste stocké <strong>uniquement dans votre navigateur</strong>.</p>

  <details class="token-box">
    <summary>Configurer mon jeton GitHub</summary>
    <div class="token-body">
      <p class="muted small">Créez un token sur
        <a href="https://github.com/settings/tokens" target="_blank" rel="noopener">github.com/settings/tokens</a>
        avec le scope <code>repo</code> (token classique) ou la permission
        <code>Contents: Read and write</code> (token fin). Il n'est envoyé qu'à
        <code>api.github.com</code> et n'est jamais écrit dans le dépôt.</p>
      <label>Jeton GitHub
        <input type="password" class="gh-token" autocomplete="off" spellcheck="false"
               placeholder="ghp_… ou github_pat_…">
      </label>
      <label>Votre nom (affiché comme auteur)
        <input type="text" class="gh-author" autocomplete="off" placeholder="ex. Amina T.">
      </label>
      <div class="eval-actions">
        <button class="btn btn-primary token-save" type="button">Enregistrer le jeton</button>
        <button class="btn btn-ghost token-clear" type="button">Effacer le jeton</button>
        <span class="token-status muted"></span>
      </div>
    </div>
  </details>

  <div class="eval-form">
    <label>Note humaine (/5)
      <select class="eval-note">
        <option value="">—</option>
        <option>0</option><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option>
      </select>
    </label>
    <label class="eval-obs-label">Observation humaine
      <textarea class="eval-obs" rows="3" placeholder="Décrivez factuellement ce que vous avez observé…"></textarea>
    </label>
    <div class="eval-actions">
      <button class="btn btn-primary eval-save" type="button">Enregistrer dans le dépôt</button>
      <a class="btn btn-ghost" href="{GITHUB_EDIT_URL}" target="_blank" rel="noopener">Éditer sur GitHub</a>
      <span class="eval-status muted"></span>
    </div>
    <p class="eval-meta muted small"></p>
  </div>
</section>

<p class="uc-nav">
  <a class="btn-back" href="../index.html">← Portail</a>
  <a class="btn-back" href="../profils/{profs[0]['slug']}.html">← Profil {esc(profs[0]['name'])}</a>
  <a class="btn-back" href="../modules/{mods[0]['slug']}.html">← Module {esc(mods[0]['name'])}</a>
</p>
"""
    return layout(f"{uc['id']} — {uc['title']}", 1, body, "Fiche de résultats du cas d'usage.")


def recap_human(uc_id: str) -> str:
    """Cellule « Note humaine » du récapitulatif (rendu serveur)."""
    hn = human_note(uc_id)
    if hn["note"]:
        return f'<span class="note-badge">{esc(hn["note"])}/5</span>'
    return '<span class="muted">—</span>'


def page_recap() -> str:
    rows = []
    notes = []
    for u in USE_CASES:
        n = u["note_ia"]
        if n:
            notes.append(int(n))
        rows.append(f"""
        <tr>
          <td><a class="uc-link" href="cas/{uc_slug(u['id'])}.html">{esc(u['id'])}</a></td>
          <td><a class="uc-link" href="cas/{uc_slug(u['id'])}.html">{esc(u['title'])}</a></td>
          <td>{esc(u['type'])}</td>
          <td>{status_badge(u['status'])}</td>
          <td class="center">{note_badge(n)}</td>
          <td class="center human-cell" data-human-note="{esc(u['id'])}">{recap_human(u['id'])}</td>
        </tr>""")
    total = sum(notes)
    avg = f"{total/len(notes):.2f}" if notes else "—"
    bloquants = sum(1 for n in notes if n <= 2)
    body = f"""
{breadcrumb(0, [("Récapitulatif", None)])}
<div class="stats stats-recap">
  <div class="stat"><span class="stat-num">{len(notes)}</span><span class="stat-label">cas notés (IA)</span></div>
  <div class="stat"><span class="stat-num">{total}</span><span class="stat-label">total des notes IA</span></div>
  <div class="stat"><span class="stat-num">{avg}</span><span class="stat-label">moyenne IA /5</span></div>
  <div class="stat"><span class="stat-num">{bloquants}</span><span class="stat-label">cas bloquants (≤2)</span></div>
</div>
<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>ID</th><th>Cas d'usage</th><th>Type</th><th>Statut</th><th class="center">Note IA (/5)</th><th class="center">Note humaine (/5)</th></tr></thead>
    <tbody>{''.join(rows)}</tbody>
  </table>
</div>
<p class="muted">La colonne « Note humaine (/5) » est renseignée par les testeurs humains depuis chaque fiche de cas
(ou directement dans <a href="notes-humaines.json">notes-humaines.json</a>) ; elle est versionnée dans le dépôt.</p>
"""
    return layout("Tableau récapitulatif", 0, body,
                  "Moyenne des notes IA, total et cas bloquants.")


# ---------------------------------------------------------------------------
# MARKDOWN
# ---------------------------------------------------------------------------

def build_markdown() -> str:
    lines = []
    lines.append("# Cahier de recette — Leopardo (leopardo-hr)\n")
    lines.append("**Dépôt testé :** [github.com/Kitokoh/leopardo-hr](https://github.com/Kitokoh/leopardo-hr)  ")
    lines.append("**Portail web :** [kitokoh.github.io/leopardo-hr/recette/](https://kitokoh.github.io/leopardo-hr/recette/)  ")
    lines.append("**Nature :** monorepo « Business OS » pour entreprises de terrain — HR & paie, pointage, absences, comptabilité, CRM, solutions verticales, mobile Flutter, kiosque biométrique, API ouverte.  ")
    lines.append("**Destinataires :** testeurs IA (colonnes « Note IA » / « Observation IA ») puis relecteur humain (colonnes « Note humaine » / « Observation humaine »).\n")
    lines.append("> Les colonnes **Note IA (/5)** et **Observation IA** sont remplies à partir d'une exécution réelle.  ")
    lines.append("> Les colonnes **Note humaine (/5)** et **Observation humaine** sont **volontairement vides** et réservées à l'évaluation humaine.\n")
    lines.append("---\n")
    lines.append("## Barème de notation\n")
    lines.append("| Note | Signification |")
    lines.append("| :---: | :--- |")
    lines.append("| **5** | Cas entièrement conforme, aucune anomalie |")
    lines.append("| **4** | Conforme, défauts cosmétiques ou mineurs |")
    lines.append("| **3** | Fonctionne partiellement : contournement nécessaire ou critère non vérifiable |")
    lines.append("| **2** | Défaillance fonctionnelle réelle sur un critère majeur |")
    lines.append("| **1** | Cas bloqué (erreur serveur, accès impossible) mais comportement compréhensible |")
    lines.append("| **0** | Cas impossible à exécuter (prérequis absents, environnement indisponible) |\n")
    lines.append("---\n")
    for u in USE_CASES:
        profs = ", ".join(PROFILE_BY_SLUG[s]["name"] for s in u["profiles"])
        mods = ", ".join(MODULE_BY_SLUG[s]["name"] for s in u["modules"])
        lines.append(f"## {u['id']} — {u['title']}\n")
        lines.append(f"**Profil(s) :** {profs} · **Module(s) :** {mods} · **Type :** {u['type']} · **Statut :** {u['status']}\n")
        lines.append("**Description / préconditions**  ")
        lines.append(f"{u['desc']}  ")
        lines.append(f"Prérequis : {u['pre']}\n")
        lines.append("**Étapes de test**")
        for i, s in enumerate(u["steps"], 1):
            lines.append(f"{i}. {s}")
        lines.append("")
        lines.append("**Résultat attendu**  ")
        lines.append(f"{u['expected']}\n")
        lines.append("| Note IA (/5) | Observation IA | Note humaine (/5) | Observation humaine |")
        lines.append("| :---: | :--- | :---: | :--- |")
        obs = u["obs_ia"].replace("|", "\\|") if u["obs_ia"] else "Non exécuté — à réaliser par le testeur IA."
        lines.append(f"| {u['note_ia'] or ''} | {obs} |  |  |\n")
        lines.append("---\n")
    # recap
    notes = [int(u["note_ia"]) for u in USE_CASES if u["note_ia"]]
    total = sum(notes)
    avg = f"{total/len(notes):.2f}" if notes else "—"
    bloquants = sum(1 for n in notes if n <= 2)
    lines.append("## Tableau récapitulatif\n")
    lines.append("| ID | Cas d'utilisation | Type | Statut | Note IA (/5) | Note humaine (/5) |")
    lines.append("| :--- | :--- | :--- | :--- | :---: | :---: |")
    for u in USE_CASES:
        hn = human_note(u['id'])
        lines.append(f"| {u['id']} | {u['title']} | {u['type']} | {u['status']} | {u['note_ia'] or ''} | {hn['note']} |")
    lines.append("")
    lines.append(f"**Cas notés (IA) :** {len(notes)} · **Total des notes IA :** {total} · "
                 f"**Moyenne IA :** {avg} / 5 · **Cas bloquants (≤2) :** {bloquants}\n")
    lines.append("> La colonne « Note humaine (/5) » est renseignée par les testeurs humains depuis le portail\n"
                 "> (ou directement dans `docs/recette/notes-humaines.json`) ; elle est versionnée dans le dépôt.\n")
    return "\n".join(lines)


PROMPT_MD = """# Prompt — Agent IA testeur (leopardo-hr)

Tu es un testeur QA automatisé. Ta mission est d'exécuter le cahier de recette
de la solution Leopardo (dépôt Kitokoh/leopardo-hr) et de remplir uniquement les
colonnes qui te sont réservées, sans jamais toucher aux colonnes destinées à l'humain.

## Ton périmètre d'action

1. Parcours le portail de recette (https://kitokoh.github.io/leopardo-hr/recette/)
   ou le fichier `docs/recette/tests-ia-leopardo-hr.md`. Pour chaque cas d'usage :
   - lis la description / préconditions ;
   - suis les étapes de test dans l'ordre ;
   - exécute réellement ce qui est exécutable (appels API, navigation web,
     commandes locales, tests automatisés) ;
   - compare le comportement observé au résultat attendu.

2. Attribue une note sur 5 à chaque cas, selon le barème du document :
   - 5 = entièrement conforme, aucune anomalie ;
   - 4 = conforme, défauts cosmétiques ou mineurs ;
   - 3 = fonctionne partiellement (contournement nécessaire ou critère non vérifiable) ;
   - 2 = défaillance fonctionnelle sur un critère majeur ;
   - 1 = bloqué (erreur serveur / accès impossible) mais comportement compréhensible ;
   - 0 = impossible à exécuter (prérequis absents).

3. Écris une observation factuelle dans la colonne « Observation IA » :
   - ce que tu as réellement fait (commande, URL, compte utilisé) ;
   - le résultat observé (code HTTP, message, capture, sortie de test) ;
   - tout écart par rapport au résultat attendu ;
   - uniquement des faits vérifiés, pas d'opinion ni de recommandation.

4. Remplis exclusivement les colonnes « Note IA (/5) » et « Observation IA ».
   Les colonnes « Note humaine (/5) » et « Observation humaine » doivent rester
   strictement vides — ne les remplis jamais, n'y écris rien, même pas un tiret
   ou un « à compléter ».

5. Mets à jour le tableau récapitulatif en fin de document : reporte chaque note
   IA par cas, puis calcule et inscris :
   - la moyenne des notes IA (somme des notes ÷ nombre de cas exécutés) ;
   - le total (somme des notes IA) ;
   - le nombre de cas bloquants (note ≤ 2).

## Règles strictes

- Ne modifie jamais les titres, la structure, les étapes de test, ni le barème du
  document. Tu ne fais que remplir les cellules qui t'appartiennent.
- Ne remplis jamais « Note humaine » ni « Observation humaine ».
- Si un cas est non exécutable (prérequis manquants, environnement indisponible,
  compte invalide), attribue 0 et explique précisément, dans l'observation,
  pourquoi tu n'as pas pu l'exécuter (ne devine pas un résultat).
- Si l'API renvoie 429 TOO_MANY_REQUESTS, attends puis réessaie une fois avant de
  conclure ; note la limitation dans l'observation.
- Termine ton travail en livrant : (a) le fichier Markdown complété, et (b) un
  court résumé listant — par identifiant de cas — la note attribuée et une phrase
  d'observation. N'invente aucun résultat : tout doit provenir d'une exécution ou
  d'une vérification réelle.
"""


# ---------------------------------------------------------------------------
# CSS / JS
# ---------------------------------------------------------------------------

CSS = r"""
:root{
  --bg:#f6f7fb; --surface:#ffffff; --ink:#0f172a; --muted:#64748b;
  --line:#e2e8f0; --brand:#0f766e; --brand-2:#115e59; --accent:#0f766e;
  --radius:14px; --shadow:0 1px 2px rgba(15,23,42,.06),0 8px 24px rgba(15,23,42,.06);
  --max:1180px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
  color:var(--ink);background:var(--bg);line-height:1.6;-webkit-font-smoothing:antialiased}
a{color:var(--brand);text-decoration:none}
a:hover{text-decoration:underline}
code{background:#eef2f7;padding:.1em .35em;border-radius:5px;font-size:.9em}
.wrap{max-width:var(--max);margin:0 auto;padding:0 20px}
/* topbar */
.topbar{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);
  border-bottom:1px solid var(--line)}
.topbar-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;height:64px}
.brand{display:flex;align-items:center;gap:10px;color:var(--ink);font-weight:700}
.brand:hover{text-decoration:none}
.brand-mark{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;
  background:linear-gradient(135deg,var(--brand),#0ea5a4);color:#fff;font-weight:800}
.brand-text{font-size:15px;line-height:1.1}
.brand-text small{display:block;font-weight:500;color:var(--muted);font-size:11px}
.mainnav{display:flex;gap:4px;flex-wrap:wrap}
.mainnav a{padding:8px 12px;border-radius:9px;color:#334155;font-size:14px;font-weight:500}
.mainnav a:hover{background:#eef2f7;text-decoration:none}
/* page */
main{padding:28px 20px 60px}
.page-title{font-size:30px;line-height:1.2;margin:6px 0 4px;letter-spacing:-.02em}
.page-sub{color:var(--muted);margin:0 0 22px}
.lead{color:#334155;font-size:16px;margin:0 0 20px}
.muted{color:var(--muted)}
/* breadcrumb */
.breadcrumb{font-size:13px;color:var(--muted);margin-bottom:14px;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.breadcrumb .sep{color:#cbd5e1}
.breadcrumb span[aria-current]{color:var(--ink);font-weight:600}
/* hero */
.hero{background:linear-gradient(135deg,#0f766e,#0e7490 55%,#1d4ed8);color:#fff;border-radius:20px;
  padding:38px 34px;box-shadow:var(--shadow);margin-bottom:34px}
.hero-kicker{text-transform:uppercase;letter-spacing:.14em;font-size:12px;opacity:.85;margin:0 0 8px}
.hero-title{font-size:34px;line-height:1.15;margin:0 0 12px;letter-spacing:-.02em}
.hero-lead{max-width:760px;opacity:.95;margin:0 0 22px;font-size:16px}
.stats{display:flex;flex-wrap:wrap;gap:14px}
.stat{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);border-radius:12px;
  padding:12px 18px;min-width:110px}
.stat-num{display:block;font-size:24px;font-weight:800;line-height:1.1}
.stat-label{font-size:12px;opacity:.9}
.stats-recap .stat{background:#fff;border:1px solid var(--line);color:var(--ink)}
.stats-recap .stat-num{color:var(--brand)}
/* sections */
.section{margin-bottom:40px}
.section-title{font-size:21px;margin:0 0 4px;letter-spacing:-.01em}
.section-sub{color:var(--muted);margin:0 0 18px;font-size:14px}
/* grid + cards */
.grid{display:grid;gap:16px}
.grid-profiles{grid-template-columns:repeat(auto-fill,minmax(250px,1fr))}
.grid-modules{grid-template-columns:repeat(auto-fill,minmax(250px,1fr))}
.grid-types{grid-template-columns:repeat(auto-fill,minmax(180px,1fr))}
.card{display:block;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);
  padding:18px;box-shadow:var(--shadow);color:var(--ink);transition:transform .15s ease,box-shadow .15s ease,border-color .15s}
.card:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(15,23,42,.10);border-color:#cbd5e1;text-decoration:none}
.card-icon{font-size:24px;margin-bottom:8px}
.card h3{margin:0 0 6px;font-size:16px}
.card p{margin:0 0 10px;color:var(--muted);font-size:13.5px}
.card-meta{font-size:12px;font-weight:600;color:var(--accent)}
.profile-card{border-top:3px solid var(--accent,var(--brand))}
.type-card{text-align:center}
.type-card .card-icon{color:var(--accent)}
/* chips + badges */
.chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.chip{display:inline-flex;align-items:center;gap:6px;background:#f1f5f9;border:1px solid var(--line);
  border-radius:999px;padding:4px 11px;font-size:12.5px;color:#334155}
.badge{display:inline-flex;align-items:center;gap:5px;border:1px solid transparent;border-radius:999px;
  padding:3px 10px;font-size:12px;font-weight:600}
.badge-done{background:#dcfce7;color:#166534;border-color:#bbf7d0}
.badge-todo{background:#fef3c7;color:#92400e;border-color:#fde68a}
/* tables */
.table-wrap{overflow-x:auto;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);
  box-shadow:var(--shadow)}
.data-table,.result-table{width:100%;border-collapse:collapse;font-size:14px}
.data-table th,.data-table td,.result-table th,.result-table td{padding:11px 13px;text-align:left;
  border-bottom:1px solid var(--line);vertical-align:top}
.data-table thead th,.result-table thead th{background:#f8fafc;font-size:12px;text-transform:uppercase;
  letter-spacing:.05em;color:#475569;font-weight:700;position:sticky;top:0}
.data-table tbody tr:hover{background:#f8fafc}
.data-table tbody tr:last-child td,.result-table tbody tr:last-child td{border-bottom:none}
.center{text-align:center}
.uc-link{font-weight:600}
/* notes */
.note{display:inline-block;min-width:44px;text-align:center;border-radius:8px;padding:2px 8px;font-weight:700;font-size:13px}
.note-good{background:#dcfce7;color:#166534}
.note-mid{background:#fef3c7;color:#92400e}
.note-bad{background:#fee2e2;color:#991b1b}
.note-empty{background:#f1f5f9;color:#94a3b8}
/* toolbar */
.toolbar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px}
.search,.select{padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:#fff;
  font-size:14px;color:var(--ink);outline:none}
.search{flex:1;min-width:220px}
.search:focus,.select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(15,118,110,.12)}
.empty-hint{color:var(--muted);text-align:center;padding:18px}
/* profile / module head */
.profile-head,.module-head{display:flex;gap:18px;align-items:flex-start;background:var(--surface);
  border:1px solid var(--line);border-left:4px solid var(--accent,var(--brand));border-radius:var(--radius);
  padding:20px;box-shadow:var(--shadow);margin-bottom:22px}
.profile-icon{font-size:34px;line-height:1}
.profile-desc{margin:0 0 8px;color:#334155}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:26px}
.panel{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:18px 20px;
  box-shadow:var(--shadow);margin-bottom:18px}
.panel h3{margin:0 0 10px;font-size:16px}
.plain{margin:0;padding-left:18px}
.plain li{margin:4px 0}
/* uc head */
.uc-head{display:flex;gap:16px;align-items:flex-start;margin-bottom:20px}
.uc-id{background:var(--brand);color:#fff;font-weight:800;border-radius:12px;padding:10px 14px;font-size:15px;
  white-space:nowrap}
.uc-title{margin:0 0 6px;font-size:24px;letter-spacing:-.01em}
.uc-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.steps{margin:0;padding-left:22px}
.steps li{margin:7px 0}
.result-table .col-note{width:120px}
.result-table .obs{min-width:280px}
.human-cell{background:#fbfcfe}
.human-meta{margin-top:6px;font-size:12px}
.small{font-size:12px}
.note-badge{display:inline-block;min-width:38px;padding:3px 9px;border-radius:999px;
  background:#eef2ff;color:#3730a3;font-weight:700;font-size:13px}
/* jeton GitHub + statuts d'enregistrement */
.token-box{margin:14px 0 18px;border:1px solid var(--line);border-radius:12px;background:#f8fafc}
.token-box>summary{cursor:pointer;padding:11px 14px;font-weight:600;font-size:14px;color:#334155}
.token-body{padding:0 14px 14px;display:grid;gap:10px}
.token-body label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600;color:#334155}
.gh-token,.gh-author{max-width:420px;padding:8px 10px;border:1px solid var(--line);border-radius:9px;font-size:14px}
.eval-status.ok{color:#047857;font-weight:600}
.eval-status.err{color:#b91c1c;font-weight:600}
.eval-status.warn{color:#b45309;font-weight:600}
.eval-meta{margin:0}
.eval-save[disabled]{opacity:.6;cursor:progress}
/* eval */
.eval-panel{border-left:4px solid var(--brand)}
.eval-form{display:grid;gap:12px}
.eval-form label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600;color:#334155}
.eval-note{max-width:120px;padding:8px 10px;border:1px solid var(--line);border-radius:9px;font-size:14px}
.eval-obs{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:10px;font-size:14px;
  font-family:inherit;resize:vertical}
.eval-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.btn{border:1px solid transparent;border-radius:10px;padding:9px 16px;font-size:14px;font-weight:600;cursor:pointer}
.btn-primary{background:var(--brand);color:#fff}
.btn-primary:hover{background:var(--brand-2)}
.btn-ghost{background:#fff;border-color:var(--line);color:#334155}
.btn-ghost:hover{background:#f8fafc}
.eval-status{font-size:13px}
/* nav back */
.uc-nav{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.btn-back{display:inline-block;background:#fff;border:1px solid var(--line);border-radius:10px;
  padding:8px 14px;font-size:13.5px;color:#334155}
.btn-back:hover{background:#f8fafc;text-decoration:none}
/* footer */
.footer{border-top:1px solid var(--line);background:#fff;padding:22px 0;margin-top:20px}
.footer p{margin:0;color:var(--muted);font-size:13px}
/* responsive */
@media (max-width:820px){
  .two-col,.uc-grid{grid-template-columns:1fr}
  .hero{padding:28px 22px}
  .hero-title{font-size:26px}
  .page-title{font-size:24px}
  .topbar-inner{height:auto;padding:10px 0;flex-direction:column;align-items:flex-start}
  .mainnav{width:100%}
}
@media print{
  .topbar,.footer,.eval-panel,.uc-nav,.toolbar{display:none}
  body{background:#fff}
  .card,.panel,.table-wrap{box-shadow:none}
}
"""

JS = r"""
(function(){
  // --- Filtre du tableau des cas d'usage (accueil) ---
  var search = document.getElementById('uc-search');
  var typeSel = document.getElementById('uc-type');
  var statusSel = document.getElementById('uc-status');
  var table = document.getElementById('uc-table');
  var empty = document.getElementById('uc-empty');
  function applyFilter(){
    if(!table) return;
    var q = (search && search.value || '').toLowerCase().trim();
    var t = typeSel && typeSel.value || '';
    var s = statusSel && statusSel.value || '';
    var rows = table.querySelectorAll('tbody tr');
    var visible = 0;
    rows.forEach(function(tr){
      var text = tr.textContent.toLowerCase();
      var ok = (!q || text.indexOf(q) !== -1)
            && (!t || tr.getAttribute('data-type') === t)
            && (!s || tr.getAttribute('data-status') === s);
      tr.style.display = ok ? '' : 'none';
      if(ok) visible++;
    });
    if(empty) empty.hidden = visible !== 0;
  }
  if(search) search.addEventListener('input', applyFilter);
  if(typeSel) typeSel.addEventListener('change', applyFilter);
  if(statusSel) statusSel.addEventListener('change', applyFilter);

  // --- Cartes "type" : cliquer filtre le tableau ---
  document.querySelectorAll('[data-filter-type]').forEach(function(card){
    card.addEventListener('click', function(e){
      e.preventDefault();
      if(typeSel){ typeSel.value = card.getAttribute('data-filter-type'); applyFilter(); }
      var target = document.getElementById('tous');
      if(target) target.scrollIntoView({behavior:'smooth'});
    });
  });

  // =========================================================================
  // NOTES HUMAINES — lecture du fichier versionné + écriture via l'API GitHub
  // =========================================================================
  // Le jeton personnel est saisi par l'utilisateur et conservé UNIQUEMENT dans
  // son navigateur (localStorage). Il n'est jamais écrit dans le dépôt ni dans
  // le code, et n'est envoyé qu'à api.github.com.
  var TOKEN_KEY = 'lhr-recette-gh-token';
  var AUTHOR_KEY = 'lhr-recette-gh-author';
  var NOTES_URL = document.body.getAttribute('data-notes-url') || 'notes-humaines.json';
  var notesCache = null;

  function b64encode(str){
    var bytes = new TextEncoder().encode(str);
    var bin = '';
    for(var i=0;i<bytes.length;i++) bin += String.fromCharCode(bytes[i]);
    return btoa(bin);
  }
  function b64decode(b64){
    var bin = atob(String(b64).replace(/\s/g,''));
    var bytes = new Uint8Array(bin.length);
    for(var i=0;i<bin.length;i++) bytes[i] = bin.charCodeAt(i);
    return new TextDecoder().decode(bytes);
  }
  function escHtml(s){
    return String(s).replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }
  function fetchNotes(){
    return fetch(NOTES_URL + '?t=' + Date.now(), {cache:'no-store'})
      .then(function(r){ if(!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function(j){ notesCache = j; return j; });
  }
  function paintHumanCells(notes){
    document.querySelectorAll('[data-human-note]').forEach(function(td){
      var n = (notes && notes[td.getAttribute('data-human-note')]) || {};
      var note = n.note_humaine;
      td.innerHTML = (note === null || note === undefined || note === '')
        ? '<span class="muted">—</span>'
        : '<span class="note-badge">' + escHtml(note) + '/5</span>';
    });
    document.querySelectorAll('[data-human-obs]').forEach(function(td){
      var n = (notes && notes[td.getAttribute('data-human-obs')]) || {};
      var obs = n.observation_humaine || '';
      var meta = '';
      if(n.auteur || n.date_modification){
        var bits = [];
        if(n.auteur) bits.push('par ' + escHtml(n.auteur));
        if(n.date_modification) bits.push('le ' + escHtml(String(n.date_modification).slice(0,10)));
        meta = '<div class="human-meta muted small">' + bits.join(' ') + '</div>';
      }
      td.innerHTML = (obs ? escHtml(obs)
        : '<span class="muted">Non renseignée — éditable ci-dessous.</span>') + meta;
    });
  }

  // Rafraîchit les cellules humaines sur TOUTES les pages (fiches + récapitulatif).
  // En cas d'échec (hors-ligne), le rendu serveur généré par build_portal.py reste affiché.
  fetchNotes().then(function(j){ paintHumanCells(j.notes || {}); }).catch(function(){});

  var panel = document.querySelector('.eval-panel');
  if(!panel) return;

  var uc = panel.getAttribute('data-uc');
  var REPO = panel.getAttribute('data-repo');
  var BRANCH = panel.getAttribute('data-branch');
  var PATH = panel.getAttribute('data-path');
  var noteEl = panel.querySelector('.eval-note');
  var obsEl = panel.querySelector('.eval-obs');
  var statusEl = panel.querySelector('.eval-status');
  var metaEl = panel.querySelector('.eval-meta');
  var saveBtn = panel.querySelector('.eval-save');
  var tokenEl = panel.querySelector('.gh-token');
  var authorEl = panel.querySelector('.gh-author');
  var tokenSave = panel.querySelector('.token-save');
  var tokenClear = panel.querySelector('.token-clear');
  var tokenStatus = panel.querySelector('.token-status');

  function getToken(){ try{ return localStorage.getItem(TOKEN_KEY) || ''; }catch(e){ return ''; } }
  function getAuthor(){ try{ return localStorage.getItem(AUTHOR_KEY) || ''; }catch(e){ return ''; } }
  function setStatus(msg, kind){
    if(!statusEl) return;
    statusEl.textContent = msg;
    statusEl.className = 'eval-status ' + (kind || 'muted');
  }

  function fillForm(notes){
    var n = (notes && notes[uc]) || {};
    if(n.note_humaine !== null && n.note_humaine !== undefined) noteEl.value = String(n.note_humaine);
    if(n.observation_humaine) obsEl.value = n.observation_humaine;
    if(metaEl){
      if(n.auteur || n.date_modification){
        var bits = [];
        if(n.auteur) bits.push('Dernière saisie par ' + n.auteur);
        if(n.date_modification) bits.push('le ' + String(n.date_modification).slice(0,10));
        metaEl.textContent = bits.join(' ');
      }else{
        metaEl.textContent = 'Aucune note humaine enregistrée pour ce cas.';
      }
    }
  }

  // --- Interface du jeton (stocké uniquement dans ce navigateur) ---
  if(tokenEl) tokenEl.value = getToken();
  if(authorEl) authorEl.value = getAuthor();
  if(tokenStatus) tokenStatus.textContent = getToken()
    ? 'Jeton configuré dans ce navigateur.'
    : 'Aucun jeton configuré.';
  if(tokenSave) tokenSave.addEventListener('click', function(){
    var t = (tokenEl.value || '').trim();
    if(!t){ if(tokenStatus) tokenStatus.textContent = 'Jeton vide — rien n\'a été enregistré.'; return; }
    try{
      localStorage.setItem(TOKEN_KEY, t);
      localStorage.setItem(AUTHOR_KEY, (authorEl.value || '').trim());
      if(tokenStatus) tokenStatus.textContent = 'Jeton enregistré dans ce navigateur ✔';
    }catch(e){
      if(tokenStatus) tokenStatus.textContent = 'Stockage indisponible (navigation privée ?).';
    }
  });
  if(tokenClear) tokenClear.addEventListener('click', function(){
    try{ localStorage.removeItem(TOKEN_KEY); localStorage.removeItem(AUTHOR_KEY); }catch(e){}
    if(tokenEl) tokenEl.value = '';
    if(authorEl) authorEl.value = '';
    if(tokenStatus) tokenStatus.textContent = 'Jeton effacé de ce navigateur.';
  });

  // --- API GitHub (contents) ---
  function ghHeaders(token){
    return {
      'Authorization': 'Bearer ' + token,
      'Accept': 'application/vnd.github+json',
      'X-GitHub-Api-Version': '2022-11-28'
    };
  }
  function ghGet(){
    return fetch('https://api.github.com/repos/' + REPO + '/contents/' + PATH + '?ref=' + BRANCH,
      {headers: ghHeaders(getToken()), cache: 'no-store'})
      .then(function(r){
        if(r.status === 401) throw {code:'AUTH', msg:'Jeton invalide ou expiré (401). Vérifiez votre jeton GitHub.'};
        if(r.status === 403) throw {code:'FORBIDDEN', msg:'Accès refusé (403) : le jeton n\'a pas le scope « repo » (ou « Contents: write »).'};
        if(r.status === 404) throw {code:'NOTFOUND', msg:'Fichier de notes introuvable (404) sur la branche ' + BRANCH + '.'};
        if(!r.ok) throw {code:'HTTP', msg:'Erreur GitHub ' + r.status + ' lors de la lecture du fichier.'};
        return r.json();
      });
  }
  function ghPut(contentStr, sha, message){
    return fetch('https://api.github.com/repos/' + REPO + '/contents/' + PATH, {
      method: 'PUT',
      headers: Object.assign({'Content-Type':'application/json'}, ghHeaders(getToken())),
      body: JSON.stringify({message: message, content: b64encode(contentStr), sha: sha, branch: BRANCH})
    }).then(function(r){
      if(r.status === 409) throw {code:'CONFLICT', msg:'Conflit de version (409) : le fichier a changé entre-temps.'};
      if(r.status === 401) throw {code:'AUTH', msg:'Jeton invalide ou expiré (401).'};
      if(r.status === 403) throw {code:'FORBIDDEN', msg:'Accès refusé (403) : scope « repo » / « Contents: write » requis.'};
      if(r.status === 422) throw {code:'VALIDATION', msg:'GitHub a refusé la mise à jour (422) — vérifiez le jeton et la branche.'};
      if(!r.ok) throw {code:'HTTP', msg:'Erreur GitHub ' + r.status + ' lors de l\'enregistrement.'};
      return r.json();
    });
  }

  // --- Enregistrement d'une note humaine dans le dépôt ---
  function saveNote(attempt){
    attempt = attempt || 0;
    if(!getToken()){
      setStatus('Aucun jeton GitHub configuré — ouvrez « Configurer mon jeton GitHub » ci-dessus.', 'warn');
      return;
    }
    if(!navigator.onLine){
      setStatus('Hors ligne : impossible d\'enregistrer dans le dépôt. Réessayez une fois reconnecté.', 'warn');
      return;
    }
    var noteVal = noteEl.value === '' ? null : parseInt(noteEl.value, 10);
    var obsVal = (obsEl.value || '').trim();
    var author = getAuthor() || 'testeur humain';
    setStatus('Enregistrement en cours…', 'muted');
    if(saveBtn) saveBtn.disabled = true;

    ghGet().then(function(file){
      var current;
      try{ current = JSON.parse(b64decode(file.content)); }catch(e){ current = {notes:{}}; }
      if(!current.notes) current.notes = {};
      if(!current.notes[uc]) current.notes[uc] = {};
      current.notes[uc].note_humaine = noteVal;
      current.notes[uc].observation_humaine = obsVal;
      current.notes[uc].auteur = author;
      current.notes[uc].date_modification = new Date().toISOString();
      if(current._meta) current._meta.updated_at = new Date().toISOString();
      var msg = 'docs(recette): note humaine ' + uc + ' (' +
                (noteVal === null ? '—' : noteVal + '/5') + ') par ' + author;
      return ghPut(JSON.stringify(current, null, 2) + '\n', file.sha, msg).then(function(){
        notesCache = current;
        paintHumanCells(current.notes);
        fillForm(current.notes);
        setStatus('Enregistré dans le dépôt ✔ (commit sur ' + BRANCH + ')', 'ok');
      });
    }).catch(function(err){
      if(err && err.code === 'CONFLICT' && attempt < 1){
        setStatus('Conflit détecté — relecture du fichier et nouvelle tentative…', 'warn');
        return saveNote(attempt + 1);
      }
      setStatus((err && err.msg) ? err.msg
        : 'Échec de l\'enregistrement (réseau ou API GitHub indisponible).', 'err');
    }).then(function(){ if(saveBtn) saveBtn.disabled = false; });
  }

  if(saveBtn) saveBtn.addEventListener('click', function(){ saveNote(0); });
  fetchNotes().then(function(j){ fillForm(j.notes || {}); }).catch(function(){ fillForm(null); });
})();
"""


# ---------------------------------------------------------------------------
# MAIN
# ---------------------------------------------------------------------------

def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--out", default=None, help="Dossier de sortie (défaut : dossier du script)")
    args = ap.parse_args()
    out = Path(args.out) if args.out else Path(__file__).resolve().parent
    out.mkdir(parents=True, exist_ok=True)
    (out / "profils").mkdir(exist_ok=True)
    (out / "modules").mkdir(exist_ok=True)
    (out / "cas").mkdir(exist_ok=True)
    (out / "assets").mkdir(exist_ok=True)

    (out / "index.html").write_text(page_home(), encoding="utf-8")
    (out / "recap.html").write_text(page_recap(), encoding="utf-8")
    (out / "profils" / "index.html").write_text(page_profiles_index(), encoding="utf-8")
    (out / "modules" / "index.html").write_text(page_modules_index(), encoding="utf-8")
    for pr in PROFILES:
        (out / "profils" / f"{pr['slug']}.html").write_text(page_profile(pr["slug"]), encoding="utf-8")
    for m in MODULES:
        (out / "modules" / f"{m['slug']}.html").write_text(page_module(m["slug"]), encoding="utf-8")
    for u in USE_CASES:
        (out / "cas" / f"{uc_slug(u['id'])}.html").write_text(page_uc(u), encoding="utf-8")
    (out / "assets" / "portal.css").write_text(CSS, encoding="utf-8")
    (out / "assets" / "portal.js").write_text(JS, encoding="utf-8")
    (out / "tests-ia-leopardo-hr.md").write_text(build_markdown(), encoding="utf-8")
    (out / "prompt-agent-testeur.md").write_text(PROMPT_MD, encoding="utf-8")

    # Le fichier de notes humaines est la source de vérité versionnée : on ne
    # l'écrase JAMAIS. S'il n'existe pas encore dans le dossier de sortie, on
    # le crée vide (tous les cas à null) ; sinon on le recopie tel quel.
    dest_notes = out / NOTES_FILENAME
    if dest_notes.resolve() != notes_file().resolve():
        if notes_file().exists():
            shutil.copyfile(notes_file(), dest_notes)
        elif not dest_notes.exists():
            empty = {
                "_meta": {
                    "description": "Notes humaines du portail de recette leopardo-hr.",
                    "schema_version": 1,
                    "repo": GITHUB_REPO,
                    "path": GITHUB_NOTES_PATH,
                    "updated_at": "",
                },
                "notes": {
                    u["id"]: {"note_humaine": None, "observation_humaine": "",
                              "auteur": "", "date_modification": ""}
                    for u in USE_CASES
                },
            }
            dest_notes.write_text(json.dumps(empty, ensure_ascii=False, indent=2) + "\n",
                                  encoding="utf-8")

    n_pages = 2 + 2 + len(PROFILES) + len(MODULES) + len(USE_CASES)
    print(f"Portail généré dans {out} — {n_pages} pages HTML, {len(USE_CASES)} cas d'usage, "
          f"{len(PROFILES)} profils, {len(MODULES)} modules.")


if __name__ == "__main__":
    main()
