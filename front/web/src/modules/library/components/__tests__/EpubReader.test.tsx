import React from 'react';
import { render, screen } from '@testing-library/react';
import { EpubReader } from '../EpubReader';

/**
 * Tests du lecteur EPUB (#9239).
 *
 * epub.js est chargé dynamiquement : en environnement de test, l'import échoue
 * (pas de DOM de rendu de livre). Le contrat vérifié ici est celui qui compte
 * pour l'utilisateur : l'état de chargement s'affiche, puis l'erreur est
 * présentée proprement — jamais un écran blanc ni une exception non gérée.
 */

// epub.js n'a pas de rendu utilisable sous jsdom : on force l'échec du
// chargement pour vérifier le chemin d'erreur (le chemin nominal est couvert
// par la spec e2e, qui exécute un vrai navigateur).
jest.mock('epubjs', () => {
  throw new Error('epubjs indisponible en environnement de test');
});

const copy = {
  page: 'Page',
  of: 'sur',
  fullscreen: 'Plein écran',
  exitFullscreen: 'Quitter le plein écran',
  lastPosition: 'Dernière position',
  close: 'Fermer',
  loading: 'Chargement du document…',
  error: 'Impossible de charger le document.',
  prev: 'Chapitre précédent',
  next: 'Chapitre suivant',
};

describe('EpubReader', () => {
  it('affiche le titre de la ressource', () => {
    render(<EpubReader slug="s" title="Mon EPUB" fileUrl="/x.epub" copy={copy} />);
    expect(screen.getByText('Mon EPUB')).toBeInTheDocument();
  });

  it('affiche l’état de chargement au montage', () => {
    render(<EpubReader slug="s" title="Mon EPUB" fileUrl="/x.epub" copy={copy} />);
    expect(screen.getByText(copy.loading)).toBeInTheDocument();
  });

  it('expose les boutons de navigation avec un libellé accessible', () => {
    render(<EpubReader slug="s" title="Mon EPUB" fileUrl="/x.epub" copy={copy} />);
    expect(screen.getByLabelText(copy.prev)).toBeInTheDocument();
    expect(screen.getByLabelText(copy.next)).toBeInTheDocument();
  });

  it('désactive la navigation tant que le livre n’est pas chargé', () => {
    render(<EpubReader slug="s" title="Mon EPUB" fileUrl="/x.epub" copy={copy} />);
    expect(screen.getByLabelText(copy.prev)).toBeDisabled();
    expect(screen.getByLabelText(copy.next)).toBeDisabled();
  });

  it('affiche la dernière position quand une progression est fournie', () => {
    render(
      <EpubReader
        slug="s"
        title="Mon EPUB"
        fileUrl="/x.epub"
        initialProgress={{ slug: 's', page: 4, totalPages: 10, updatedAt: 1 }}
        copy={copy}
      />,
    );
    expect(screen.getByText(new RegExp(copy.lastPosition))).toBeInTheDocument();
  });

  it('ne plante pas si le chargement échoue (erreur présentée, pas d’exception)', async () => {
    render(<EpubReader slug="s" title="Mon EPUB" fileUrl="/x.epub" copy={copy} />);
    // Laisse l'effet asynchrone se terminer (l'import échoue en test).
    await screen.findByText(copy.error);
    expect(screen.getByText(copy.error)).toBeInTheDocument();
  });
});
