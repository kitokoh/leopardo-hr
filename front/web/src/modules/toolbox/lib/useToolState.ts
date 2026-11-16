'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import {
  SHARE_PARAM,
  buildShareUrl,
  decodeToolState,
  mergeWithDefaults,
  type ToolStateValues,
} from './share';

/**
 * État de formulaire synchronisé avec l'URL (`?s=…`).
 *
 * Permet à un visiteur de **partager un calcul par lien** sans compte : l'URL
 * porte les entrées, le calcul est rejoué localement à l'ouverture.
 *
 * Choix d'implémentation :
 * - l'URL est lue **au montage** (jamais au rendu serveur) : `window` n'existe
 *   pas côté serveur, et lire l'URL au premier rendu client casserait
 *   l'hydratation ;
 * - l'URL est écrite avec `history.replaceState` (pas `pushState`) : partager
 *   ne doit pas empiler une entrée d'historique par frappe ;
 * - l'écriture n'a lieu qu'après une **première modification** : ouvrir un outil
 *   ne pollue pas l'URL avec les valeurs par défaut.
 *
 * ## Pourquoi `removeValue` existe (issue #9531)
 *
 * Un formulaire dont l'état est une LISTE (TVA multi-taux) aplatit ses lignes
 * sous des clés indexées (`amount0`, `rate0`, …). En supprimant une ligne, il ne
 * suffit pas de réécrire les clés restantes : les clés devenues orphelines
 * resteraient dans l'état, et une relecture ordonnée les ferait réapparaître
 * (lignes fantômes, total faux). `removeValue` supprime réellement la clé.
 */
/**
 * Motif de clé additionnelle accepté à l'hydratation (`mergeWithDefaults`).
 *
 * Un formulaire à état de longueur variable (TVA multi-taux) doit le déclarer,
 * sinon un lien partagé portant plus d'entrées que les valeurs par défaut en
 * perd silencieusement (issue #9531).
 */
export interface UseToolStateOptions {
  /** Motif des clés acceptées au-delà des valeurs par défaut (ex. `/^(amount|rate)\d+$/`). */
  extraKeyPattern?: RegExp;
}

export function useToolState(
  defaults: ToolStateValues,
  options: UseToolStateOptions = {},
): [
  ToolStateValues,
  (key: string, value: string) => void,
  (keys: string[]) => void,
] {
  const [values, setValues] = useState<ToolStateValues>(defaults);
  const defaultsRef = useRef(defaults);
  const extraKeyPatternRef = useRef(options.extraKeyPattern);
  const hydrated = useRef(false);
  const dirty = useRef(false);

  // Hydratation depuis l'URL — une seule fois, après le montage.
  useEffect(() => {
    if (hydrated.current) return;
    hydrated.current = true;

    if (typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);
    const decoded = decodeToolState(params.get(SHARE_PARAM));
    if (decoded) {
      setValues(mergeWithDefaults(decoded, defaultsRef.current, extraKeyPatternRef.current));
    }
  }, []);

  // Réécriture de l'URL — uniquement après une saisie de l'utilisateur.
  useEffect(() => {
    if (!dirty.current) return;
    if (typeof window === 'undefined') return;

    try {
      const url = buildShareUrl(window.location.href, values);
      window.history.replaceState(window.history.state, '', url);
    } catch {
      // URL non constructible : l'état reste en mémoire, rien n'échoue.
    }
  }, [values]);

  const setValue = useCallback((key: string, value: string) => {
    dirty.current = true;
    setValues((previous) => ({ ...previous, [key]: value }));
  }, []);

  /**
   * Supprime des clés de l'état (et donc de l'URL au rendu suivant).
   *
   * Une clé absente est ignorée : appeler `removeValue` avec une liste calculée
   * (`pruneOrphanKeys`) reste sûr même si l'état a déjà été nettoyé.
   */
  const removeValue = useCallback((keys: string[]) => {
    if (keys.length === 0) return;

    dirty.current = true;
    setValues((previous) => {
      const next = { ...previous };
      for (const key of keys) delete next[key];
      return next;
    });
  }, []);

  return [values, setValue, removeValue];
}
