import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { ImageResponse } from 'next/og'
import type { AppLocale } from '@/lib/i18n'
import { getLocaleDirection, normalizeLocale } from '@/lib/i18n'

export const alt = 'Leopardo RH - plateforme RH web, mobile et kiosque'
export const size = {
  width: 1200,
  height: 630,
}

export const contentType = 'image/png'
export const runtime = 'nodejs'

const arabicFont = readFile(join(process.cwd(), 'public/fonts/NotoSansArabic-Regular.ttf'))

const LOCALES: AppLocale[] = ['fr', 'en', 'tr', 'ar']

const COPY: Record<
  AppLocale,
  {
    title: string
    subtitle: string
    stats: Array<[string, string]>
    badges: string[]
  }
> = {
  fr: {
    title: 'Pilotez vos RH sans friction.',
    subtitle: 'Pointage, paie, absences, onboarding et espace client en une seule plateforme SaaS.',
    stats: [
      ['3', 'apps mobiles'],
      ['2', 'apps web'],
      ['14 j', "d'essai gratuit"],
    ],
    badges: ['Web', 'Mobile', 'Kiosque', 'IA-ready'],
  },
  en: {
    title: 'Run HR without friction.',
    subtitle: 'Time tracking, payroll, leave, onboarding and client space in one SaaS platform.',
    stats: [
      ['3', 'mobile apps'],
      ['2', 'web apps'],
      ['14 days', 'free trial'],
    ],
    badges: ['Web', 'Mobile', 'Kiosk', 'AI-ready'],
  },
  tr: {
    title: 'İK süreçlerinizi zahmetsizce yönetin.',
    subtitle: 'Tek bir SaaS platformunda puantaj, bordro, izin, işe alım ve müşteri alanı.',
    stats: [
      ['3', 'mobil uygulama'],
      ['2', 'web uygulaması'],
      ['14 gün', 'ücretsiz deneme'],
    ],
    badges: ['Web', 'Mobil', 'Kiosk', 'Yapay zekâ hazır'],
  },
  ar: {
    title: 'أدر مواردك البشرية بلا تعقيد.',
    subtitle: 'الحضور والرواتب والإجازات والتهيئة ومساحة العميل في منصة SaaS واحدة.',
    stats: [
      ['3', 'تطبيقات جوّال'],
      ['2', 'تطبيقات ويب'],
      ['14 يومًا', 'تجربة مجانية'],
    ],
    badges: ['ويب', 'جوّال', 'كشك', 'جاهز للذكاء الاصطناعي'],
  },
}

export function generateImageMetadata() {
  return LOCALES.map((locale) => ({
    id: locale,
    alt: `Leopardo RH — ${COPY[locale].title}`,
  }))
}

export default async function Image({ id }: { id?: Promise<string | number> }) {
  const locale = normalizeLocale(id ? await id : 'fr')
  const copy = COPY[locale]
  const direction = getLocaleDirection(locale)
  const isRtl = direction === 'rtl'

  return new ImageResponse(
    (
      <div
        dir={direction}
        style={{
          alignItems: 'center',
          background: 'linear-gradient(135deg, #06151f 0%, #0f2f2c 46%, #0e7490 100%)',
          color: 'white',
          display: 'flex',
          flexDirection: isRtl ? 'row-reverse' : 'row',
          height: '100%',
          justifyContent: 'space-between',
          padding: '76px',
          textAlign: isRtl ? 'right' : 'left',
          width: '100%',
        }}
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: '28px', width: '58%' }}>
          <div
            style={{
              alignItems: 'center',
              display: 'flex',
              flexDirection: isRtl ? 'row-reverse' : 'row',
              gap: '18px',
            }}
          >
            <div
              style={{
                alignItems: 'center',
                background: '#10b981',
                borderRadius: '24px',
                display: 'flex',
                fontSize: '46px',
                fontWeight: 900,
                height: '82px',
                justifyContent: 'center',
                width: '82px',
              }}
            >
              L
            </div>
            <div style={{ display: 'flex', flexDirection: 'column' }}>
              <div style={{ fontSize: '42px', fontWeight: 900 }}>Leopardo RH</div>
              <div style={{ color: '#9ae6c8', fontSize: '20px', fontWeight: 700, letterSpacing: '3px' }}>
                HR PLATFORM
              </div>
            </div>
          </div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: '18px' }}>
            <div style={{ fontSize: '64px', fontWeight: 900, lineHeight: 1.02 }}>{copy.title}</div>
            <div style={{ color: '#d7fff0', fontSize: '29px', lineHeight: 1.35 }}>{copy.subtitle}</div>
          </div>

          <div style={{ display: 'flex', flexDirection: isRtl ? 'row-reverse' : 'row', gap: '14px' }}>
            {copy.badges.map((item) => (
              <div
                key={item}
                style={{
                  background: 'rgba(255,255,255,0.12)',
                  border: '1px solid rgba(255,255,255,0.18)',
                  borderRadius: '999px',
                  color: '#ecfeff',
                  fontSize: '22px',
                  fontWeight: 700,
                  padding: '12px 18px',
                }}
              >
                {item}
              </div>
            ))}
          </div>
        </div>

        <div
          style={{
            background: 'rgba(255,255,255,0.10)',
            border: '1px solid rgba(255,255,255,0.20)',
            borderRadius: '34px',
            display: 'flex',
            flexDirection: 'column',
            gap: '18px',
            padding: '28px',
            width: '34%',
          }}
        >
          {copy.stats.map(([value, label]) => (
            <div
              key={label}
              style={{
                background: 'rgba(6,21,31,0.45)',
                borderRadius: '22px',
                display: 'flex',
                flexDirection: 'column',
                padding: '24px',
              }}
            >
              <div style={{ color: '#6ee7b7', fontSize: '48px', fontWeight: 900 }}>{value}</div>
              <div style={{ color: '#dbeafe', fontSize: '21px', marginTop: '4px' }}>{label}</div>
            </div>
          ))}
        </div>
      </div>
    ),
    {
      ...size,
      ...(isRtl
        ? {
            fonts: [
              {
                data: Uint8Array.from(await arabicFont).buffer,
                name: 'Noto Sans Arabic',
                style: 'normal' as const,
                weight: 400 as const,
              },
            ],
          }
        : {}),
    },
  )
}
