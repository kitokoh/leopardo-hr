# Leopardo RH SDKs

Official generated clients for the Leopardo RH API.

## Source of Truth

The canonical contract is `api/openapi.yaml`. JavaScript and Python clients are generated from that file with:

```bash
node dev-hub/tools/generate-openapi-sdk.mjs
```

To verify that committed SDKs are still aligned with OpenAPI:

```bash
node dev-hub/tools/generate-openapi-sdk.mjs --check
```

## Generated Targets

- JavaScript: `dev-hub/sdk/javascript/leopardoClient.js`
- Python: `dev-hub/sdk/python/leopardo_client.py`
- Manifest: `dev-hub/sdk/MANIFEST.json`

The generated clients expose one method per OpenAPI operation plus a generic `request(...)` method for advanced cases.

## Rule

Do not edit generated SDK files manually. Update `api/openapi.yaml`, run the generator, then commit the generated output.
