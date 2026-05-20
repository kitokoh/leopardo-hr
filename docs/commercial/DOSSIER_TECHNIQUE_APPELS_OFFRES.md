# Dossier Technique — Leopardo RH

## Fiche d'identite solution

| Critere | Detail |
|---------|--------|
| **Nom** | Leopardo RH |
| **Type** | SaaS multi-tenant |
| **Version** | 4.16.x |
| **Langages** | PHP 8.4 (API), TypeScript/JavaScript (web), Dart (mobile) |
| **Frameworks** | Laravel 11, Vue.js 3, Next.js 16, Flutter 3.x |
| **Base de donnees** | PostgreSQL 16, Redis 7 |
| **Hebergement** | Render (API), Cloudflare Pages (admin), Vercel (vitrine) |
| **CI/CD** | GitHub Actions (18 workflows) |
| **Monitoring** | Sentry APM, structured logging JSON |
| **Marches** | DZ, MA, SN, TN, CI, CM, TR, FR |

---

## 1. Architecture technique

### 1.1 Vue d'ensemble

```
                    ┌──────────────────────────┐
                    │      Load Balancer        │
                    │      (Cloudflare)         │
                    └─────────┬────────────────┘
                              │
              ┌───────────────┼───────────────┐
              │               │               │
       ┌──────▼──────┐ ┌─────▼─────┐ ┌──────▼──────┐
       │  API Laravel │ │Admin Vue  │ │ Vitrine     │
       │  (Render)    │ │(CF Pages) │ │ Next.js     │
       │  Port 8000   │ │ SPA       │ │ (Vercel)    │
       └──────┬───────┘ └───────────┘ └─────────────┘
              │
      ┌───────┼───────┐
      │               │
┌─────▼─────┐  ┌──────▼─────┐
│ PostgreSQL │  │   Redis    │
│   16       │  │     7      │
└────────────┘  └────────────┘
```

### 1.2 Multi-tenant

- Isolation par schema PostgreSQL (un schema par entreprise)
- `TenantMiddleware` resout le tenant depuis le token JWT
- Pas de fuite inter-tenant : FK chain isolation verifiee par tests automatises
- Modeles sans `company_id` direct isoles via relation parent

### 1.3 API RESTful

- 130+ endpoints documentes via OpenAPI/Swagger
- Versioning explicite (`v1`) avec header `X-API-Version`
- Rate limiting par plan (Starter: 100 req/min, Pro: 1000, Enterprise: illimite)
- Pagination, filtrage, tri sur toutes les collections
- Compression gzip/brotli

---

## 2. Securite

### 2.1 Authentification

| Mecanisme | Detail |
|-----------|--------|
| Tokens | Laravel Sanctum (tokens personnels avec expiration) |
| Rotation | Auto-refresh transparent via middleware quand le token approche l'expiration |
| 2FA | TOTP pour super-admin (verification code + recovery) |
| OAuth | Google SSO (web + mobile) |
| Rate limiting | Endpoints auth limites a 5 tentatives/min |

### 2.2 Autorisation (RBAC)

| Role | Scope | Exemple |
|------|-------|---------|
| `super_admin` | Plateforme entiere | Gestion entreprises, facturation |
| `admin` | N/A (reserve futur) | — |
| `manager` (principal) | Entreprise entiere | Paie, conges, recrutement |
| `manager` (departement) | Son departement | Validation conges de son equipe |
| `employee` | Ses propres donnees | Consultation bulletins, demande conge |

### 2.3 Protection donnees

- Chiffrement IBAN/salaire/numero national au repos (AES-256 via Eloquent `encrypted` casts)
- Pas de stockage de mots de passe en clair (bcrypt hash)
- Audit trail : `audit_logs` pour chaque acces sensible (fiches employes, exports privacy)
- CORS configure strictement pour les domaines autorises

### 2.4 Conformite

| Norme | Statut |
|-------|--------|
| RGPD (UE) | Conforme — export donnees, droit a l'oubli, registre traitements |
| Loi 18-07 (DZ) | Conforme — consentement biometrique, journalisation acces |
| OWASP Top 10 | Scan ZAP automatise en CI |
| ISO 27001 | En cours (objectif 12 mois) |

---

## 3. Fonctionnalites

### 3.1 Modules RH

