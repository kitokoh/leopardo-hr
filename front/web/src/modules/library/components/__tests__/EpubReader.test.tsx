import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { EpubReader } from '../EpubReader';

/**
 * Tests du lecteur EPUB.
 *
 * Sous jsdom, `fetch` échoue : on vérifie donc le comportement de l'interface
 * AVANT chargement (barre d'outils, navigation, recherche) et l'état d'erreur
 * propre — jamais un écran blanc ni une exception non gérée. L'extraction
 * elle-même est couverte par `lib/epub.test.ts` sur de vraies archives ZIP.
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

describe('EpubReader', () => {
  it('affiche la barre d’outils et l’état de chargement', () => {
    render(<EpubReader slug="s" title="Mon EPUB" fileUrl="/x.epub" copy={copy} />);

    expect(screen.getByText('Mon EPUB')).toBeInTheDocument();
    expect(screen.getByLabelText('Zoom avant')).toBeInTheDocument();
    expect(screen.getByLabelText('Zoom arrière')).toBeInTheDocument();
    expect(screen.getByLabelText('Plein écran')).toBeInTheDocument();
    expect(screen.getByLabelText('Rechercher')).toBeInTheDocument();
  });

  it('bascule en plein écran au clic', () => {
    render(<EpubReader slug="s" title="Mon EPUB" fileUrl="/x.epub" copy={copy} />);

    fireEvent.click(screen.getByLabelText('Plein écran'));
    expect(screen.getByLabelText('Quitter le plein écran')).toBeInTheDocument();
  });

  it('ouvre et referme le panneau de recherche', () => {
    render(<EpubReader slug="s" title="Mon EPUB" fileUrl="/x.epub" copy={copy} />);

    fireEvent.click(screen.getByLabelText('Rechercher'));
    expect(screen.getByPlaceholderText('Rechercher…')).toBeInTheDocument();

    fireEvent.click(screen.getByLabelText('Fermer'));
    expect(screen.queryByPlaceholderText('Rechercher…')).not.toBeInTheDocument();
  });

  it('affiche un état d’erreur propre si le fichier ne peut pas être chargé', async () => {
    render(<EpubReader slug="s" title="Mon EPUB" fileUrl="/x.epub" copy={copy} />);

    expect(await screen.findByText('Impossible de charger le document.')).toBeInTheDocument();
  });

  it('appelle onFallback quand le chargement échoue', async () => {
    const onFallback = jest.fn();
    render(
      <EpubReader slug="s" title="Mon EPUB" fileUrl="/x.epub" copy={copy} onFallback={onFallback} />,
    );

    await screen.findByText('Impossible de charger le document.');
    expect(onFallback).toHaveBeenCalled();
  });
});
