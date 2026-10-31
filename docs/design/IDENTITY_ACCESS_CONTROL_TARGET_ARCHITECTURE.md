# Architecture cible — Identity & Access Control

- **Statut** : proposition, soumise à validation
- **Date** : 31 octobre 2026 · **HEAD** : `31ef78e04`
- **Principe directeur** : **ne pas créer de Bounded Context** là où l'existant suffit.
  Ce document démontre, pour chaque ajout proposé, pourquoi aucun mécanisme existant ne le
  couvre déjà.

---

## 1. Ce qui n'a PAS besoin d'être refait

Il est tentant de dessiner une architecture neuve. Ce serait une erreur : quatre couches
du socle actuel sont **déjà conformes** au modèle cible et **déjà testées**.

| Couche | Mécanisme existant | Pourquoi on n'y touche pas |
|---|---|---|
| **Isolation** | Schéma PostgreSQL par tenant + `search_path` + `BelongsToCompany` + fail-closed | 4 couches indépendantes, `assertSafeSearchPath()` anti-injection, `try/finally` de restauration. **Tests exécutés PASS.** |
| **Guards** | 5 guards Sanctum disjoints | Structurellement anti-escalade : un token n'ouvre que sa population. Y toucher = risque élevé, gain nul. |
| **Audit** | `audit_logs` + `platform_impersonation_sessions` | Trace déjà l'attribution de rôle, la révocation, la raison d'impersonation. |
| **Invitations** | Token 64 car. haché SHA-256, 7 jours, `lockForUpdate`, usage unique | Cycle complet et sûr. Ajouter l'invitation par téléphone se fait **sans** changer ce socle. |

> **Règle** : toute proposition ci-dessous **s'ajoute**. Aucune ne modifie un guard, un
> middleware ou un trait existant.

---

## 2. Le modèle cible en sept concepts

```
Identity     1 ──── N  Membership  N ──── 1  Scope (tenant)
                            │
                            ├── N  RoleAssignment ── 1 Role ── N Permission
                            │
                            └── N  Entitlement (module / feature)

Audit         journal transverse, immuable
```

### Correspondance avec l'existant

| Concept | Aujourd'hui | Cible | Nature du changement |
|---|---|---|---|
| **Identity** | `users`, `employees`, `super_admins`, `travel_customer_accounts` | `users` (+ pont travel) | **Réduction** (ADR) |
| **Membership** | `user_employee_links` (**non consommé au login**) | même table, **consommée** | **Activation** |
| **Role** | enums en code | enums **+ table `role_assignments`** pour les rôles personnalisés | **Extension** |
| **Permission** | `PlatformPermission` + `manager_role` + grants | + `permissions` en base pour les rôles custom | **Extension** |
| **Scope** | schéma PG + `company_id` | inchangé | **Aucun** |
| **Entitlement** | `companies.features` + `employee_module_grants` | + entitlement par personne | **Extension légère** |
| **Audit** | `audit_logs` | inchangé (+ consentement impersonation) | **Extension légère** |

---

## 3. Les trois ajouts structurants

### 3.1 `ContextSelection` — le chaînon manquant

**Problème prouvé** : une personne dans N entreprises n'a pas de contexte actif ;
`AuthService::findEmployeeInTenantSchemas()` (`:618-663`) retourne le **premier** schéma
trouvé. Aucun endpoint de sélection n'existe (test exécuté, PASS).

**Solution** : un **endpoint de contexte**, pas une nouvelle entité.

```
GET  /api/v1/auth/contexts
  → 200 [{company_id, company_name, role, manager_role, permissions[]}, …]

POST /api/v1/auth/context
  { company_id }
  → 200 { token, token_expires_at, tenant_schema, permissions[] }
```

**Mécanisme** : réutilise **les abilities Sanctum déjà posées** au login
(`AuthService.php:214-220` : `tenant_schema:`, `tenant_company:`, `tenant_employee:`).
L'échange **réémet un token** dont l'ability `tenant_company` porte le contexte choisi.
Aucune colonne nouvelle, aucune session serveur à maintenir.

**Pourquoi pas un nouveau Bounded Context ?** Parce que `TenantManager` sait déjà
basculer un contexte (`setTenant()`, `withinTenant()`). Il manque **seulement** la
surface HTTP qui expose ce choix et le contrôle que l'utilisateur **appartient bien** à
la société demandée — ce qui se lit dans `user_employee_links` ou, à défaut, dans
l'existence de l'employé dans le schéma cible.

**Garde anti-escalade obligatoire** : `POST /auth/context` doit vérifier que l'employé
authentifié a bien un enregistrement dans le schéma demandé **avant** de réémettre. Sans
ce contrôle, l'endpoint devient un moyen de choisir son tenant — c'est-à-dire une faille.

### 3.2 `RoleAssignment` — les rôles personnalisés, sans casser les enums

