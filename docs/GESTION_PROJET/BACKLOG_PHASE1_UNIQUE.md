# BACKLOG UNIQUE - PHASE 1 MVP
# Version 4.1.3 | 04 Avril 2026
# Scope verrouille: Persona Murat / petites structures

---

## REGLES DE BACKLOG

- Ce fichier est la seule liste executable Phase 1.
- Toute nouvelle demande hors liste -> backlog Phase 2.
- Un ticket est "Ready" seulement si API + ERD/SQL + regles metier + tests sont references.

---

## TICKETS EXECUTABLES (ORDRE OBLIGATOIRE)

| ID | Ticket | Domaine | Sortie attendue | Gate de validation |
|---|---|---|---|---|
| P1-01 | Bootstrap API Laravel + base test | Backend | app boot + migrations pass | CI backend verte |
| P1-02 | Auth Sanctum (login/logout/me) sans refresh | Backend | endpoints auth stables | tests auth + 401 flow |
| P1-03 | CRUD employes minimal Murat | Backend | employe cree en <2 min | tests feature employes |
| P1-04 | Pointage check-in/check-out + schedule snapshot | Backend | attendance fiable | tests attendance + timezone |
| P1-05 | Endpoint `daily-summary` | Backend | montant journalier estime | tests calcul + RBAC |
| P1-06 | Endpoint `quick-estimate` | Backend | estimation periode | tests calcul periode + RBAC |
| P1-07 | Recu PDF informatif periode | Backend | PDF non officiel genere | test generation fichier |
| P1-08 | Quickstart onboarding (<15) | Backend/Web | mode quickstart actif | tests settings + status onboarding |
| P1-09 | Ecran Flutter daily cost manager | Mobile | liste + total journalier | tests widget + integration API mock |
| P1-10 | Widget employe gain du jour | Mobile | gain estime visible | tests UI + etat vide |
| P1-11 | Ecran Flutter quick estimate + partage | Mobile | simulation + share PDF | tests parcours complet |
| P1-12 | Stabilisation beta + hardening | Cross | release candidate | 0 bug bloquant P1 |

---

## DEFINITION OF READY (PAR TICKET)

- Contrat API reference (ou spec UI si mobile)
- Regle metier explicite
- Impacts DB identifies
- Cas RBAC identifies
- Plan de test minimal defini

---

## DEFINITION OF DONE (PAR TICKET)

- Code merge sur branche cible
- Tests automatiques verts
- Changelog mis a jour
- Journal racine mis a jour
- Aucun ecart avec `INDEX_CANONIQUE.md`
