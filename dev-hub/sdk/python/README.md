# Leopardo RH Python SDK

Generated from `api/openapi.yaml`.

## Usage

```python
from leopardo_client import LeopardoClient

client = LeopardoClient(
    base_url="https://api.leopardo-rh.com/api/v1",
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
