# 🧭 Onboarding pilote < 30 min — Checklist opérationnelle (issue #5151)

**Version** : 1.0 · **Date** : 2026-08-20 · **Objectif CDC** : « configuration entreprise + premiers employés < 30 minutes ».
**Cible** : 2 des 3 pilotes DZ doivent réaliser le parcours **seuls** en < 30 min (critère du gate pilotes).

---

## 0. Prérequis (à valider AVANT la session — fait par l'équipe, pas le pilote)

- [ ] Compte pilote créé en amont (invitation envoyée, ligne employé existante) — **le parcours Google nécessite une invitation** (#5171) ; ne pas laisser le pilote se heurter au 401 `UNKNOWN_ACCOUNT`.
- [ ] `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URL` renseignés sur Render (runbook `docs/GESTION_PROJET/RUNBOOK_GOOGLE_OAUTH_PROD.md`) — sinon le bouton « Continue with Google » donne un 503/500.
- [ ] Workers de queue provisionnés sur Render (`leopardo-queue-worker` + `leopardo-scheduler`, runbook `docs/GESTION_PROJET/RUNBOOK_RENDER_WORKERS.md`) — sinon les emails d'invitation ne partent pas.
- [ ] Base de paie DZ vérifiée : pays DZ, barèmes IRG/CNAS actifs (`docs/payroll/DZ_COMPLIANCE.md`).
- [ ] Le pilote a : un email professionnel, un ordinateur avec un navigateur récent (Chrome/Edge/Firefox), 45 min libres.

## 1. Parcours cible (chronométré — objectif cumulé < 30 min)

> L'instrumentation (#5151) horodate automatiquement chaque étape :
> - champ `data.elapsed_since_company_creation_minutes` de `GET /api/v1/onboarding-setup/checklist`
> - log `onboarding.step_completed` (channel `stderr`, context : `step_key`, `completed_at`, `elapsed_minutes_since_company_creation`).
> Pour une preuve horodatée : relever l'heure de début, puis comparer avec les logs à la fin.

| # | Étape (clé `onboarding_steps`) | Action du pilote | Durée cible | Pièges connus |
|---|---|---|---|---|
| 1 | Connexion | Se connecter via Google (bouton « Continue with Google ») | 1 min | 503/500 si env non configurée ; 401 si pas d'invitation préalable |
| 2 | `company_info` | Renseigner les informations entreprise (nom, pays **DZ**, devise **DZD**, fuseau horaire) | 3 min | Pays/zone mal choisis → mauvais barèmes paie. Vérifier que le modèle DZ (IRG/CNAS) est bien sélectionné |
| 3 | `first_department` | Créer le premier département (ex. « Production ») | 2 min | — |
| 4 | `invite_manager` | Inviter le 2e compte (gestionnaire) — **envoi d'email** | 3 min | Email jamais reçu = worker de queue absent (#5172) ou SPF/DKIM Mailgun |
| 5 | `first_employee` | Ajouter les premiers employés — **import CSV recommandé** pour > 3 employés | 5-8 min | Colonnes obligatoires (nom, prénom, email, salaire de base) ; fichier Excel → exporter en CSV UTF-8 ; doublons emails rejetés |
| 6 | `first_attendance` | Effectuer le premier pointage (kiosque web ou app) | 3 min | Geofence non configuré → pointage hors zone refusé ; méthode non autorisée (cf. punch-methods #5121) |
| 7 | `configure_schedules` | Configurer les horaires (équipes / plages) | 3 min | Horaires vides → absence de pointage « hors planning » au rapport |
| 8 | `configure_payroll` | Configurer la paie (modèle DZ, période, paramètres IRG/CNAS) | 4 min | Barème non renseigné → paie impossible à lancer ; penser au SMIG et à l'abattement 40 % |
| 9 | `first_report` | Générer le premier rapport mensuel (présences) | 2 min | Aucune donnée si les pointages sont vides |
| 10 | `install_kiosk` + `activate_geofence` | (Optionnel) Connecter un kiosque / activer la zone de pointage | 5 min | Device ZKTeco : firmware + méthodes de pointage compatibles |

**Total cible : 28-35 min la 1re fois, < 30 min en conditions réelles** (sans relecture de ce guide).

## 2. Définition du parcours cible (promesse CDC)

1. Création entreprise (infos + modèle DZ) → 2. Invitation gestionnaire → 3. Import employés (CSV) →
4. 1er pointage → 5. 1re paie simulée (run de test, pas de virement).

**Critère pilote validé** : les étapes 1-5 réalisées de bout en bout, seul, en < 30 min, avec les données réelles du pilote.

## 3. Règles d'or pour les agents (instrumentation & suivi)

- **Horodatage** : ne pas ajouter d'outil externe (analytics/APM) — le log `onboarding.step_completed` + le champ `elapsed_since_company_creation_minutes` suffisent (freeze scope #5147, section « Outils d'analytics » = gelé).
- **Chaque blocage rencontré par un pilote** → issue fille **P1** (fix) ou **P2** (amélioration), avec le repro horodaté dans le corps.
- **Preuve** : l'agent QA rejoue le parcours en conditions pilotes et publie le chronogramme dans le carnet du pilote (#5152).

## 4. Support (canal pilote)

- Canal SLA pilotes : voir `docs/pilotes/SLA_PILOTES.md` (#5155) — triage < 24 h, hotfix < 24 h.
- Carnets de feedback : `docs/pilotes/carnets/` (#5152) — un carnet par pilote DZ.
- Contacts d'escalade : fondateur (décisions produit, ex. #5171) / ops Render (provisionnement, secrets).

## 5. Checklist de suivi agent QA (preuve horodatée)

- [ ] Parcours complet rejoué par l'agent QA en conditions pilotes, **chrono à l'appui** (< 30 min, sans assistance)
- [ ] Chronogramme publié (carnet pilote + commentaire issue #5151)
- [ ] Chaque blocage → issue fille P1/P2 créée avec repro
- [ ] Logs `onboarding.step_completed` présents (vérification dans les logs Render)
- [ ] Entrée CHANGELOG (déjà fournie par la PR #5151)

---

*Issue du plan 60 jours (Batch 2). Parcours mesuré — la promesse « < 30 min » est un critère de gate, pas une intention.*
