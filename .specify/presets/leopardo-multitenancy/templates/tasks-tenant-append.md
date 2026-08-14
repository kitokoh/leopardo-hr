---

## 🔒 Tâches Isolation Tenant (preset leopardo-multitenancy v1.0.0)

### T-ISO-1 : Vérifier le scope company_id
- [ ] Chaque `Model::query()` porte `->where('company_id', ...)`
- [ ] Aucun `Model::find($id)` sans vérification `company_id` post-récupération

### T-ISO-2 : Tests cross-tenant
- [ ] `GET /resource/{id}` avec token tenant B + ID tenant A → 404
- [ ] `PUT/DELETE /resource/{id}` cross-tenant → 404
- [ ] Lister les resources : seuls ceux du tenant courant dans la réponse

### T-ISO-3 : Migration
- [ ] `company_id NOT NULL` sur toute nouvelle table métier
- [ ] Index composite `(company_id, id)` ou `(company_id, created_at)` selon usage
- [ ] Migration dans `database/migrations/tenant/` (pas `public/`)
