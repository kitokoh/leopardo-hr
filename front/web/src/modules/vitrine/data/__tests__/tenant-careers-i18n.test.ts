import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import {
  tenantCareersByLocale,
  getTenantCareersCopy,
  tenantCareersMetaTitle,
  tenantCareersMetaDescription,
  tenantJobMetaTitle,
} from '../tenant-careers'
import type { AppLocale } from '@/lib/i18n'

// Issue #4448 : les portails carrières publics par tenant ([companySlug]/careers*)
// doivent rester 100 % localisés. Ce test garde (a) l'égalité des clés entre
// les 4 locales et (b) l'absence de littéraux FR orphelins dans les pages.

const LOCALES: AppLocale[] = ['fr', 'en', 'tr', 'ar']

function deepKeys(obj: unknown, prefix = ''): string[] {
  if (typeof obj !== 'object' || obj === null) return []
  return Object.entries(obj as Record<string, unknown>).flatMap(([k, v]) => [
    prefix ? `${prefix}.${k}` : k,
    ...deepKeys(v, prefix ? `${prefix}.${k}` : k),
  ])
}

describe('tenant careers i18n (#4448)', () => {
  it('expose les 4 locales de la vitrine', () => {
    for (const locale of LOCALES) {
      expect(tenantCareersByLocale[locale]).toBeDefined()
    }
  })

  it('a des clés strictement identiques entre toutes les locales', () => {
    const reference = deepKeys(tenantCareersByLocale.en).sort()
    for (const locale of LOCALES) {
      const keys = deepKeys(tenantCareersByLocale[locale]).sort()
      expect(keys).toEqual(reference)
    }
  })

  it('aucune chaîne vide dans les locales non-fr (traductions complètes)', () => {
    const nonEmpty = (obj: unknown): string[] => {
      if (typeof obj === 'string') return obj.trim() ? [] : ['<empty string>']
      if (typeof obj !== 'object' || obj === null) return []
      return Object.values(obj as Record<string, unknown>).flatMap((v) => nonEmpty(v))
    }
    for (const locale of ['en', 'tr', 'ar'] as AppLocale[]) {
      expect(nonEmpty(tenantCareersByLocale[locale])).toEqual([])
    }
  })

  it('retombe sur fr pour une locale inconnue', () => {
    expect(getTenantCareersCopy('xx' as AppLocale)).toBe(tenantCareersByLocale.fr)
  })

  it('les helpers metadata restent localisés', () => {
    expect(tenantCareersMetaTitle('en', 'Acme', 3)).toContain('open positions')
    expect(tenantCareersMetaTitle('fr', 'Acme', 1)).toContain('poste')
    expect(tenantCareersMetaDescription('ar', 'Acme')).toContain('فرص العمل')
    expect(tenantJobMetaTitle('tr', 'Dev', 'Acme')).toBe('Dev - Acme')
  })

  it('ne laisse aucun littéral FR orphelin dans les pages carrières par tenant', () => {
    const repoRoot = join(__dirname, '..', '..', '..', '..', '..')
    const pages = [
      join(repoRoot, 'src/app/[companySlug]/careers/page.tsx'),
      join(repoRoot, 'src/app/[companySlug]/careers/jobs/[jobId]/page.tsx'),
      join(repoRoot, 'src/app/[companySlug]/careers/jobs/[jobId]/ApplyForm.tsx'),
    ]
    const frTokens = [
      'Carrieres',
      'Rejoignez',
      'actuellement ouvert',
      'Aucun poste ouvert',
      'publiee pour le moment',
      'Flux XML',
      'Retour aux offres',
      'Competences recherchees',
      'Postuler a cette offre',
      'Portail carrieres introuvable',
      'Offre introuvable',
      'Prenom',
      'Telephone (optionnel)',
      'Lettre de motivation',
      'Choisir un fichier',
      'depasser 5 Mo',
      'Envoyer ma candidature',
      'Envoi en cours',
      'Candidature envoyee',
      'Merci pour votre interet',
      'reessayer',
      'notre equipe',
    ]
    for (const page of pages) {
      const content = readFileSync(page, 'utf-8')
      const withoutComments = content
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .split('\n')
        .filter((line) => !line.trim().startsWith('//'))
        .join('\n')
      for (const token of frTokens) {
        expect(withoutComments).not.toContain(token)
      }
    }
  })
})
