# 14 — PLAN DE SOLIDİFİCATİON TECHNIQUE & COMMERCIALE

**Date :** 2026-05-13
**Objectif :** Rendre Leopardo RH techniquement viable, fiable et capable de gagner des marches face aux solutions etablies (Sage HR, OrangeHRM, Kiwi HR, PaieNA).

---

## Diagnostic actuel

### Forces
- API backend Laravel complete (434 tests verts, 18 sprints)
- Multi-pays paie (DZ, MA, SN, TN, TR, FR) avec baremes fiscaux
- Multi-tenant PostgreSQL avec isolation schema
- Dashboard admin Vue.js 3 (16+ ecrans)
- App mobile Flutter avec Riverpod (11 ecrans)
- CI/CD GitHub Actions (backend, mobile, coverage, CodeQL, deploy)
- Architecture RBAC fine (admin, super_admin, manager, employee)
- IA integree (chat, voice, analytics)

### Faiblesses a corriger
- Pas de tests E2E frontend (Playwright)
- Pas de monitoring production actif (Sentry configure mais pas deploye)
- Pas d'integration tierce reelle (banques, CNAS/CNSS)
- Documentation API incomplete pour les integrateurs
- Performance non mesuree sous charge
- Pas de conformite RGPD/loi 18-07 DZ documentee
- Pas d'audit securite independant

---

## Vue d'ensemble du plan

| Phase | Nom | Duree | Priorite | Impact marche |
|-------|-----|-------|----------|---------------|
| 1 | Fiabilite & Tests | 3 semaines | CRITIQUE | Confiance client |
| 2 | Securite & Conformite | 2 semaines | CRITIQUE | Appels d'offres |
| 3 | Performance & Scalabilite | 2 semaines | HAUTE | SLA enterprises |
| 4 | Integrations Metier | 4 semaines | HAUTE | Differentiation |
| 5 | UX & Accessibilite | 2 semaines | MOYENNE | Adoption |
| 6 | Documentation & Certification | 2 semaines | HAUTE | Credibilite |
| 7 | Go-To-Market Cible | 3 semaines | HAUTE | Revenue |

**Total : ~18 semaines (4.5 mois)**

---

## Phase 1 : FİABİLİTE & TESTS (3 semaines)

### 1.1 Tests E2E Frontend — Playwright
**Objectif :** 0 regression visible par le client

```
- [ ] Setup Playwright dans front/admin-dashboard/ (config + fixtures)
- [ ] Scenario login + dashboard chargement KPI
- [ ] Scenario paie : creer un run → calculer → valider → telecharger bulletin
- [ ] Scenario conges : soumettre → approuver → verifier solde
- [ ] Scenario recrutement : creer poste → pipeline kanban → changer stage
- [ ] Scenario exports : generer rapport PDF/CSV
- [ ] Integrer dans CI web-ci.yml avec screenshots on failure
```

### 1.2 Tests Integration API — Scenarios metier complets
**Objectif :** Couvrir les parcours client de bout en bout

```
- [ ] Scenario onboarding : creer entreprise → ajouter employes → configurer paie pays
- [ ] Scenario cycle paie mensuel : pointage → calcul → validation → bulletins → export banque
- [ ] Scenario conges avec regles : demande → validation manager → deduction solde → rapport
- [ ] Scenario multi-tenant : isolation complete entre 2 entreprises (donnees, fichiers, logs)
- [ ] Coverage backend cible : 60% (actuel : ~40%)
```

### 1.3 Tests Mobile — Flutter
**Objectif :** App stable sans crash

```
- [ ] Widget tests pour les 11 ecrans principaux
- [ ] Tests unitaires pour les 8 repositories (mock ApiClient)
- [ ] Test navigation GoRouter (routes protegees / publiques)
- [ ] Golden tests pour les composants critiques (fiche de paie, calendrier conges)
```

---

## Phase 2 : SECURİTE & CONFORMİTE (2 semaines)

### 2.1 Audit Securite
```
- [ ] Activer OWASP ZAP scan automatise dans CI
- [ ] Audit SQL injection sur tous les endpoints avec parametres
- [ ] Verifier CSRF/XSS protection sur formulaires admin
- [ ] Audit des permissions RBAC : matrice complete roles/routes
- [ ] Rate limiting sur tous les endpoints sensibles (auth, paie, IA)
- [ ] Rotation automatique des tokens JWT (refresh token flow)
```

### 2.2 Conformite RGPD / Loi 18-07 (DZ)
```
- [ ] Page politique de confidentialite + CGU
- [ ] Mecanisme d'export des donnees personnelles (droit d'acces)
- [ ] Mecanisme de suppression des donnees (droit a l'oubli)
- [ ] Registre des traitements (document interne)
- [ ] Consentement employe pour le traitement biometrique
- [ ] Chiffrement des donnees sensibles au repos (AES-256 pour IBAN, salaire)
- [ ] Journalisation des acces aux donnees RH (audit trail)
```

