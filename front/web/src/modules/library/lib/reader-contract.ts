/**
 * Leopardo Library — contrat commun des lecteurs de documents.
 *
 * `PdfReader` et `EpubReader` implémentent la MÊME interface : mêmes props,
 * mêmes libellés i18n, même comportement de reprise de lecture. Le point
 * d'entrée unique (`ResourceActions`) choisit l'implémentation à partir du
 * FORMAT RÉEL du fichier (`file_format` renvoyé par l'API, dérivé du MIME
 * vérifié côté serveur) — jamais de l'extension d'une URL fournie par le
 * client.
 *
 * Le repli texte (`LibraryReader`) reste le dernier recours : si le rendu
 * échoue, on ne montre jamais un écran blanc.
 */

import type { ReadingProgress } from './progress';

/** Libellés du lecteur (déjà traduits fr/en/tr/ar par l'appelant). */
export interface ReaderCopy {
  page: string;
  of: string;
  zoomIn: string;
  zoomOut: string;
  fullscreen: string;
  exitFullscreen: string;
  search: string;
  searchPlaceholder: string;
  noMatches: string;
  lastPosition: string;
  close: string;
  loading: string;
  error: string;
}

/** Props communes à tous les lecteurs de fichier. */
export interface DocumentReaderProps {
  /** Slug de la ressource (clé de progression). */
  slug: string;
  /** Titre affiché dans la barre d'outils. */
  title: string;
  /** URL du fichier (présignée ou route interne signée). */
  fileUrl: string;
  /** Progression initiale (reprise de lecture). */
  initialProgress?: ReadingProgress | null;
  /** Libellés traduits. */
  copy: ReaderCopy;
  /**
   * Appelé quand le rendu du format échoue : l'appelant bascule alors sur le
   * repli texte. Optionnel — sans lui, le lecteur affiche son état d'erreur.
   */
  onFallback?: () => void;
}

/** Formats de fichier reconnus par le module. */
export type LibraryFileFormat = 'pdf' | 'epub' | 'txt' | 'html';

/**
 * Normalise un format renvoyé par l'API (`file_format`) en format de lecteur.
 * Toute valeur inconnue retombe sur `null` → repli texte.
 */
export function normalizeFileFormat(value: string | null | undefined): LibraryFileFormat | null {
  if (typeof value !== 'string') return null;
  const normalized = value.trim().toLowerCase();
  if (normalized === 'pdf' || normalized === 'epub' || normalized === 'txt' || normalized === 'html') {
    return normalized;
  }
  return null;
}