| Module | Statut | Sous-fonctionnalites |
|--------|--------|---------------------|
| **Gestion employes** | Production | CRUD, profils, documents, organigramme |
| **Pointage** | Production | QR, biometrique ZKTeco, geolocalise, anomalies |
| **Conges/Absences** | Production | Demande, validation, soldes, calendrier, accrue automatique |
| **Paie** | Production | Multi-pays (DZ/MA/SN/TN/TR/FR), bulletins PDF, cotisations |
| **Recrutement** | Production | Pipeline kanban, offres, candidatures |
| **Formation** | Production | Catalogue, inscriptions, suivi |
| **Vehicules** | Production | Flotte, suivi, depenses |
| **Contrats** | Production | Types, renouvellements, alertes expiration |
| **Notes de frais** | Production | Demande, approbation, remboursement |
| **Prets** | Production | Demande, echeancier, deductions paie |

### 3.2 Integrations

| Integration | Statut |
|------------|--------|
| ZKTeco (pointeuses) | Pret |
| Google Calendar/Outlook | Pret |
| API REST v1 (OpenAPI) | Pret |
| Webhooks (temps reel) | Pret |
| Stripe (paiement international) | Pret |
| Chargily (paiement DZ) | Pret |
| Export SEPA XML ISO 20022 | Pret |
| Export CPA/BNA (DZ) | Pret |
| Export CCP Algerie | Pret |
| CNAS/CNSS declarations | Pret |

### 3.3 Exports bancaires

| Format | Pays | Description |
|--------|------|-------------|
| SEPA XML (pain.001.001.03) | FR, MA | Virements credit ISO 20022 |
| CPA pipe-delimited | DZ | Credit Populaire d'Algerie |
| BNA pipe-delimited | DZ | Banque Nationale d'Algerie |
| CCP fixed-width | DZ | Poste Algerie |
| CSV generique | Tous | Import banque generique |

---

## 4. Performance

### 4.1 Architecture de performance

- Cache Redis sur endpoints read-heavy (dashboard, analytics, listes employes)
- Queue asynchrone pour calculs paie batch, exports PDF, notifications
- Indexation PostgreSQL optimisee sur colonnes filtrees
- Code splitting et lazy loading sur le dashboard admin
- Compression response (gzip/brotli)

### 4.2 SLA cibles

| Metrique | Valeur |
|----------|--------|
| Disponibilite | 99.9% |
| Temps reponse P95 | < 500ms |
| RPO (perte donnees max) | < 24h |
| RTO (reprise max) | < 4h |

---

## 5. Qualite logicielle

### 5.1 Tests

| Type | Nombre | Outil |
|------|--------|-------|
| Tests unitaires/feature backend | 130+ | PHPUnit |
| Tests E2E admin | 11 specs | Playwright |
| Tests mobile | 16 | Flutter test |
| Tests isolation tenant | FK chain | PHPUnit |
| Analyse statique | PHPStan L5 | Diff-gate CI |
| Scan securite | CodeQL + ZAP | CI automatise |

### 5.2 CI/CD

18 workflows GitHub Actions :
- Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)
- Backend Quality (Pint + PHPStan)
- Backend Security (Composer Audit)
- Coverage Gate (seuil configurable)
- Mobile Flutter
- Web Build + Lint + E2E
- CodeQL (backend + actions)
- Governance Gates (CHANGELOG + fichiers canoniques)
- Deploy Staging automatique
- TruffleHog Secret Scan
- Dependency Review

### 5.3 Documentation

- OpenAPI/Swagger publie sur `/docs`
- AGENTS.md (guide operationnel agents)
- DEVELOPMENT.md (guide contributeur)
- Architecture Decision Records (ADR)
- Diagramme C4 (contexte, containers, composants)
- Runbooks : deploy, rollback, backup/restore, incident P1, monitoring

---

## 6. Support et maintenance

| Niveau | SLA | Canal |
|--------|-----|-------|
| Starter | 24h reponse | Email |
| Pro | 4h reponse, 24h resolution | Email + chat |
| Enterprise | 2h reponse, 8h resolution | Dedie + telephone |

---

## 7. Feuille de route

| Trimestre | Fonctionnalites |
|-----------|----------------|
| Q3 2026 | SSO SAML/OIDC, mode offline mobile, push notifications Firebase |
| Q4 2026 | ISO 27001, integrations ERP (Sage, SAP), SDK Python/JS |
| Q1 2027 | IA avancee (predictions absenteisme, chatbot vocal), marketplace extensions |
