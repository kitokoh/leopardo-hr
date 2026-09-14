/**
 * errorMessage — message utilisateur d'une erreur d'action (issue #7433).
 *
 * Aucun chemin d'action utilisateur ne doit échouer en silence : toute erreur
 * doit produire un message affiché (toast, bandeau ou champ d'erreur du
 * formulaire). Cette fonction normalise la forme du message — message API
 * localisé, `message`, ou texte d'exception en dernier recours.
 */
export function errorMessage(error, fallback = '') {
  const apiMessage = error?.response?.data?.message
  if (typeof apiMessage === 'string' && apiMessage.trim() !== '') {
    return apiMessage
  }
  const localized = error?.response?.data?.localized_message
  if (typeof localized === 'string' && localized.trim() !== '') {
    return localized
  }
  const errors = error?.response?.data?.errors
  if (errors && typeof errors === 'object') {
    const flat = Object.values(errors).flat().filter((item) => typeof item === 'string')
    if (flat.length > 0) {
      return flat.join(' ')
    }
  }
  if (error?.message) {
    return String(error.message)
  }
  return fallback
}
