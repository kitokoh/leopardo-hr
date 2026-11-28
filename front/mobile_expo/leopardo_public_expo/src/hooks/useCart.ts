import { useCallback, useMemo, useState } from 'react';

export type CartLine = { key: string; label: string; unitMinor: number; quantity: number };

/**
 * Panier local d'une verticale.
 *
 * Le panier ne calcule JAMAIS le total affiche comme s'il etait contractuel :
 * le serveur relit les prix en base et renvoie le total reel. Le total local
 * n'est qu'une estimation d'affichage, et l'ecran de confirmation affiche le
 * montant SERVEUR.
 */
export function useCart() {
  const [lines, setLines] = useState<CartLine[]>([]);

  const add = useCallback((line: Omit<CartLine, 'quantity'>, quantity = 1) => {
    setLines((previous) => {
      const existing = previous.find((item) => item.key === line.key);
      if (existing) {
        return previous.map((item) =>
          item.key === line.key ? { ...item, quantity: item.quantity + quantity } : item,
        );
      }
      return [...previous, { ...line, quantity }];
    });
  }, []);

  const decrement = useCallback((key: string) => {
    setLines((previous) =>
      previous.flatMap((item) => {
        if (item.key !== key) return [item];
        if (item.quantity <= 1) return [];
        return [{ ...item, quantity: item.quantity - 1 }];
      }),
    );
  }, []);

  const remove = useCallback((key: string) => {
    setLines((previous) => previous.filter((item) => item.key !== key));
  }, []);

  const clear = useCallback(() => setLines([]), []);

  const count = useMemo(() => lines.reduce((sum, line) => sum + line.quantity, 0), [lines]);
  const totalMinor = useMemo(
    () => lines.reduce((sum, line) => sum + line.unitMinor * line.quantity, 0),
    [lines],
  );

  return { lines, add, decrement, remove, clear, count, totalMinor };
}
