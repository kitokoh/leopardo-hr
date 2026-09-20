'use client';

import { useEffect, useRef } from 'react';

/**
 * #7556 — Verrou de défilement partagé par les surfaces superposées (tiroir,
 * panneaux de la barre, menu du compte). Compté plutôt que posé à `hidden` en
 * aveugle : deux surfaces ouvertes en même temps ne doivent pas se rendre la
 * main l'une à l'autre un `overflow` intermédiaire (le dernier fermé restitue
 * la valeur d'origine du document).
 *
 * Extrait du monolithe `(dashboard)/layout.tsx` par la refonte #7908 : la
 * sidebar unifiée et son menu de compte consomment le même contrat.
 */
let overlayScrollLocks = 0;
let overflowBeforeFirstLock = '';

export function lockDocumentScroll(): () => void {
  if (overlayScrollLocks === 0) {
    overflowBeforeFirstLock = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
  }
  overlayScrollLocks += 1;
  let released = false;

  return () => {
    if (released) {
      return;
    }
    released = true;
    overlayScrollLocks = Math.max(0, overlayScrollLocks - 1);
    if (overlayScrollLocks === 0) {
      document.body.style.overflow = overflowBeforeFirstLock;
    }
  };
}

/**
 * #7556 — tout panneau déroulant (tiroir de navigation, notifications, menu
 * du compte) se referme par Échap et verrouille le défilement du document
 * tant qu'il est ouvert.
 *
 * `onClose` est lu via une ref : l'effet ne se réabonne pas à chaque rendu.
 */
export function usePanelDismiss(open: boolean, onClose: () => void): void {
  const closeRef = useRef(onClose);

  useEffect(() => {
    closeRef.current = onClose;
  }, [onClose]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        closeRef.current();
      }
    };

    window.addEventListener('keydown', onKeyDown);
    const unlock = lockDocumentScroll();

    return () => {
      window.removeEventListener('keydown', onKeyDown);
      unlock();
    };
  }, [open]);
}
