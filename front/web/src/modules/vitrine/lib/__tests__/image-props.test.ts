import { isVectorAsset, localImageProps } from '../image-props'

// Bug de production : les SVG passés à next/image répondaient 400
// (« dangerouslyAllowSVG is disabled ») — couvertures d'articles et avatars.
describe('isVectorAsset', () => {
  it('reconnaît les SVG servis en local', () => {
    expect(isVectorAsset('/blog/startup-rh.svg')).toBe(true)
    expect(isVectorAsset('/avatars/ahmed.svg')).toBe(true)
    expect(isVectorAsset('/AVATARS/Ahmed.SVG')).toBe(true)
  })

  it('ignore les paramètres de requête et le fragment', () => {
    expect(isVectorAsset('/logo.svg?v=2')).toBe(true)
    expect(isVectorAsset('/logo.svg#icon')).toBe(true)
  })

  it('ne classe pas les images matricielles comme vecteurs', () => {
    expect(isVectorAsset('/screenshots/web-dashboard.png')).toBe(false)
    expect(isVectorAsset('/og/default.png')).toBe(false)
    expect(isVectorAsset('/favicon.ico')).toBe(false)
    // Un nom qui contient « svg » mais pas comme extension ne doit pas tromper.
    expect(isVectorAsset('/images/svg-placeholder.png')).toBe(false)
  })

  it('tolère les sources non textuelles (StaticImport, undefined)', () => {
    expect(isVectorAsset(undefined)).toBe(false)
    expect(isVectorAsset(null)).toBe(false)
    expect(isVectorAsset({ src: '/blog/x.svg' })).toBe(false)
  })
})

describe('localImageProps', () => {
  it('désactive l’optimiseur pour un SVG', () => {
    expect(localImageProps('/blog/startup-rh.svg')).toEqual({ unoptimized: true })
  })

  it('laisse l’optimiseur actif pour les autres formats', () => {
    expect(localImageProps('/screenshots/web-dashboard.png')).toEqual({})
  })
})
