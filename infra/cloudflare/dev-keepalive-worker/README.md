# dev-keepalive-worker — relais Cloudflare du keepalive dev

Épic #8659 / issue #8664. Pourquoi ce Worker : l'ordonnanceur de crons GitHub
a déjà cessé de tirer sur ce dépôt (arrêt constaté le 2026-09-21). Le keepalive
du tier dev ne peut pas dépendre d'un seul ordonnanceur gratuit. Cloudflare
Workers (plan gratuit) fournit des Cron Triggers fiables — ce Worker est le
relais hors GitHub de `.github/workflows/dev-keepalive.yml`.

## Ce qu'il fait / ne fait pas

- ✅ Ping `KEEPALIVE_URL` toutes les 10 min, 06:00–22:59 UTC (même fenêtre
  budgétaire que le workflow — voir `docs/ops/DEV_STACK_ROTATION.md` § Budget).
- ✅ `GET /` renvoie le statut de la dernière sonde (debug opérateur).
- ❌ Aucune bascule : la rotation est arbitrée uniquement par le watchdog
  GitHub (cooldown, rapport d'incident). Un seul arbitre = pas de ping-pong.

## Déploiement (compte Cloudflare DEV, plan gratuit)

```bash
cd infra/cloudflare/dev-keepalive-worker
npx wrangler@latest deploy        # 1ʳᵉ fois : wrangler login (ou CLOUDFLARE_API_TOKEN)
```

Le Cron Trigger de `wrangler.toml` est créé avec le déploiement. Vérification :
dashboard Cloudflare → Workers → `leopardo-dev-keepalive` → onglet Triggers.

## Après une bascule de stack

Mettre à jour `KEEPALIVE_URL` vers l'URL `/health` de la nouvelle stack active :

```bash
npx wrangler@latest deploy        # après édition de wrangler.toml [vars]
# ou, sans redéployer : dashboard → Worker → Settings → Variables
```

puis commiter le `wrangler.toml` à jour dans la PR d'alignement du registre
(voir le rapport `[dev-switch]` émis à chaque bascule).

## Coût

Plan Workers gratuit : ~25 000 requêtes cron/mois ici (4 464 exactement :
6/h × 17 h × 31 j + appels manuels) — très en deçà des 100 000/jour offerts.
