# PLAN_ACTION2 - Leopardo Mobile-First Company OS

Version: 1.0  
Date: 2026-06-13  
Statut: pret pour decoupage GitHub Projects

## Objectif

Ce dossier prend le relais des plans historiques `docs/PLAN_ACTION` deja largement executes ou cartographies. Il sert a organiser la prochaine phase de solidification du produit en tickets atomiques, assignables a plusieurs agents sans perdre la vision globale.

Le but n'est plus de prouver que Leopardo fonctionne. Le but est de le rendre:

- vendable immediatement depuis la vitrine;
- stable pour plusieurs clients pilotes;
- clair pour les employes, managers, RH et super-admins;
- robuste cote API, securite, jobs et observabilite;
- extensible vers l'ecosysteme developpeur et l'IA;
- coherent comme "OS de gestion d'entreprise mobile-first".

## Structure

| Fichier | Usage |
|---|---|
| `01_MODE_EXECUTION_MULTI_AGENT.md` | Discipline de travail pour plusieurs agents, Definition of Done et regles de PR |
| `02_BACKLOG_ATOMIQUE.md` | Backlog detaille par tickets PA2, avec dependances et criteres d'acceptation |
| `03_GITHUB_PROJECT_IMPORT.csv` | Version CSV importable dans GitHub Projects |
| `04_ROADMAP_RELEASES.md` | Ordre de livraison recommande par releases marche |

## Axes couverts

1. Acquisition et vitrine commerciale.
2. Trial self-service et onboarding client.
3. Web admin premium et workflows plateforme.
4. Mobile employee/manager/platform admin.
5. Kiosk et biometrie terrain.
6. API production-grade et contrats frontend.
7. Securite, RBAC, multi-tenant et audit.
8. Jobs, Redis, notifications et traitements asynchrones.
9. Paiements, avances, solde employe et documents PDF.
10. Internationalisation, multi-pays, devises et accessibilite.
11. Observabilite, tests de charge et readiness operations.
12. Documentation developpeur, marketplace future et IA-ready.

## Regles de priorisation

- P0: bloque le lancement, la connexion, le paiement, la securite ou l'acquisition.
- P1: augmente fortement la conversion, la confiance client ou la robustesse pilote.
- P2: ameliore l'experience, la maintenabilite ou la scalabilite a moyen terme.
- P3: opportunite future, a planifier apres les pilotes.

## Definition of Done globale

Chaque ticket doit:

- respecter l'architecture existante;
- declarer les routes/endpoints/UI concernes;
- garder les reponses API compatibles mobile/web/kiosk;
- mettre a jour `CHANGELOG.md`;
- mettre a jour `AGENTS.md` si une lecon durable est apprise;
- ajouter ou adapter les tests/contrats proportionnes au risque;
- eviter les boutons, liens ou routes fictifs;
- fournir une preuve CI ou une justification claire si la verification est deleguee a GitHub Actions.

