/**
 * dev-keepalive-worker — Épic #8659 / issue #8664.
 *
 * Relais de keepalive du tier dev, HORS GitHub : l'ordonnanceur de crons
 * GitHub a déjà cessé de tirer sur le dépôt (arrêt constaté le 2026-09-21).
 * Un Cron Trigger Cloudflare (plan Workers gratuit) ping la stack dev ACTIVE
 * pendant la fenêtre de travail. Même budget quota que dev-keepalive.yml :
 * fenêtré 06:00–22:59 UTC, cadence 10 min < spin-down Render (15 min),
 * ~620 h/mois < 750 h. Ne pinger QUE la stack active : les standby restent
 * froides pour préserver leur propre quota.
 *
 * Ce worker NE FAIT PAS de bascule : la logique de rotation (watchdog,
 * cooldown, rapport) vit dans .github/workflows/dev-keepalive.yml — un seul
 * endroit arbitre, le worker ne fait que tenir la stack éveillée.
 *
 * Config : variable KEEPALIVE_URL (URL /health complète, non secrète) posée
 * dans wrangler.toml ou le dashboard. Mettre à jour KEEPALIVE_URL après
 * chaque bascule (runbook docs/ops/DEV_STACK_ROTATION.md § Worker).
 */

export default {
  async scheduled(event, env, ctx) {
    const url = env.KEEPALIVE_URL;
    if (!url) {
      console.error("KEEPALIVE_URL absente — keepalive inopérant.");
      return;
    }
    ctx.waitUntil(ping(url));
  },

  // Lecture manuelle : GET / → statut de la dernière sonde (debug opérateur).
  async fetch(request, env) {
    const url = env.KEEPALIVE_URL || "";
    if (!url) return new Response("KEEPALIVE_URL non configurée\n", { status: 500 });
    const result = await ping(url);
    return new Response(JSON.stringify(result), {
      headers: { "content-type": "application/json" },
      status: result.http_code === 200 ? 200 : 502,
    });
  },
};

async function ping(url) {
  const started = Date.now();
  let http_code = 0;
  try {
    const res = await fetch(url, { cf: { cacheTtl: 0, cacheEverything: false } });
    http_code = res.status;
  } catch (err) {
    console.error(`keepalive ${url} → erreur réseau: ${err}`);
  }
  const result = {
    target: url,
    http_code,
    latency_ms: Date.now() - started,
    at: new Date().toISOString(),
  };
  console.log(`keepalive ${url} → HTTP ${http_code} en ${result.latency_ms} ms`);
  return result;
}
