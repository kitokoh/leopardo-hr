/**
 * password-policy.ts — politique de mot de passe (source unique côté front).
 *
 * QA onboarding 2026-09-14 : la politique était éclatée et incohérente.
 *   - un helper `validatePassword()` (modules/vitrine/lib/validation.ts)
 *     exigeait 8 caractères + majuscule + chiffre + caractère spécial… mais
 *     n'était appelé par AUCUN écran : ses messages français codés en dur
 *     décrivaient une politique que rien n'appliquait (code mort trompeur) ;
 *   - l'API appliquait `Password::min(8)->numbers()` — donc `abc12345`
 *     suffisait (vérifié en live : set-password accepté, connexion réussie) ;
 *   - les écrans de réinitialisation/activation ne vérifiaient que la longueur.
 *
 * Décision : une seule longueur minimale, partagée par les écrans et alignée
 * sur la validation serveur (`Password::min(12)->numbers()`). Les messages
 * restent dans le catalogue i18n du composant appelant (aucun littéral ici,
 * garde PA2-I18N-014) : ce module ne renvoie que des faits.
 *
 * Prochaines étapes possibles (hors périmètre) : contrôle des mots de passe
 * compromis (HaveIBeenPwned k-anonymity ou liste locale) et indicateur de
 * robustesse en direct — la longueur seule reste la mesure la plus efficace
 * selon le NIST SP 800-63B.
 */

/** Longueur minimale d'un mot de passe — DOIT rester alignée sur l'API. */
export const PASSWORD_MIN_LENGTH = 12;

/** Borne haute (l'API accepte `max:255` ; on évite les saisies absurdes). */
export const PASSWORD_MAX_LENGTH = 128;

/**
 * Un mot de passe est-il acceptable ?
 *
 * Aligné sur `Password::min(12)->numbers()` : longueur minimale + au moins un
 * chiffre. Les règles de composition (majuscule, caractère spécial) ont été
 * volontairement écartées : le NIST SP 800-63B recommande la longueur, et non
 * la composition, qui pousse aux mots de passe prévisibles (« Password1! »).
 */
export function isPasswordAcceptable(password: string): boolean {
  return password.length >= PASSWORD_MIN_LENGTH && /[0-9]/.test(password);
}
