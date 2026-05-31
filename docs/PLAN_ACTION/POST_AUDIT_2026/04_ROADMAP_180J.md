# ROADMAP 180 JOURS — Scale & Expansion
## Leopardo HR — Post-Audit 2026

**Objectif :** Atteindre 50+ clients, 1000+ utilisateurs actifs, MRR significatif, expansion régionale.
**Période :** J91 à J180
**Prérequis :** Roadmap 90J complétée (stores publiés, billing actif, 5+ clients)

---

## ROADMAP 180 JOURS (Scale)

| # | Tâche | Priorité | Effort | Impact | Dépendances |
|---|---|---|---|---|---|
| 1 | **Infrastructure Kubernetes / auto-scaling** — Migration de Render vers K8s managé (Render K8s ou Fly.io) avec HPA | 🔴 CRITIQUE | 10j | Scale 10 000 utilisateurs | DevOps senior |
| 2 | **Database read replicas** — PostgreSQL avec 1-2 replicas lecture pour les requêtes reporting | 🔴 CRITIQUE | 5j | Performance requêtes analytiques | Neon.tech / Render PG |
| 3 | **Export bancaire SEPA / virement masse** — BankExportController + formats PAIN.001 (FR/EU) | 🔴 CRITIQUE | 7j | Enterprise grade France/Europe | BankExportGenerator |
| 4 | **IA Assistant RH production-grade** — AIGatewayController + analystes prédictifs + recommandations | 🟠 MAJEUR | 15j | Différenciation marché | AI models (Plan 04) |
| 5 | **Module recrutement complet** — RecruitmentController + JobPosting + Applicant + Interview pipeline | 🟠 MAJEUR | 10j | Extension du produit | Modèles existants |
| 6 | **Formation et e-learning** — TrainingController + enrollment + progression + certificats | 🟠 MAJEUR | 8j | Rétention client + upsell | Training models |
| 7 | **Webhooks enterprise** — WebhookEndpoint + WebhookDispatcher + retry + signature HMAC | 🟠 MAJEUR | 5j | Intégration ERP clients | WebhookController |
| 8 | **SSO SAML/OIDC enterprise** — SSOController étendu pour les grands comptes (Azure AD, Okta) | 🟠 MAJEUR | 7j | Adoption grandes entreprises | SSOService |
| 9 | **Multi-pays Phase 2** — Support Tunisie, Sénégal, Turquie (moteurs paie + UI localisée) | 🟠 MAJEUR | 10j | Expansion géographique | CountryRules existants |
| 10 | **App Platform Admin v2** — Dashboard analytiques avancés, health monitoring, facturation automatique | 🟠 MAJEUR | 7j | Efficacité opérationnelle | PlatformMetrics |
| 11 | **Marketplace de modules** — Architecture plugin, SDK partenaire, API publique versionnée | 🟡 MOYEN | 15j | Écosystème partenaires | Plan 66.4 |
| 12 | **Programme partenaires revendeurs** — Portail partenaire, marges, white-label | 🟡 MOYEN | 10j | Distribution indirecte | Billing + Branding |
| 13 | **Conformité SOC 2 Type I** — Préparation audit sécurité, politiques, contrôles | 🟡 MOYEN | 20j | Crédibilité enterprise | Sécurité complète |
| 14 | **Intégration comptabilité** — Export vers Sage, QuickBooks, Odoo (via webhooks/CSV) | 🟡 MOYEN | 7j | Valeur ajoutée PME | BankExport + Payroll |
| 15 | **Mobile App v2 — Performance & UX** — Refonte navigation, animations Lottie, mode hors-ligne complet | 🟡 MOYEN | 15j | Engagement + rétention | Flutter 3.x |
| 16 | **CDN et optimisation assets** — Cloudflare R2 pour fichiers RH, PDF mis en cache | 🟡 MOYEN | 5j | Latence mondiale | Firebase Storage → R2 |
| 17 | **Programme certification** — Formation partenaires, certification "Leopardo Partner" | 🟡 MOYEN | 10j | Croissance indirecte | Content + LMS |
| 18 | **SLA contractuels 99.9%** — Monitoring SLA, playbooks incident, communication client | 🟡 MOYEN | 5j | Confiance enterprise | Monitoring stack |
| 19 | **Tests de charge 10 000 utilisateurs** — k6 load testing complet, chaos engineering | 🟡 MOYEN | 7j | Confiance scale | Infrastructure K8s |
| 20 | **Series A / financement** — Deck investisseur mis à jour avec métriques réelles | 🔵 FAIBLE | 15j | Accélération croissance | MRR + métriques |

---

## Critères de sortie (J180)

- [ ] 50+ clients actifs, MRR > 5 000€/mois
- [ ] 1 000+ utilisateurs actifs quotidiens sur les apps mobiles
- [ ] Infrastructure scalée à 10 000 utilisateurs sans dégradation
- [ ] Apps disponibles sur App Store ET Google Play dans 3 pays
- [ ] Module recrutement et formation disponibles
- [ ] Webhook API publiée avec documentation développeur
- [ ] Au moins 1 intégration comptabilité live chez un client
- [ ] SOC 2 Type I en cours de préparation
