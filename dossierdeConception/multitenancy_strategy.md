# MULTI-TENANCY STRATEGY – LEOPARDO RH

## Type choisi
👉 Single Database + Tenant ID

## Structure
Chaque table contient :
- tenant_id

## Exemple :
employees
- id
- tenant_id
- name

## Isolation
- Toutes les requêtes filtrées par tenant_id
- Middleware obligatoire

## Sécurité
- Vérification tenant à chaque requête API

## Avantages
- Scalabilité
- Simplicité
- Coût réduit

## Risques
- fuite de données

## Solution
- tests stricts
- scopes automatiques (ORM)

## Auth
- user lié à tenant_id

## Evolution future
- migration possible vers multi-database