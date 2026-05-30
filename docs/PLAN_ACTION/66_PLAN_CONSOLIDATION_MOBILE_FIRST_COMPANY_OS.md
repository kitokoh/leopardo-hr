# Plan 66 - Consolidation finale Mobile-First Company OS

## Source

Document utilisateur "Remporter l'application - consolidation finale du Mobile-First Company OS" recu le 2026-05-29.

## Objectif

Verifier que les 44 idees consolidees sont soit deja implementees, soit rattachees a un plan executable existant, soit ajoutees dans cette feuille de route. Ce plan sert de table de correspondance pour eviter les oublis et guider les prochains lots jusqu'au positionnement officiel : "OS de gestion d'entreprise mobile-first".

## Regle d'execution

- Ne pas coder une nouvelle fonctionnalite tant que son plan source est identifie.
- Livrer par lots coherents, avec PR, checks CI et mise a jour `CHANGELOG.md`.
- Garder `front/mobile_apps/` comme source mobile canonique.
- Garder l'API Laravel comme socle unique de tous les frontends.

## Cartographie phases A-J

| Phase | Points | Plans sources | Etat |
|-------|--------|---------------|------|
| A - Depot, infra, API, async, charge, observabilite | 1-7 | 15, 16, 20, 21, 23, 27, 30, 57, 63 | Partiel, continuer Plan 57 puis Plan 63 |
| B - Mobile-first experience, branding, design system, tenant branding | 8-11 | 25, 28, 41, 58 | Partiel, Plan 58 reste a implementer |
| C - Pointage intelligent, multi-sessions, auto-close, timezone, GPS, kiosque/biometrie | 12-17 | 31, 42, 43, 49, 51, 64 | Multi-sessions livre, auto-close/timezone/GPS restent Plan 64 |
| D - Taches, performance, validations, notifications | 18-21 | 19, 31, 38, 50, 52, 60 | Partiel, double validation avances reste Plan 60 |
| E - Profil portable, placard numerique, QR onboarding | 22-24 | 32, 33, 54 | Largement planifie/partiel, verifier UX et tests |
| F - Manager/RH, horaires, isolation tenant | 25-28 | 34, 35, 36, 37, 40, 53 | Partiel, garder tests RBAC/tenant obligatoires |
| G - Super-admin plateforme | 29-31 | 29, 45, 46, 56 | Partiel, continuer durcissement platform admin |
| H - Paie/finance | 32-38 | 03, 60, 61, 62, 65 | A implementer par ordre 60 -> 61 -> 62 -> 65 |
| I - Internationalisation, docs dev, marketplace, open core | 39-42 | 10, 24, 47, 57, nouveau lot 66.4 | Marketplace/open core restent a cadrer |
| J - Positionnement produit | 43-44 | 11, 16, 59 | Plan 59 a implementer apres socle operationnel |

## Lots d'execution

### Lot 66.1 - Audit anti-oubli

- Lire les plans 01-65 et produire une matrice "idee -> fichier plan -> statut".
- Marquer comme `implemente`, `partiel`, `a faire`, `hors lancement`.
- Ne pas dupliquer les plans existants.

### Lot 66.2 - Stabilite mobile immediate

- Corriger tout ecran de demarrage bloque : jamais de page grise, jamais de logo infini.
- Les apps employee, manager et platform admin doivent afficher soit l'app, soit une erreur actionnable.
- Les initialisations optionnelles ne doivent pas bloquer le premier rendu.

### Lot 66.3 - Readiness lancement par domaine

- API : health, auth, erreurs, rate limits, docs.
- Mobile : login, pointage, compte, notifications, demandes, manager team, platform admin.
- Finance : avances, solde, paiements, documents.
- Infra : queues, workers, monitoring, k6.

### Lot 66.4 - Marketplace et open core

- Cadrer les extensions futures : plugins, modules, webhooks, scopes API.
- Definir ce qui est open core et ce qui reste enterprise.
- Ne rien ouvrir publiquement sans audit secrets, licences et donnees demo.

### Lot 66.5 - Positionnement final

- Aligner vitrine, pricing, docs et pitch sur "Mobile-First Company OS".
- Distinguer clairement modules disponibles, beta et roadmap.
- Eviter toute promesse marketing non soutenue par l'API.

## Criteres d'acceptation

- Les 44 points consolides ont une destination claire.
- Les manques ne sont plus disperses dans la conversation.
- Le prochain agent peut reprendre par plan et par lot sans redecouvrir l'historique.
- Le produit garde une trajectoire coherente vers le lancement marche.
