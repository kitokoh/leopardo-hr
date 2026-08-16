# Feature Specification: /supported-countries — directives de cache + ETag (Closes #4502)

**Issue**: #4502 (P3, api, perf) — registre pays quasi-statique servi sans Cache-Control ni ETag,
chaque lancement mobile rebrûlait le bucket public-registry 60/min.

## Fix
- `Cache-Control: public, max-age=3600` + ETag (`W/"<sha1(serialize(registry))>"`) + `Vary: Accept-Language`.
- Requête `If-None-Match` → 304.

## Tests
- `test_registry_is_cacheable_with_etag` : headers + 304.
