# Leopardo RH Web

Frontend Next.js pour le dashboard web Leopardo RH.

## API behavior

- Local development defaults to `http://localhost:8000/api/v1`.
- Production builds default to `https://gestionemployerbackend.onrender.com/api/v1`.
- Set `NEXT_PUBLIC_API_URL` to override either mode.

Create a local env file when you want an explicit override:

```bash
cp .env.example .env.local
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
