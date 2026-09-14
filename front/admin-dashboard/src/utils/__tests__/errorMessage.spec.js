import { describe, it, expect } from 'vitest'
import { errorMessage } from '@/utils/errorMessage'

/**
 * #7433 — « jamais de `catch` muet » : le message produit ici est ce que
 * l'utilisateur voit quand une action échoue. Il doit toujours être non vide
 * quand l'erreur porte une information exploitable.
 */
describe('errorMessage', () => {
  it('privilégie le message API', () => {
    expect(errorMessage({ response: { data: { message: 'Suppression refusée' } } })).toBe('Suppression refusée')
  })

  it('retombe sur localized_message', () => {
    expect(errorMessage({ response: { data: { localized_message: 'Accès refusé' } } })).toBe('Accès refusé')
  })

  it('aplatit les erreurs de validation Laravel', () => {
    const error = { response: { data: { errors: { name: ['Requis', 'Trop court'], code: ['Unique'] } } } }
    expect(errorMessage(error)).toBe('Requis Trop court Unique')
  })

  it('retombe sur le message d’exception', () => {
    expect(errorMessage(new Error('Network Error'))).toBe('Network Error')
  })

  it('retombe sur le fallback fourni par la vue', () => {
    expect(errorMessage({}, 'Enregistrement impossible.')).toBe('Enregistrement impossible.')
  })
})
