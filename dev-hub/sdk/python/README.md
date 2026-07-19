# Leopardo RH Python SDK

Generated from `api/openapi.yaml`.

## Usage

> Note : `api.leopardo-rh.com` est un domaine cible non encore configure en production.
> Remplacer par `https://gestionemployerbackend.onrender.com/api/v1` pour un usage reel aujourd'hui.

```python
from leopardo_client import LeopardoClient

client = LeopardoClient(
    base_url="https://api.leopardo-rh.com/api/v1",  # domaine cible, voir note ci-dessus
    token="your-token",
)

health = client.get_health()
employees = client.get_employees(query={"page": 1})
```

For path parameters:

```python
client.get_platform_companies_by_company_health(
    path_params={"company": 42},
)
```

Run `node dev-hub/tools/generate-openapi-sdk.mjs --check` before publishing.
