---

## 🔒 Isolation Multi-Tenant (preset leopardo-multitenancy v1.0.0)

> Toute table métier et tout endpoint API doivent respecter l'isolation tenant PostgreSQL.

### Checklist isolation tenant

**Modèle / Migration**
- [ ] Nouvelle table : colonne `company_id BIGINT NOT NULL` + index `(company_id, ...)`
- [ ] Migration dans `database/migrations/tenant/` (pas `public/`)
- [ ] Nom de table qualifié dans les migrations si `Schema::table()` utilisé

**Contrôleur / Requête**
- [ ] Toutes les requêtes Eloquent : `->where('company_id', auth()->user()->company_id)`
- [ ] Ressource partagée cross-tenant : retourner 404, jamais 403
- [ ] Aucune donnée d'un tenant visible pour un autre dans la réponse

**Tests**
- [ ] Test `assert 404` : manager tenant B accède ressource tenant A
- [ ] Test isolation sur chaque endpoint CRUD de la spec

**Audit**
- [ ] Opérations sensibles tracées dans `AuditLog` avec `company_id`
