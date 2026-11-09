import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { PdfReader } from '../PdfReader';

/**
 * Tests du lecteur PDF (pdf.js).
 *
 * pdf.js est chargé dynamiquement : en environnement jsdom, `import('pdfjs-dist')`
 * échoue (pas de canvas natif). On vérifie donc le comportement de l'interface
 * AVANT chargement du document : barre d'outils, navigation, recherche, et
 * l'état d'erreur propre (jamais un écran blanc ni une exception non gérée).
 */

const copy = {
  page: 'Page',
  of: 'sur',
  zoomIn: 'Zoom avant',
  zoomOut: 'Zoom arrière',
  fullscreen: 'Plein écran',
  exitFullscreen: 'Quitter le plein écran',
  search: 'Rechercher',
  searchPlaceholder: 'Rechercher…',
  noMatches: 'Aucun résultat.',
  lastPosition: 'Dernière position',
  close: 'Fermer',
  loading: 'Chargement du document…',
  error: 'Impossible de charger le document.',
};

describe('PdfReader', () => {
  it('affiche la barre d’outils et l’état de chargement', async () => {
    render(<PdfReader slug="s" title="Mon PDF" fileUrl="/x.pdf" copy={copy} />);

    expect(screen.getByText('Mon PDF')).toBeInTheDocument();
    expect(screen.getByLabelText('Zoom avant')).toBeInTheDocument();
    expect(screen.getByLabelText('Zoom arrière')).toBeInTheDocument();
    expect(screen.getByLabelText('Plein écran')).toBeInTheDocument();
    expect(screen.getByLabelText('Rechercher')).toBeInTheDocument();

    // Laisse l'effet de chargement se terminer (pdf.js indisponible sous jsdom).
    await screen.findByText('Impossible de charger le document.');
  });

  it('bascule en plein écran au clic', async () => {
    render(<PdfReader slug="s" title="Mon PDF" fileUrl="/x.pdf" copy={copy} />);

    fireEvent.click(screen.getByLabelText('Plein écran'));
    expect(screen.getByLabelText('Quitter le plein écran')).toBeInTheDocument();

    await screen.findByText('Impossible de charger le document.');
  });

  it('ouvre et referme le panneau de recherche', async () => {
    render(<PdfReader slug="s" title="Mon PDF" fileUrl="/x.pdf" copy={copy} />);

    fireEvent.click(screen.getByLabelText('Rechercher'));
    expect(screen.getByPlaceholderText('Rechercher…')).toBeInTheDocument();

    fireEvent.click(screen.getByLabelText('Fermer'));
    expect(screen.queryByPlaceholderText('Rechercher…')).not.toBeInTheDocument();

    await screen.findByText('Impossible de charger le document.');
  });

  it('affiche un état d’erreur propre si le document ne peut pas être chargé', async () => {
    render(<PdfReader slug="s" title="Mon PDF" fileUrl="/x.pdf" copy={copy} />);

    // pdf.js ne peut pas s'initialiser sous jsdom → l'état d'erreur doit
    // apparaître, sans exception non gérée.
    expect(await screen.findByText('Impossible de charger le document.')).toBeInTheDocument();
  });
});
