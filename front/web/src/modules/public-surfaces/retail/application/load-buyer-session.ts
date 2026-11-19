/**
 * Cas d'usage — verticale Retail : le compte acheteur.
 *
 * Repris de `front/marketplace/src/hooks/useBuyer.ts` (#9627).
 *
 * Le hook d'origine mélangeait trois responsabilités : migration douce du
 * jeton legacy, restauration de session, et gestion du 401. Les deux premières
 * sont ici (testables), la troisième reste au composant qui sait où rediriger.
 */

import {
  clearBuyerSession,
  readBuyerSession,
  takeLegacyBuyerToken,
  writeBuyerSession,
  type BuyerProfile,
  type BuyerSession,
} from '../domain/buyer';
import { fetchBuyerProfile, restoreBuyerSession } from '../infrastructure/account-gateway';

/**
 * Restaure la session au montage.
 *
 * Trois cas, dans cet ordre :
 *
 * 1. **Jeton legacy** présent → échangé une fois contre le cookie, puis purgé.
 *    C'est la migration #8096 : un utilisateur qui revient avec une session
 *    d'avant le passage au cookie ne perd pas son compte.
 * 2. **Profil local** présent → on l'affiche immédiatement (indice), puis on
 *    confirme auprès de l'API. Un 401 purge l'indice.
 * 3. **Rien** → pas de session.
 *
 * Ne lève jamais : une session illisible n'est pas une erreur pour l'acheteur.
 */
export async function restoreSession(): Promise<BuyerSession | null> {
  const legacyToken = takeLegacyBuyerToken();

  if (legacyToken !== null) {
    try {
      const buyer = await restoreBuyerSession(legacyToken);
      const session: BuyerSession = { buyer };
      writeBuyerSession(session);

      return session;
    } catch {
      // Jeton refusé : la session est morte, on repart à zéro.
      clearBuyerSession();

      return null;
    }
  }

  const local = readBuyerSession();

  if (local === null) {
    return null;
  }

  try {
    const buyer = await fetchBuyerProfile();
    const session: BuyerSession = { buyer };
    writeBuyerSession(session);

    return session;
  } catch {
    // Cookie expiré : l'indice local ne vaut plus rien.
    clearBuyerSession();

    return null;
  }
}

/** Enregistre une session après connexion ou inscription. */
export function persistSession(buyer: BuyerProfile): BuyerSession {
  const session: BuyerSession = { buyer };
  writeBuyerSession(session);

  return session;
}

/** Purge la session locale (la déconnexion API est faite par l'appelant). */
export function forgetSession(): void {
  clearBuyerSession();
}