### 2.3 Sauvegarde & Reprise
```
- [ ] Backup PostgreSQL automatise quotidien (pg_dump + S3)
- [ ] Test de restauration mensuel documente
- [ ] RPO < 24h, RTO < 4h documentes
- [ ] Runbook incident documente (qui contacter, quoi faire)
```

---

## Phase 3 : PERFORMANCE & SCALABİLİTE (2 semaines)

### 3.1 Load Testing
```
- [ ] Setup k6 ou Artillery pour tests de charge
- [ ] Benchmark : 100 employes simultanes (pointage, consultation paie)
- [ ] Benchmark : calcul paie 500 employes en < 30 secondes
- [ ] Benchmark : dashboard admin avec 10k employes (pagination, search)
- [ ] Identifier et corriger les N+1 queries (Laravel Debugbar)
```

### 3.2 Optimisation Backend
```
- [ ] Cache Redis sur les endpoints read-heavy (dashboard, analytics, employes liste)
- [ ] Queue asynchrone pour : calcul paie batch, export PDF, envoi notifications
- [ ] Indexation PostgreSQL sur les colonnes filtrees frequemment
- [ ] Compression response (gzip/brotli)
- [ ] CDN pour assets statiques (bulletins PDF, photos profil)
```

### 3.3 Optimisation Frontend
```
- [ ] Code splitting par route (Vue.js lazy loading)
- [ ] Service Worker pour mode offline mobile (cache API + assets)
- [ ] Optimisation bundle size (tree-shaking, analyse webpack)
- [ ] Skeleton loading sur tous les ecrans (pas de blank screen)
```

---

## Phase 4 : İNTEGRATİONS METİER (4 semaines)

### 4.1 Integrations Bancaires
```
- [ ] Export virement SEPA (XML ISO 20022) pour FR/MA
- [ ] Export virement CPA/BNA format DZ
- [ ] Integration API CIH/BMCE pour virements MA (si partenariat)
- [ ] Statut virement suivi dans les bulletins
```

### 4.2 Integrations Organismes Sociaux
```
- [ ] Generation declaration CNAS trimestrielle (DZ) — format PDF/XML
- [ ] Generation declaration CNSS (MA) — bordereau
- [ ] Export DSN simplifie (FR)
- [ ] Simulation cotisations en temps reel (widget dashboard)
```

### 4.3 Integrations Tiers
```
- [ ] Webhook bidirectionnel avec Slack/Teams (notifications RH)
- [ ] SSO SAML/OIDC pour enterprises (Azure AD, Google Workspace)
- [ ] Import/export fichier Excel employes (bulk onboarding)
- [ ] Integration comptable (export ecritures paie vers Sage, QuickBooks)
- [ ] Integration pointeuse ZKTeco (SDK TCP/IP ou API cloud)
- [ ] Calendrier Google/Outlook sync (conges, formations)
```

### 4.4 API Publique
```
- [ ] Documentation OpenAPI 3.1 complete et publiee (Swagger UI)
- [ ] SDK client JavaScript/Python genere depuis OpenAPI
- [ ] Rate limiting API avec plans (starter: 100 req/min, pro: 1000, enterprise: illimite)
- [ ] Versioning API (v1 stable, v2 beta)
- [ ] Sandbox environnement pour les integrateurs
```

---

## Phase 5 : UX & ACCESSİBİLİTE (2 semaines)

### 5.1 Dashboard Admin
```
- [ ] Mode sombre complet (Tailwind dark:)
- [ ] Responsive mobile sur tous les ecrans
- [ ] Raccourcis clavier (Ctrl+K search, navigation rapide)
- [ ] Notifications temps reel (WebSocket ou SSE)
- [ ] Personnalisation dashboard (widgets drag & drop)
- [ ] Multi-langue complet (FR/EN/AR/TR) avec RTL
```

### 5.2 App Mobile
```
- [ ] Biometric login (empreinte / Face ID)
- [ ] Push notifications Firebase (absences, paie, approbations)
- [ ] Mode offline avec sync (pointage, demandes conge)
- [ ] Widget home screen (solde conges, prochain jour de paie)
- [ ] Deep linking (notification → ecran specifique)
```

### 5.3 Accessibilite (a11y)
```
- [ ] Audit WCAG 2.1 AA sur dashboard et vitrine
- [ ] Navigation clavier complete
- [ ] Labels ARIA sur tous les composants interactifs
- [ ] Contraste minimum 4.5:1 verifie
- [ ] Screen reader compatible (VoiceOver/TalkBack testes)
```

---

## Phase 6 : DOCUMENTATİON & CERTİFİCATİON (2 semaines)

