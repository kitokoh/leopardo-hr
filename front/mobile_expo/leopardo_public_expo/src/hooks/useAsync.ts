import { useCallback, useEffect, useRef, useState } from 'react';
import { PublicApiFailure, classifyFailure } from '../api/client';

type State<T> = {
  data: T | null;
  failure: PublicApiFailure | null;
  loading: boolean;
  refreshing: boolean;
};

/**
 * Chargement asynchrone avec les QUATRE etats d'ecran.
 *
 * `reload` conserve les donnees deja affichees pendant un rafraichissement :
 * un pull-to-refresh ne doit pas faire clignoter la liste vers un spinner
 * plein ecran. Seul le PREMIER chargement montre l'etat `loading`.
 *
 * La reponse d'une requete perimee est ignoree (`requestId`) : sans cela, un
 * changement rapide de filtre peut afficher le resultat de la requete
 * precedente, arrivee en retard.
 */
export function useAsync<T>(
  loader: () => Promise<T>,
  deps: readonly unknown[],
): State<T> & { reload: () => void; retry: () => void } {
  const [state, setState] = useState<State<T>>({
    data: null,
    failure: null,
    loading: true,
    refreshing: false,
  });

  const requestId = useRef(0);
  const mounted = useRef(true);

  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
    };
  }, []);

  const run = useCallback(
    (mode: 'initial' | 'refresh') => {
      const id = requestId.current + 1;
      requestId.current = id;

      setState((previous) => ({
        ...previous,
        loading: mode === 'initial' && previous.data === null,
        refreshing: mode === 'refresh',
        failure: mode === 'initial' ? null : previous.failure,
      }));

      loader()
        .then((data) => {
          if (!mounted.current || requestId.current !== id) return;
          setState({ data, failure: null, loading: false, refreshing: false });
        })
        .catch((error: unknown) => {
          if (!mounted.current || requestId.current !== id) return;
          setState((previous) => ({
            data: previous.data,
            failure: classifyFailure(error),
            loading: false,
            refreshing: false,
          }));
        });
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    deps,
  );

  useEffect(() => {
    run('initial');
  }, [run]);

  return {
    ...state,
    reload: () => run('refresh'),
    retry: () => run('initial'),
  };
}
