import { ImageResponse } from 'next/og'

export const alt = 'Leopardo RH - plateforme RH web, mobile et kiosque'
export const size = {
  width: 1200,
  height: 630,
}

export const contentType = 'image/png'

export default function Image() {
  return new ImageResponse(
    (
      <div
        style={{
          alignItems: 'center',
          background: 'linear-gradient(135deg, #06151f 0%, #0f2f2c 46%, #0e7490 100%)',
          color: 'white',
          display: 'flex',
          height: '100%',
          justifyContent: 'space-between',
          padding: '76px',
          width: '100%',
        }}
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: '28px', width: '58%' }}>
          <div
            style={{
              alignItems: 'center',
              display: 'flex',
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
            <div style={{ fontSize: '64px', fontWeight: 900, lineHeight: 1.02 }}>
              Pilotez vos RH sans friction.
            </div>
            <div style={{ color: '#d7fff0', fontSize: '29px', lineHeight: 1.35 }}>
              Pointage, paie, absences, onboarding, notifications et espace client en une seule plateforme SaaS.
            </div>
          </div>

          <div style={{ display: 'flex', gap: '14px' }}>
            {['Web', 'Mobile', 'Kiosque', 'IA-ready'].map((item) => (
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
          {[
            ['500+', 'entreprises accompagnees'],
            ['50K+', 'employes geres'],
            ['99.9%', 'disponibilite cible'],
          ].map(([value, label]) => (
            <div
              key={value}
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
    size,
  )
}
