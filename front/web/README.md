# Leopardo RH Web

Frontend Next.js pour le dashboard web Leopardo RH.

## Required build-time environment variables

The production build **fails fast** without these two variables — they are not
optional defaults (issues #7963, #3452/#4600):

- `NEXT_PUBLIC_API_URL` — public base URL of the API consumed by the browser
  (SDK, forms, checkout). There is no hard-coded remote fallback: a production
  build without it aborts instead of shipping a frontend pointed at the wrong
  backend.
- `NEXT_PUBLIC_SITE_URL` — brand domain used for canonicals, `sitemap.xml`,
  JSON-LD and hreflang alternates. `DEFAULT_SITE_URL` (`leopardo-rh.com`) is
  NXDOMAIN, so a production build without it aborts rather than emitting
  canonicals to a dead domain.

Local development defaults to `http://localhost:8000/api/v1` for the API and
`http://localhost:3000` for the site URL.

Create a local env file when you want an explicit override:

```bash
cp .env.local.example .env.local
```

## Getting started

```bash
npm install
npm run dev
```

Open [http://localhost:3000](http://localhost:3000).

## Useful commands

```bash
npm run dev
npm run lint
npm run build
```
