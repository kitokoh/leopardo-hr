import { getLocaleDirection, normalizeLocale, type AppLocale } from '@/lib/i18n'
import { t } from '@/lib/i18n/locale-catalog'

/**
 * Manifeste PWA de l'application grand public (couloir 16, #9103).
 *
 * ## Pourquoi un manifeste SÉPARÉ et pas celui de `/manifest`
 *
 * `/manifest` décrit le produit RH : `start_url: '/'`, catégories
 * `business`/`productivity`, et quatre raccourcis `/signup`, `/demo`,
 * `/guides/rh-startup`, `/pricing`. Un visiteur qui installe la PWA depuis
 * `/decouvrir` — le point d'arrivée des QR codes des commerçants — obtenait
 * donc une icône qui ouvrait la page de vente RH.
 *
 * La spécification du couloir 16 est explicite : « l'app ne doit jamais
 * ressembler à un logiciel RH avec des boutons ajoutés ». Le manifeste fait
 * partie de l'identité perçue : il doit donc être propre à la surface publique.
 *
 * ## Ce que ce manifeste ne fait pas
 *
 * Il ne touche ni à `/manifest` (le produit RH garde le sien, test de
 * non-régression dédié), ni à `sw.js`, ni au périmètre du service worker.
 * `scope: '/decouvrir'` borne volontairement l'application installée à la
 * surface publique : installer Leopardo Public ne doit pas donner une fenêtre
 * sans barre d'adresse sur le back-office.
 */

export const dynamic = 'force-dynamic'

interface Shortcut {
  name: string
  short_name: string
  description: string
  url: string
}

const COPY: Record<AppLocale, { name: string; description: string; shortcuts: Shortcut[] }> = {
  fr: {
    name: String(t('fr', 'publicApp.manifestName') ?? ''),
    description: String(t('fr', 'publicApp.manifestDescription') ?? ''),
    shortcuts: [
      {
        name: String(t('fr', 'publicApp.shortcutHome') ?? ''),
        short_name: String(t('fr', 'publicApp.shortcutHome') ?? ''),
        description: String(t('fr', 'publicApp.shortcutHomeDesc') ?? ''),
        url: '/decouvrir',
      },
      {
        name: String(t('fr', 'publicApp.shortcutMessages') ?? ''),
        short_name: String(t('fr', 'publicApp.shortcutMessages') ?? ''),
        description: String(t('fr', 'publicApp.shortcutMessagesDesc') ?? ''),
        url: '/conversations',
      },
      {
        name: String(t('fr', 'publicApp.shortcutProfile') ?? ''),
        short_name: String(t('fr', 'publicApp.shortcutProfile') ?? ''),
        description: String(t('fr', 'publicApp.shortcutProfileDesc') ?? ''),
        url: '/decouvrir?panel=profile',
      },
    ],
  },
  en: {
    name: String(t('en', 'publicApp.manifestName') ?? ''),
    description: String(t('en', 'publicApp.manifestDescription') ?? ''),
    shortcuts: [
      {
        name: String(t('en', 'publicApp.shortcutHome') ?? ''),
        short_name: String(t('en', 'publicApp.shortcutHome') ?? ''),
        description: String(t('en', 'publicApp.shortcutHomeDesc') ?? ''),
        url: '/decouvrir',
      },
      {
        name: String(t('en', 'publicApp.shortcutMessages') ?? ''),
        short_name: String(t('en', 'publicApp.shortcutMessages') ?? ''),
        description: String(t('en', 'publicApp.shortcutMessagesDesc') ?? ''),
        url: '/conversations',
      },
      {
        name: String(t('en', 'publicApp.shortcutProfile') ?? ''),
        short_name: String(t('en', 'publicApp.shortcutProfile') ?? ''),
        description: String(t('en', 'publicApp.shortcutProfileDesc') ?? ''),
        url: '/decouvrir?panel=profile',
      },
    ],
  },
  tr: {
    name: String(t('tr', 'publicApp.manifestName') ?? ''),
    description: String(t('tr', 'publicApp.manifestDescription') ?? ''),
    shortcuts: [
      {
        name: String(t('tr', 'publicApp.shortcutHome') ?? ''),
        short_name: String(t('tr', 'publicApp.shortcutHome') ?? ''),
        description: String(t('tr', 'publicApp.shortcutHomeDesc') ?? ''),
        url: '/decouvrir',
      },
      {
        name: String(t('tr', 'publicApp.shortcutMessages') ?? ''),
        short_name: String(t('tr', 'publicApp.shortcutMessages') ?? ''),
        description: String(t('tr', 'publicApp.shortcutMessagesDesc') ?? ''),
        url: '/conversations',
      },
      {
        name: String(t('tr', 'publicApp.shortcutProfile') ?? ''),
        short_name: String(t('tr', 'publicApp.shortcutProfile') ?? ''),
        description: String(t('tr', 'publicApp.shortcutProfileDesc') ?? ''),
        url: '/decouvrir?panel=profile',
      },
    ],
  },
  ar: {
    name: String(t('ar', 'publicApp.manifestName') ?? ''),
    description: String(t('ar', 'publicApp.manifestDescription') ?? ''),
    shortcuts: [
      {
        name: String(t('ar', 'publicApp.shortcutHome') ?? ''),
        short_name: String(t('ar', 'publicApp.shortcutHome') ?? ''),
        description: String(t('ar', 'publicApp.shortcutHomeDesc') ?? ''),
        url: '/decouvrir',
      },
      {
        name: String(t('ar', 'publicApp.shortcutMessages') ?? ''),
        short_name: String(t('ar', 'publicApp.shortcutMessages') ?? ''),
        description: String(t('ar', 'publicApp.shortcutMessagesDesc') ?? ''),
        url: '/conversations',
      },
      {
        name: String(t('ar', 'publicApp.shortcutProfile') ?? ''),
        short_name: String(t('ar', 'publicApp.shortcutProfile') ?? ''),
        description: String(t('ar', 'publicApp.shortcutProfileDesc') ?? ''),
        url: '/decouvrir?panel=profile',
      },
    ],
  },
}

function resolveLocale(request: Request): AppLocale {
  const language = request.headers.get('accept-language')?.split(',')[0]
  return normalizeLocale(language)
}

export async function GET(request: Request) {
  const locale = resolveLocale(request)
  const copy = COPY[locale]

  return Response.json(
    {
      name: copy.name,
      short_name: 'Leopardo',
      description: copy.description,
      lang: locale,
      dir: getLocaleDirection(locale),
      // `start_url` et `scope` bornent l'app installée à la surface publique.
      // Un visiteur qui installe depuis un QR code de commerçant doit atterrir
      // sur la découverte, jamais sur la page de vente RH.
      start_url: '/decouvrir',
      scope: '/decouvrir',
      display: 'standalone',
      orientation: 'portrait-primary',
      theme_color: '#10b981',
      background_color: '#ffffff',
      // Catégories de l'app grand public : on cherche un commerce, on achète,
      // on se déplace. `business`/`productivity` décrivaient le produit RH.
      categories: ['food', 'shopping', 'travel'],
      icons: [
        { src: '/icon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any maskable' },
        { src: '/favicon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' },
        { src: '/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any maskable' },
        { src: '/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any maskable' },
      ],
      shortcuts: copy.shortcuts,
      prefer_related_applications: false,
      related_applications: [],
    },
    { headers: { 'Cache-Control': 'public, max-age=300', Vary: 'Accept-Language' } },
  )
}
