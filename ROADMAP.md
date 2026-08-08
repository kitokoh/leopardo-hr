# Product Roadmap — Leopardo RH

> ⚠️ **Source de vérité opérationnelle : [`docs/REFERENTIEL_PRODUIT/ROADMAP.md`](docs/REFERENTIEL_PRODUIT/ROADMAP.md)** (séquencement détaillé, horizons) et **[`PILOTAGE.md`](PILOTAGE.md)** (état opérationnel réel, priorités courantes). Ce document est un résumé de statut produit — en cas de divergence, **PILOTAGE.md prime** (issue #1505).

## 📍 Statut actuel (2026-08)

Le code sur `main` a dépassé le périmètre MVP initial. Plateforme livrée :

- **HR Core** : cycle de vie employé, départements, contrats, RBAC (principal/rh/dept/comptable/superviseur/employee), cabinet documentaire.
- **Multi-tenant** : isolation **mode `schema`** activée (`search_path` PostgreSQL), onboarding invités, trial guidé.
- **Paie** : moteur de paie automatisé multi-pays (DZ, MA, FR, TR), bulletins PDF, exports bancaires, avances, prêts, commissions.
- **Présence & pointage** : mobile géolocalisé, kiosque ZKTeco biométrique, corrections, workflows d'approbation, anomalies.
- **Mobile** : 6 apps Flutter (employee, manager, hr, marketing, platform_admin + core).
- **IA** : Leo AI — orchestrateur d'agents, prédictions (absentéisme, turnover), commande vocale, préparation de paie.
- **Modules Phase 2 livrés** : Billing/abonnements (Stripe, Chargily), Caméras RTSP, Absence avancée (politiques de congés), Fleet, Recrutement, Notifications, Marketing.
- **Intégrations** : webhooks sortants (endpoints + dispatcher), SSO, ZKTeco, Traccar (GPS).

## 🚀 Prochaines priorités (extrait)

1. **Stabilisation** : fermer les issues P0/P1 du backlog GitHub (mobile, CI staging, infra de test).
2. **Pilotes clients** : 3 pilotes avant ouverture de nouveaux modules Phase 2.
3. **Enterprise** (12-24 mois) : moteurs de conformité fiscale additionnels, API publique v1 stabilisée, forecasting avancé.
4. **Écosystème** (24+ mois) : marketplace de modules, SDK ouvert, couche d'identité globale mobile.

## 📋 Comment suivre

- Backlog détaillé et issues : **GitHub Issues** (label `P1`/`P2`/`P3`).
- Séquençage produit : `docs/REFERENTIEL_PRODUIT/ROADMAP.md`.
- État opérationnel et écarts : `PILOTAGE.md`.

*Cette roadmap est sujette à évolution selon les retours clients et de la communauté.*
