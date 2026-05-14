# Leopardo RH JavaScript SDK

Generated from `api/openapi.yaml`.

## Usage

```js
import { createLeopardoClient } from "./leopardoClient.js";

const client = createLeopardoClient({
  baseUrl: "https://api.leopardo-rh.com/api/v1",
  token: process.env.LEOPARDO_TOKEN,
});

const health = await client.getHealth();
const employees = await client.getEmployees({
  query: { page: 1 },
});
```

For path parameters:

```js
await client.getPlatformCompaniesByCompanyHealth({
  pathParams: { company: 42 },
});
```

Run `node dev-hub/tools/generate-openapi-sdk.mjs --check` before publishing.
