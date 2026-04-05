# RUNBOOK BETA ACCEPTANCE
# Version 1.0 | 2026-04-05

But: valider le MVP complet sur un environnement reel avant exposition a des prospects.

## Preconditions

- Branche `main` a jour
- CI verte sur la derniere PR mergee
- VPS disponible
- Base PostgreSQL disponible
- URL backend connue
- 1 company, 1 manager, 1 employee seeds ou crees manuellement

## Checklist de validation

### 1. Sante plateforme

- Ouvrir `/api/v1/health`
- Attendu: HTTP 200, `status=ok`

### 2. Auth web manager

- Ouvrir `/login`
- Se connecter avec le manager
- Attendu: redirection vers `/dashboard`
- Attendu: tableau employes visible

### 3. Dashboard web

- Verifier presence du jour, total estime, retards
- Ouvrir la fiche d'un employe
- Lancer un quick estimate
- Telecharger le PDF
- Attendu: PDF genere sans erreur

### 4. Mobile reel

- Configurer `API_BASE_URL` vers l'environnement beta
- Login mobile avec l'employe
- Verifier chargement de `/auth/me`
- Faire check-in
- Verifier `today`
- Faire check-out
- Verifier `history`

### 5. Securite minimale

- Tenter acces employee sur un endpoint manager
- Attendu: HTTP 403
- Tenter acces sans token sur endpoint protege
- Attendu: HTTP 401

## Go / No-Go

Go si:
- health OK
- login web OK
- dashboard OK
- PDF OK
- mobile OK
- aucun bug bloquant de securite ou de calcul

No-Go si:
- login KO
- pointage KO
- estimation KO
- PDF KO
- fuite tenant ou contournement RBAC

## Trace minimale a conserver

- Date du test
- URL testee
- Build/commit teste
- Nom testeur
- Liste courte des anomalies
