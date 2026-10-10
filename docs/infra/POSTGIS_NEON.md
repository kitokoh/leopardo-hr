# PostGIS sur Neon — runbook (BC-33 GEO)

**Références** : GEO-01 (#8350), spec `docs/specifications/MODULE_GEOCORE_ET_VERTICAL_VTC.md` (décision D1).

## Activation

L'extension est installée par la migration publique
`2026_10_10_000001_8350_enable_postgis_extension.php`
(`CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public`), exécutée par
`php artisan leopardo:migrate`. Aucune action manuelle n'est requise sur Neon :
PostGIS est une extension supportée nativement, activable par le rôle
applicatif (pas de superuser requis).

## Vérification

```bash
php artisan geo:check-postgis
```

Sortie attendue : `PostGIS disponible (version X.Y).`.
Si l'extension est absente : `PostGIS indisponible : le module geo fonctionne
en mode dégradé (Haversine).` — la commande sort quand même en SUCCESS : le
mode dégradé est supporté (GEO-03), ce n'est pas une panne.

## Mode dégradé

Le module `geo` bascule automatiquement sur le calculateur Haversine quand
l'extension est indisponible (détection `GeoCapabilities`, cache
`GEO_CAPABILITIES_CACHE_TTL`, défaut 300 s). Après provisionnement de
l'extension, vider le cache via la commande ou attendre le TTL.

## Points d'attention Neon

- **Schéma** : le `search_path` applicatif est `shared_tenants,public` — la
  migration force `WITH SCHEMA public` ; les fonctions `ST_*` se résolvent
  ensuite sans qualification dans les deux schémas.
- **Quotas** : les requêtes spatiales doivent exploiter les index GIST
  (`<->` KNN, `ST_DWithin`) — pas de seq-scan sur les tables de positions ;
  surveiller le compute time (alerte quota Neon).
- **Restauration** : un backup restauré conserve l'extension (le
  dump/restore Neon gère PostGIS).
- **Rollback** : `down()` de la migration drope l'extension — à ne jouer que
  si aucune colonne `geography` n'existe encore (les migrations tenant
  GEO/VTC qui en créent portent leur propre `down()`).
