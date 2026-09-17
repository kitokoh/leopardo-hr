import { HONEYPOT_FIELD } from '@/modules/vitrine/lib/antispam-fields';

/**
 * #7594 — champ leurre anti-robot.
 *
 * Un robot qui remplit « tous les champs » du DOM remplit aussi celui-ci ; un
 * humain ne le voit pas et ne peut pas le remplir. Côté serveur, une valeur non
 * vide suffit à classer la soumission en spam (voir `_lib/antispam.ts`).
 *
 * Trois précautions, chacune pour une raison précise :
 *
 * - **`aria-hidden` sur le conteneur** : sans lui, un lecteur d'écran annoncerait
 *   un champ que l'utilisateur ne doit surtout pas remplir — le leurre
 *   deviendrait une barrière d'accessibilité.
 * - **`tabIndex={-1}`** : hors du parcours clavier, donc jamais atteignable au
 *   `Tab`. Un piège qui capture un utilisateur au clavier est un bug, pas une
 *   protection.
 * - **`autoComplete="off"`** : l'autocomplétion du navigateur pourrait remplir un
 *   champ au nom plausible et faire passer un vrai prospect pour un robot. C'est
 *   le faux positif classique du honeypot.
 *
 * Il n'y a **aucun texte** dans ce composant : rien à traduire, et rien qui
 * puisse déclencher la garde des chaînes codées en dur. Le nom du champ est
 * exporté (`HONEYPOT_FIELD`) pour que le serveur et le client ne divergent
 * jamais.
 */
export function HoneypotField() {
  return (
    <div
      aria-hidden="true"
      className="pointer-events-none absolute -left-[9999px] top-0 h-px w-px overflow-hidden"
    >
      <input
        type="text"
        name={HONEYPOT_FIELD}
        id={`hp-${HONEYPOT_FIELD}`}
        tabIndex={-1}
        autoComplete="off"
        defaultValue=""
      />
    </div>
  );
}
