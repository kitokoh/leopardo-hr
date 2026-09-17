import { FORM_RENDERED_AT_FIELD, HONEYPOT_FIELD } from './antispam-fields';

/**
 * #7594 — champs anti-bot à joindre aux soumissions publiques.
 *
 * `form_rendered_at` est fixé à l'**initialisation du module**, c'est-à-dire au
 * chargement de la page : c'est l'instant le plus proche du rendu du formulaire
 * qu'on puisse obtenir sans instrumenter chaque composant, et il suffit au
 * time-trap. Un composant qui voudrait une mesure plus fine peut surcharger la
 * valeur (`antispamFields({ renderedAt })`).
 *
 * Le honeypot est envoyé **vide** : c'est sa valeur non vide qui dénonce un
 * robot. Il doit aussi être présent dans le DOM (`HoneypotField`), sinon seuls
 * les robots qui ajoutent le champ à l'aveugle sont pris.
 */
const pageRenderedAt = new Date().toISOString();

export function antispamFields(overrides: { renderedAt?: string } = {}): Record<string, string> {
  return {
    [HONEYPOT_FIELD]: '',
    [FORM_RENDERED_AT_FIELD]: overrides.renderedAt ?? pageRenderedAt,
  };
}
