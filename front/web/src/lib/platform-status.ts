/**
 * platform-status — calcul du statut public de la plateforme (issue #8700).
 *
 * Pourquoi ce module plutôt que du JSX avec des chaînes en dur : pendant la
 * panne du 2026-09-20 → 2026-10-18, la prod a servi du code figé et l'API dev
 * était suspendue sans que personne — ni bêta-testeur, ni le fondateur — ne
 * puisse lire « oui, on est au courant » à un endroit. Une page de statut qui
 * affiche des états écrits à la main serait devenue fausse au premier incident.
 *
 * Règle unique : **on ne déclare jamais un service en ligne sans l'avoir
 * sondé**. Chaque composant porte donc l'URL réellement appelée, le code HTTP
 * obtenu et l'horodatage de la mesure ; un composant non sondable est
 * `unknown`, jamais `operational`.
 *
 * ⚠️ Les sondes sont en lecture seule, sans authentification, et ne doivent
 * jamais exposer de secret : seule l'issue (code HTTP, latence) sort d'ici.
 */

export type StatusState = 'operational' | 'degraded' | 'down' | 'unknown'

export type StatusComponent = {
  id: string
  /** Clé i18n du libellé (`statusPage.components.<clé>`) — résolue côté écran. */
  labelKey: string
  state: StatusState
  /** URL réellement sondée, exposée pour que le visiteur puisse vérifier. */
  probeUrl: string
  httpCode: number | null
  latencyMs: number | null
  /** Valeur retournée par le service quand il en expose une (`version`). */
  detail?: string | null
}

export type StatusSnapshot = {
  state: StatusState
  checkedAt: string
  components: StatusComponent[]
}

type ProbeSpec = {
  id: string
  labelKey: string
  url: string
  /** Un 200 ne suffit pas : on peut exiger un marqueur dans le corps. */
  expectJsonField?: string
}

// Les URLs viennent de l'environnement : la page doit fonctionner sur un
// déploiement de stack B sans qu'on oublie de la reconfigurer. Les valeurs par
// défaut sont celles mesurées le 2026-10-18.
const DEV_API_BASE = (process.env.NEXT_PUBLIC_STATUS_DEV_API_BASE_URL
  ?? 'https://leopardo-dev-b.onrender.com/api/v1').replace(/\/+$/, '')
const PROD_API_BASE = (process.env.NEXT_PUBLIC_STATUS_PROD_API_BASE_URL
  ?? 'https://leopardo-prod.onrender.com/api/v1').replace(/\/+$/, '')

const PROBES: ProbeSpec[] = [
  {
    id: 'api-prod',
    labelKey: 'apiProd',
    url: `${PROD_API_BASE}/health`,
    expectJsonField: 'status',
  },
  {
    id: 'api-dev',
    labelKey: 'apiDev',
    url: `${DEV_API_BASE}/health`,
    expectJsonField: 'status',
  },
  // Les fronts sont sondés sur leur racine : un 200 prouve que le déploiement
  // sert bien quelque chose, pas que l'API derrière répond — d'où la séparation
  // explicite entre composants dans l'affichage.
  {
    id: 'web',
    labelKey: 'web',
    url: process.env.NEXT_PUBLIC_STATUS_WEB_URL
      ?? 'https://gestionemployer-backend.vercel.app',
  },
  {
    id: 'admin',
    labelKey: 'admin',
    url: process.env.NEXT_PUBLIC_STATUS_ADMIN_URL
      ?? 'https://leo-admin-prod.pages.dev',
  },
]

const PROBE_TIMEOUT_MS = 6000

async function probe(spec: ProbeSpec): Promise<StatusComponent> {
  const startedAt = Date.now()
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), PROBE_TIMEOUT_MS)

  try {
    const response = await fetch(spec.url, {
      method: 'GET',
      redirect: 'follow',
      cache: 'no-store',
      signal: controller.signal,
      headers: { 'user-agent': 'leopardo-status-page/1.0' },
    })
    const latencyMs = Date.now() - startedAt

    let detail: string | null = null
    if (spec.expectJsonField) {
      // Un service suspendu Render renvoie du HTML avec un code d'erreur ; un
      // proxy peut renvoyer 200 avec une page d'erreur. On lit donc le corps et
      // on exige le champ attendu avant de déclarer `operational`.
      try {
        const payload: unknown = await response.json()
        if (payload && typeof payload === 'object' && spec.expectJsonField in payload) {
          const value = (payload as Record<string, unknown>)[spec.expectJsonField]
          detail = value === undefined || value === null ? null : String(value)
        } else {
          detail = null
        }
      } catch {
        detail = null
      }
    }

    if (response.ok && (!spec.expectJsonField || detail !== null)) {
      // Latence élevée = dégradé, pas en panne : sur Render free, un service
      // réveillé en 4 s reste utilisable — l'annoncer « down » serait faux.
      const state: StatusState = latencyMs > 3000 ? 'degraded' : 'operational'
      return {
        id: spec.id,
        labelKey: spec.labelKey,
        state,
        probeUrl: spec.url,
        httpCode: response.status,
        latencyMs,
        detail,
      }
    }

    return {
      id: spec.id,
      labelKey: spec.labelKey,
      state: response.ok ? 'degraded' : 'down',
      probeUrl: spec.url,
      httpCode: response.status,
      latencyMs,
      detail,
    }
  } catch {
    return {
      id: spec.id,
      labelKey: spec.labelKey,
      state: 'down',
      probeUrl: spec.url,
      httpCode: null,
      latencyMs: null,
      detail: null,
    }
  } finally {
    clearTimeout(timer)
  }
}

/** Agrège : `down` si un composant est down, `degraded` si l'un est dégradé. */
export function aggregateState(components: StatusComponent[]): StatusState {
  if (components.length === 0) return 'unknown'
  if (components.some((component) => component.state === 'down')) return 'down'
  if (components.some((component) => component.state === 'degraded')) return 'degraded'
  if (components.every((component) => component.state === 'unknown')) return 'unknown'
  return 'operational'
}

export async function getStatusSnapshot(): Promise<StatusSnapshot> {
  // Sondes en parallèle : la page ne doit pas mettre 4 × 6 s à répondre quand
  // deux services sont injoignables.
  const components = await Promise.all(PROBES.map(probe))

  return {
    state: aggregateState(components),
    checkedAt: new Date().toISOString(),
    components,
  }
}

export const STATUS_PROBES = PROBES
