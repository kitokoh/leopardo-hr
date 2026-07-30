# Leopardo RH SDKs

Official generated clients for the Leopardo RH API.

## Source of Truth

The canonical contract is `api/openapi.yaml`. JavaScript and Python clients, and the `dev-hub/openapi/v1.yaml` mirror below, are generated from that file with:

```bash
node dev-hub/tools/generate-openapi-sdk.mjs
```

To verify that committed SDKs and the OpenAPI mirror are still aligned with `api/openapi.yaml`:

```bash
node dev-hub/tools/generate-openapi-sdk.mjs --check
```

## Generated Targets

- JavaScript: `dev-hub/sdk/javascript/leopardoClient.js`
- Python: `dev-hub/sdk/python/leopardo_client.py`
- Manifest: `dev-hub/sdk/MANIFEST.json`
- OpenAPI mirror for external integrators: `dev-hub/openapi/v1.yaml` (full copy of `api/openapi.yaml`, kept for backward compatibility with older integrations that reference this path; prefer `api/openapi.yaml` or the API's own `/docs/openapi.yaml` endpoint directly)

The generated clients expose one method per OpenAPI operation plus a generic `request(...)` method for advanced cases.

## Rule

Do not edit generated SDK files manually. Update `api/openapi.yaml`, run the generator, then commit the generated output.
