"use client";

import { useEffect, useRef } from "react";

/**
 * useFocusTrap — WCAG 2.1.1 / 2.1.2 / 2.4.3.
 *
 * Piège le focus clavier à l'intérieur d'un dialogue : Tab / Shift+Tab cyclent
 * entre les éléments focusables du conteneur, le focus est déplacé dans le
 * dialogue à l'ouverture et restauré sur l'élément précédent à la fermeture.
 * `Escape` ferme le dialogue via `onEscape` (optionnel).
 *
 * Usage :
 *   const ref = useFocusTrap(open, () => setOpen(false));
 *   <div ref={ref} role="dialog" aria-modal="true">…</div>
 *
 * @param isActive  état d'ouverture du dialogue.
 * @param onEscape  callback appelé sur la touche Échap (optionnel).
 * @returns ref à poser sur le conteneur du dialogue.
 */
export function useFocusTrap<T extends HTMLElement = HTMLDivElement>(
  isActive: boolean,
  onEscape?: () => void,
) {
  const containerRef = useRef<T | null>(null);
  const previouslyFocused = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!isActive) return;

    const container = containerRef.current;
    if (!container) return;

    const FOCUSABLE_SELECTOR = [
      "a[href]",
      "button:not([disabled])",
      "textarea:not([disabled])",
      "input:not([disabled])",
      "select:not([disabled])",
      '[tabindex]:not([tabindex="-1"])',
    ].join(", ");

    const getFocusable = () =>
      Array.from(container.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)).filter(
        (node) => node.offsetParent !== null || node === document.activeElement,
      );

    previouslyFocused.current =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;

    container.setAttribute("tabindex", "-1");
    const items = getFocusable();
    (items[0] ?? container).focus();

    const onKeydown = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        onEscape?.();
        return;
      }
      if (e.key !== "Tab") return;

      const focusable = getFocusable();
      if (focusable.length === 0) {
        e.preventDefault();
        return;
      }
      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    };

    container.addEventListener("keydown", onKeydown);

    return () => {
      container.removeEventListener("keydown", onKeydown);
      previouslyFocused.current?.focus?.();
      previouslyFocused.current = null;
    };
  }, [isActive, onEscape]);

  return containerRef;
}
