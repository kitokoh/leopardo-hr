import { describe, it, expect } from 'vitest'
import { buildDemoPersonas, countDemoPersonas } from '@/services/demoPersonas'

/**
 * #7402 — l'admin dashboard poste vers `/platform/auth/login`, qui ne connaît
 * que la table `super_admins`. Un persona `web-manager` est donc
 * structurellement inconnexible depuis cette console : il ne doit plus être
 * rendu (il échouait systématiquement en INVALID_CREDENTIALS).
 */
const DEMO_USERS = {
  data: {
    super_admin: {
      label: 'Super Administrateur',
      email: 'admin@leopardo-rh.com',
      password: 'password123',
      surface: 'admin-platform',
      primary_path: '/platform',
    },
    companies: [
      {
        name: 'TechCorp Algerie SARL',
        users: [
          {
            email: 'ahmed.benali@techcorp-algerie.dz',
            name: 'Ahmed Benali',
            password: 'password123',
            manager_role: 'principal',
            surface: 'web-manager',
            primary_path: '/dashboard',
          },
        ],
      },
    ],
  },
}

describe('buildDemoPersonas (#7402)', () => {
  it('ne rend QUE les personas de surface admin-platform', () => {
    const personas = buildDemoPersonas(DEMO_USERS)
    expect(personas).toHaveLength(1)
    expect(personas[0].email).toBe('admin@leopardo-rh.com')
    expect(personas[0].password).toBe('password123')
    expect(personas.map((p) => p.email)).not.toContain('ahmed.benali@techcorp-algerie.dz')
  })

  it('compte bien tous les personas publiés (pour expliquer leur absence)', () => {
    expect(countDemoPersonas(DEMO_USERS)).toBe(2)
    expect(buildDemoPersonas(DEMO_USERS)).toHaveLength(1)
  })

  it('accepte un corps sans enveloppe `data`', () => {
    const personas = buildDemoPersonas(DEMO_USERS.data)
    expect(personas).toHaveLength(1)
  })

  it("retombe sur la surface admin quand l'API ne publie pas `surface` (super_admin)", () => {
    const personas = buildDemoPersonas({
      data: {
        super_admin: { email: 'admin@leopardo-rh.com', password: 'password123' },
        companies: [{ name: 'X', users: [{ email: 'a@b.c', password: 'p' }] }],
      },
    })
    expect(personas).toHaveLength(1)
    expect(personas[0].email).toBe('admin@leopardo-rh.com')
  })

  it('ignore un persona sans mot de passe publié', () => {
    const personas = buildDemoPersonas({ data: { super_admin: { email: 'admin@leopardo-rh.com' } } })
    expect(personas).toHaveLength(0)
    expect(countDemoPersonas({ data: { super_admin: { email: 'admin@leopardo-rh.com' } } })).toBe(0)
  })

  it('rend un persona admin-platform listé côté entreprise (surface explicite)', () => {
    const personas = buildDemoPersonas({
      data: {
        companies: [
          {
            name: 'Plateforme',
            users: [{ email: 'ops@leopardo-rh.com', password: 'password123', surface: 'admin-platform' }],
          },
        ],
      },
    })
    expect(personas).toHaveLength(1)
    expect(personas[0].badge).toBe('Plateforme')
  })

  it('utilise les libellés i18n fournis', () => {
    const personas = buildDemoPersonas(
      { data: { super_admin: { email: 'a@b.c', password: 'p', surface: 'admin-platform' } } },
      { t: (key, fallback) => `${key}:${fallback}` }
    )
    expect(personas[0].label).toBe('auth.demo_super_admin_label:Super Administrateur')
    expect(personas[0].badge).toBe('auth.demo_badge_platform:Plateforme')
  })

  it('supporte une réponse vide ou inattendue', () => {
    expect(buildDemoPersonas(null)).toEqual([])
    expect(buildDemoPersonas({ data: { companies: 'oops' } })).toEqual([])
  })
})