### 6.1 Documentation Technique
```
- [ ] Architecture Decision Records (ADR) pour les choix critiques
- [ ] Diagramme d'architecture (C4 model) : contexte, containers, composants
- [ ] Runbook operations : deploiement, rollback, monitoring, incidents
- [ ] Guide integration partenaires (webhooks, API, SSO)
```

### 6.2 Documentation Commerciale
```
- [ ] Dossier technique pour appels d'offres (architecture, securite, conformite)
- [ ] Matrice de conformite (RGPD, loi 18-07, ISO 27001 objectifs)
- [ ] Benchmarks performance publies (temps de reponse, SLA)
- [ ] Comparatif fonctionnel vs concurrents (Sage HR, OrangeHRM, PaieNA)
```

### 6.3 Certifications (moyen terme)
```
- [ ] ISO 27001 — Securite de l'information (objectif 12 mois)
- [ ] SOC 2 Type I — Confiance tiers (objectif 18 mois)
- [ ] Hebergement certifie (OVH/Scaleway, datacenters en Afrique pour la latence)
```

---

## Phase 7 : GO-TO-MARKET CİBLE (3 semaines)

### 7.1 Produit Minimum Vendable (PMV)
```
- [ ] Configurer Stripe/Chargily pour paiement SaaS (DZ: Chargily, MA/FR: Stripe)
- [ ] Onboarding self-service : inscription → configuration pays → import employes → premier bulletin
- [ ] Trial 14 jours automatise avec donnees de demo
- [ ] Email sequences automatiques (J1, J3, J7, J12 — tips + call to action)
```

### 7.2 Canaux de Vente
```
- [ ] Landing page avec temoignages video + metriques clients
- [ ] Blog SEO : 10 articles (pointage biometrique DZ, paie MA, conges legaux, etc.)
- [ ] LinkedIn Ads cible DRH PME Maghreb (budget test 500 USD/mois)
- [ ] Partenariat revendeur ZKTeco (Algerie, Maroc)
- [ ] Webinaires mensuels « Simplifiez votre RH » (FR + AR)
- [ ] Programme referral : 1 mois gratuit par client apporte
```

### 7.3 Support & Success
```
- [ ] Chat support in-app (Crisp ou Intercom)
- [ ] Base de connaissances / Help Center (guides pas a pas avec captures)
- [ ] SLA documente : reponse < 4h, resolution < 24h (Pro), < 8h (Enterprise)
- [ ] Onboarding assiste pour les clients Enterprise (call setup 1h)
```

---

## Priorites immediates (30 prochains jours)

| Semaine | Actions | Impact |
|---------|---------|--------|
| S1 | Tests E2E Playwright (login, paie, conges) + Coverage 60% | Fiabilite |
| S2 | Audit securite OWASP + chiffrement donnees sensibles | Conformite |
| S3 | Load testing + optimisation N+1 queries + cache Redis | Performance |
| S4 | OpenAPI complete + SDK + sandbox integrateurs | Credibilite |

---

## KPI de succes

| Metrique | Objectif S1 | Objectif S3 | Objectif S6 |
|----------|-------------|-------------|-------------|
| Coverage backend | 60% | 70% | 80% |
| Tests E2E | 5 scenarios | 15 scenarios | 30 scenarios |
| Temps reponse P95 | < 500ms | < 300ms | < 200ms |
| Uptime | 99% | 99.5% | 99.9% |
| Clients payants | 3 pilots | 10 | 30 |
| NPS | N/A | > 30 | > 50 |
| Securite | OWASP scan vert | Audit externe | ISO 27001 lance |

---

## Budget estimatif

| Poste | Mensuel | Annuel |
|-------|---------|--------|
| Hebergement (Render/Railway/OVH) | 150 USD | 1 800 USD |
| Sentry APM | 26 USD | 312 USD |
| GitHub Team | 44 USD (4 devs) | 528 USD |
| Domaine + SSL | 2 USD | 24 USD |
| LinkedIn Ads (test) | 500 USD | 6 000 USD |
| ZKTeco demo hardware | — | 800 USD (one-time) |
| **Total** | **~722 USD** | **~9 464 USD** |

---

## Conclusion

Ce plan transforme Leopardo RH d'un MVP fonctionnel en un produit **enterprise-ready** capable de :
1. **Repondre aux appels d'offres** avec un dossier technique complet (securite, conformite, SLA)
2. **Gagner la confiance** des DRH avec des tests automatises et un uptime mesure
3. **Se differencier** par les integrations metier (CNAS, banques, ZKTeco) que les concurrents generiques ne couvrent pas
4. **Scaler** avec une architecture multi-tenant et une API publique documentee

Le ticket d'entree est la **Phase 1 (Fiabilite)** — sans elle, aucun client enterprise ne signera.
