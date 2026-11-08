import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { LibraryReader } from '../LibraryReader';

/**
 * Tests du lecteur numérique — navigation, zoom, recherche, progression.
 */
const pages = ['Page une contenu.', 'Page deux contenu.', 'Page trois contenu.'];

const copy = {
  title: 'Lecteur',
  page: 'Page',
  of: 'sur',
  zoomIn: 'Zoom avant',
  zoomOut: 'Zoom arrière',
  fullscreen: 'Plein écran',
  exitFullscreen: 'Quitter le plein écran',
  search: 'Rechercher dans le document',
  searchPlaceholder: 'Rechercher…',
  noMatches: 'Aucun résultat dans le document.',
  resume: 'Reprendre la lecture',
  lastPosition: 'Dernière position enregistrée',
  close: 'Fermer',
};

describe('LibraryReader', () => {
  it('affiche la première page et le compteur', () => {
    render(<LibraryReader slug="s" title="Titre" pages={pages} copy={copy} />);
    expect(screen.getByText('Page une contenu.')).toBeInTheDocument();
    expect(screen.getByText(/Page 1 sur 3/)).toBeInTheDocument();
  });

  it('navigue vers la page suivante', () => {
    render(<LibraryReader slug="s" title="Titre" pages={pages} copy={copy} />);
    fireEvent.click(screen.getByRole('button', { name: /Page 2/ }));
    expect(screen.getByText('Page deux contenu.')).toBeInTheDocument();
  });

  it('désactive le bouton précédent sur la première page', () => {
    render(<LibraryReader slug="s" title="Titre" pages={pages} copy={copy} />);
    expect(screen.getByRole('button', { name: /Page 1/ })).toBeDisabled();
  });

  it('change le niveau de zoom', () => {
    render(<LibraryReader slug="s" title="Titre" pages={pages} copy={copy} />);
    expect(screen.getByText('100%')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Zoom avant' }));
    expect(screen.getByText('125%')).toBeInTheDocument();
  });

  it('recherche dans le document et saute à la page trouvée', () => {
    render(<LibraryReader slug="s" title="Titre" pages={pages} copy={copy} />);
    fireEvent.click(screen.getByRole('button', { name: 'Rechercher dans le document' }));
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'trois' } });
    fireEvent.click(screen.getByRole('button', { name: /Page 3/ }));
    expect(screen.getByText('Page trois contenu.')).toBeInTheDocument();
  });

  it('affiche « aucun résultat » quand la recherche ne trouve rien', () => {
    render(<LibraryReader slug="s" title="Titre" pages={pages} copy={copy} />);
    fireEvent.click(screen.getByRole('button', { name: 'Rechercher dans le document' }));
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'zzz' } });
    expect(screen.getByText('Aucun résultat dans le document.')).toBeInTheDocument();
  });

  it('reprend à la position initiale fournie', () => {
    render(
      <LibraryReader
        slug="s"
        title="Titre"
        pages={pages}
        initialProgress={{ slug: 's', page: 2, totalPages: 3, updatedAt: 1 }}
        copy={copy}
      />,
    );
    expect(screen.getByText('Page deux contenu.')).toBeInTheDocument();
  });
});
