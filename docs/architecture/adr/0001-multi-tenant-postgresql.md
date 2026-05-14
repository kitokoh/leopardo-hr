# ADR 0001 - Multi-tenant PostgreSQL

## Statut

Acceptee.

## Contexte

Leopardo RH sert des PME differentes avec des donnees RH sensibles : paie, pointage, documents, biometrie et audit. La plateforme doit eviter les fuites inter-tenant tout en gardant un cout d'exploitation compatible avec des clients de 5 a 50 employes.

## Decision

Le backend Laravel conserve PostgreSQL comme base transactionnelle principale avec :

- schemas separes pour les tenants dedies ;
- schema `shared_tenants` pour les petits clients mutualises ;
- tables publiques pour la plateforme, les plans, les super-admins et le provisioning ;
- `TenantMiddleware` responsable du contexte tenant et du `search_path` ;
- `company_id` obligatoire sur les modeles tenant directs ;
- tests de securite pour les modeles isoles par chaine FK.

## Consequences

Cette decision donne une isolation forte sans multiplier les services. Elle impose de verifier le `search_path` avant toute requete tenant, de ne pas contourner les global scopes sans justification, et de garder les migrations separees entre `database/migrations/public` et `database/migrations/tenant`.

## Regles operationnelles

- Ne jamais lire une donnee tenant avant resolution du tenant courant.
- Toute nouvelle table tenant directe doit porter `company_id`.
- Toute table sans `company_id` direct doit avoir un test d'isolation via parent FK.
- Les migrations publiques doivent rester idempotentes pour Render.
