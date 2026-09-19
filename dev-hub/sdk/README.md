# Leopardo RH SDKs

Official generated clients for the Leopardo RH API.

## Source of Truth

The canonical contract is `api/openapi.yaml`. JavaScript and Python clients, and the `dev-hub/openapi/v1.yaml` mirror below, are generated from that file with:

```bash
node dev-hub/tools/generate-openapi-sdk.mjs   # ou : make openapi-sync
```

> **Since #7654 (tranche 3), generated outputs are NOT committed.** They are
> gitignored and regenerated on demand (locally via the command above, and in CI by
> `.github/workflows/openapi-ci.yml`, which proves generation works on every PR).
> Committing them regenerated ~738 MB of historical pack bloat.

To verify that locally generated SDKs and the OpenAPI mirror are still aligned with `api/openapi.yaml` (after running the generator):

```bash
node dev-hub/tools/generate-openapi-sdk.mjs --check
```

## Generated Targets

- JavaScript: `dev-hub/sdk/javascript/leopardoClient.js` (generated, not committed)
- Python: `dev-hub/sdk/python/leopardo_client.py` (generated, not committed)
- Manifest: `dev-hub/sdk/MANIFEST.json` (generated, not committed)
- OpenAPI mirror for external integrators: `dev-hub/openapi/v1.yaml` (generated, not committed — full copy of `api/openapi.yaml`; prefer `api/openapi.yaml` or the API's own `/docs/openapi.yaml` endpoint directly)

The generated clients expose one method per OpenAPI operation plus a generic `request(...)` method for advanced cases.

## Rule

Do not edit generated SDK files manually. Update `api/openapi.yaml`, then run the generator (`make openapi-sync`) to refresh your local copies. Generated files are gitignored since #7654 — never commit them.
