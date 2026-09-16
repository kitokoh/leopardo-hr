import { getLocaleDirection, normalizeLocale, type AppLocale } from '@/lib/i18n'
import { t } from '@/lib/i18n/locale-catalog'

export const dynamic = 'force-dynamic'

const COPY: Record<
  AppLocale,
  {
    name: string
    description: string
    shortcuts: Array<{ name: string; short_name: string; description: string; url: string }>
  }
> = {
  fr: {
    name: String(t('fr', 'seoRoot.manifestName') ?? ''),
    description: String(t('fr', 'seoRoot.manifestDescription') ?? ''),
    shortcuts: [
      { name: 'Essai gratuit', short_name: 'Essai', description: 'Commencer un essai gratuit de 14 jours', url: '/signup?source=pwa_shortcut' },
      { name: 'Demander une démo', short_name: 'Démo', description: 'Demander une démonstration personnalisée', url: '/demo?source=pwa_shortcut' },
      { name: 'Guides RH', short_name: 'Guides', description: 'Lire les guides RH et ressources pour managers', url: '/guides/rh-startup?source=pwa_shortcut' },
      { name: 'Paie et tarifs', short_name: 'Paie', description: 'Comparer les plans et modules disponibles', url: '/pricing?source=pwa_shortcut' },
    ],
  },
  en: {
    name: String(t('en', 'seoRoot.manifestName') ?? ''),
    description: String(t('en', 'seoRoot.manifestDescription') ?? ''),
    shortcuts: [
      { name: 'Free trial', short_name: 'Trial', description: 'Start a free 14-day trial', url: '/signup?source=pwa_shortcut' },
      { name: 'Request a demo', short_name: 'Demo', description: 'Request a personalized demonstration', url: '/demo?source=pwa_shortcut' },
      { name: 'HR guides', short_name: 'Guides', description: 'Read HR guides and resources for managers', url: '/guides/rh-startup?source=pwa_shortcut' },
      { name: 'Payroll and pricing', short_name: 'Pricing', description: 'Compare available plans and modules', url: '/pricing?source=pwa_shortcut' },
    ],
  },
  tr: {
    name: String(t('tr', 'seoRoot.manifestName') ?? ''),
    description: String(t('tr', 'seoRoot.manifestDescription') ?? ''),
    shortcuts: [
      { name: 'Ücretsiz deneme', short_name: 'Deneme', description: '14 günlük ücretsiz denemeyi başlatın', url: '/signup?source=pwa_shortcut' },
      { name: 'Demo isteyin', short_name: 'Demo', description: 'Kişiselleştirilmiş bir demo isteyin', url: '/demo?source=pwa_shortcut' },
      { name: 'İK rehberleri', short_name: 'Rehber', description: 'İK rehberlerini ve yönetici kaynaklarını okuyun', url: '/guides/rh-startup?source=pwa_shortcut' },
      { name: 'Bordro ve fiyatlar', short_name: 'Fiyatlar', description: 'Mevcut planları ve modülleri karşılaştırın', url: '/pricing?source=pwa_shortcut' },
    ],
  },
  ar: {
    name: String(t('ar', 'seoRoot.manifestName') ?? ''),
    description: String(t('ar', 'seoRoot.manifestDescription') ?? ''),
    shortcuts: [
      { name: 'تجربة مجانية', short_name: 'تجربة', description: 'ابدأ تجربة مجانية لمدة 14 يومًا', url: '/signup?source=pwa_shortcut' },
      { name: 'طلب عرض توضيحي', short_name: 'عرض', description: 'اطلب عرضًا توضيحيًا مخصصًا', url: '/demo?source=pwa_shortcut' },
      { name: 'أدلة الموارد البشرية', short_name: 'أدلة', description: 'اقرأ أدلة الموارد البشرية وموارد المديرين', url: '/guides/rh-startup?source=pwa_shortcut' },
      { name: 'الرواتب والأسعار', short_name: 'الأسعار', description: 'قارن الخطط والوحدات المتاحة', url: '/pricing?source=pwa_shortcut' },
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
      start_url: '/',
      scope: '/',
      display: 'standalone',
      orientation: 'portrait-primary',
      theme_color: '#10b981',
      background_color: '#ffffff',
      categories: ['business', 'productivity'],
      icons: [
        { src: '/icon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any maskable' },
        { src: '/favicon.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' },
        { src: '/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any maskable' },
        { src: '/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any maskable' },
      ],
      shortcuts: copy.shortcuts,
      share_target: {
        action: '/share',
        method: 'POST',
        enctype: 'multipart/form-data',
        params: {
          title: 'title',
          text: 'text',
          url: 'url',
          files: [{ name: 'media', accept: ['image/*', 'video/*'] }],
        },
      },
      prefer_related_applications: false,
      related_applications: [],
    },
    { headers: { 'Cache-Control': 'public, max-age=300', Vary: 'Accept-Language' } },
  )
}