**Problème** : aucune table `roles`/`permissions` ; les rôles sont des enums figés (test
exécuté, PASS). Un admin de tenant ne peut pas créer « Caissier ».

**Solution** : une table **additive**, lue **après** les enums.

```sql
CREATE TABLE tenant.role_assignments (
    id            bigserial PRIMARY KEY,
    company_id    uuid NOT NULL,
    employee_id   bigint NOT NULL,
    role_key      varchar(64) NOT NULL,   -- 'caissier', 'chef_de_rang', …
    permissions   jsonb NOT NULL,         -- ['restaurant.orders.view', …]
    granted_by    bigint NOT NULL,
    granted_at    timestamptz NOT NULL,
    revoked_at    timestamptz NULL,
    UNIQUE (company_id, employee_id, role_key)
);
```

**Règle de résolution** : `effectivePermissions = enumPermissions(role, manager_role) ∪
roleAssignments(active)`.

**Garde anti-escalade — la règle qui manque aujourd'hui** : lors de la création d'un
`role_assignment`, l'ensemble `permissions` doit être un **sous-ensemble** des permissions
du créateur. C'est le scénario #11 de la matrice de tests, qui est aujourd'hui **sans
objet** (pas de rôles custom) mais qui devient **critique** dès l'introduction de cette
table. **Ce test s'écrit AVANT la table** (voir plan, L3).

**Pourquoi pas Spatie ?** Le projet n'a aucune dépendance de ce type et les enums sont
utilisés partout dans les gates. Introduire Spatie imposerait de réécrire les gates
existants — contraire à « préserver les mécanismes de sécurité ».

### 3.3 `ImpersonationConsent` — le consentement, sans refonte

**Problème** : l'impersonation est tracée (raison, IP, expiration, révocation) mais
**non consentie** (test exécuté, PASS sur l'absence de colonne `consent*`).

**Solution** : deux colonnes sur la table existante.

```sql
ALTER TABLE platform_impersonation_sessions
    ADD COLUMN consent_notified_at timestamptz NULL,
    ADD COLUMN consent_reason_code varchar(32) NULL;  -- 'support_request', 'incident', …
```

Plus une notification à la personne impersonnée (`Notification` existe déjà). Pas de
blocage dur : un support doit pouvoir intervenir en urgence — mais la personne **sait**,
et la trace le prouve.

---

## 4. Ce qu'il ne faut PAS faire

| Tentation | Pourquoi c'est une erreur |
|---|---|
| Une table `identities` unifiant les 4 modèles | Migration de données massive, casse 4 guards, aucun gain de sécurité — le problème n'est pas le nombre de tables mais **l'absence de lien**. |
| Un service d'autorisation central (« PDP ») externe | Le socle `Gate`/policies suffit ; un PDP ajouterait un aller-retour réseau sur **chaque** requête. |
| Fusionner `super_admins` dans `users` | Population interne, guards disjoints, aucune confusion d'autorisation. Le gain serait cosmétique, le risque réel. |
| Rendre `findEmployeeInTenantSchemas()` déterministe par tri | Ne résout rien : « le premier par ordre alphabétique » reste arbitraire. Il faut **demander** à l'utilisateur. |
| Supprimer le guard `travel_customer` maintenant | Casse le contrat de la marketplace et l'app mobile. L'ADR prévoit une dépréciation **planifiée**. |

---

## 5. Sécurité : les gardes à écrire avec chaque ajout

| Ajout | Garde obligatoire | Test à écrire **en premier** |
|---|---|---|
| `POST /auth/context` | Appartenance vérifiée au tenant demandé | « un employé de A ne peut pas obtenir un token pour B » |
| `role_assignments` | `permissions ⊆ permissions(créateur)` | « un admin tenant ne peut pas créer un rôle avec `payments.manage` » |
| `role_assignments` | Révocation immédiate | « révoquer un rôle retire l'accès à la requête suivante » |
| Consentement impersonation | Notification + trace | « une impersonation notifie la personne » |
| Expiration des tokens | `createToken(..., $expiresAt)` partout | « aucun chemin de login ne rend un token sans échéance » |

---

## 6. Compatibilité mobile

**Aucune modification d'application mobile n'est requise par ce document.**

- Les ajouts sont **additifs** : trois nouveaux endpoints, deux colonnes nullables.
- Les contrats existants (`/auth/login`, `/auth/login-code/*`, `/auth/me`) sont
  **inchangés**.
- Le sélecteur de contexte est **optionnel** : une app qui ne l'appelle pas conserve le
  comportement actuel (premier tenant trouvé). C'est ce qui rend la migration sans
  rupture — et c'est aussi la dette à éteindre (§7).

Le détail des contrats destinés à l'app grand public est dans
`docs/design/PUBLIC_ACCOUNT_API_CONTRACT.md`.
