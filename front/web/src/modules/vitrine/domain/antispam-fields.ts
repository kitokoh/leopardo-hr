/**
 * #7594 — constantes anti-bot partagées entre le serveur et le client.
 *
 * Module **sans dépendance** (ni `next/server`, ni React) : il est importé des
 * deux côtés. La garde serveur `_lib/antispam.ts` ne peut pas être importée par
 * un composant client — elle tire `NextRequest`/`NextResponse` — d'où ce
 * fichier séparé pour les valeurs communes.
 */

/**
 * Champ leurre. Le nom imite un champ plausible — c'est ce qui le rend
 * attractif pour un robot qui remplit tout le formulaire.
 */
export const HONEYPOT_FIELD = 'lp_website_url';

/**
 * Horodatage du **RENDU** du formulaire (et non de sa soumission).
 *
 * ⚠️ Piège trouvé en câblant #7594 : les fronts posaient déjà un `timestamp`,
 * mais **au moment de la soumission**. Comparer cette valeur revient à comparer
 * zéro à zéro — un humain comme un robot sont « instantanés » — donc un
 * contrôle « trop rapide » sur ce champ rejetterait **tous** les vrais
 * utilisateurs. Un time-trap n'a de sens que sur l'écart entre le rendu et
 * l'envoi.
 */
export const FORM_RENDERED_AT_FIELD = 'form_rendered_at';

/** En dessous de ce délai, un être humain n'a pas pu remplir le formulaire. */
export const MIN_FILL_DURATION_MS = 3000;
