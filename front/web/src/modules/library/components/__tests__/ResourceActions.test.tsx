import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { ResourceActions } from '../ResourceActions';
import type { AccessCapabilities } from '../../lib/access';

/**
 * Tests des actions d'une ressource — lecture, téléchargement autorisé/refusé,
 * achat, favori.
 */
const readerCopy = {
  title: 'Lecteur',
  page: 'Page',
  of: 'sur',
  zoomIn: 'Zoom avant',
  zoomOut: 'Zoom arrière',
  fullscreen: 'Plein écran',
  exitFullscreen: 'Quitter le plein écran',
  search: 'Rechercher',
  searchPlaceholder: 'Rechercher…',
  noMatches: 'Aucun résultat.',
  resume: 'Reprendre',
  lastPosition: 'Dernière position',
  close: 'Fermer',
  loading: 'Chargement du document…',
  error: 'Impossible de charger le document.',
};

const copy = {
  readOnline: 'Lire en ligne',
  download: 'Télécharger',
  downloadDisabled: 'Téléchargement non autorisé pour cette ressource.',
  buy: 'Acheter',
  subscribe: 'Inclus dans l’abonnement',
  privateAccess: 'Réservé à une organisation.',
  addFavorite: 'Ajouter aux favoris',
  removeFavorite: 'Retirer des favoris',
  notAvailable: 'Lecture en ligne non disponible pour cette ressource.',
  reader: readerCopy,
};

const freeCaps: AccessCapabilities = {
  canRead: true,
  canDownload: true,
  requiresPurchase: false,
  requiresSubscription: false,
  requiresPrivateAccess: false,
  visible: true,
};

const readOnlyCaps: AccessCapabilities = {
  ...freeCaps,
  canDownload: false,
};

const purchaseCaps: AccessCapabilities = {
  canRead: false,
  canDownload: false,
  requiresPurchase: true,
  requiresSubscription: false,
  requiresPrivateAccess: false,
  visible: true,
};

describe('ResourceActions', () => {
  it('affiche le bouton de lecture et ouvre le lecteur', () => {
    render(
      <ResourceActions slug="s" title="T" pages={['Contenu.']} capabilities={freeCaps} copy={copy} />,
    );
    fireEvent.click(screen.getByRole('button', { name: /Lire en ligne/ }));
    expect(screen.getByText('Contenu.')).toBeInTheDocument();
  });

  it('affiche le lien de téléchargement quand autorisé', () => {
    render(
      <ResourceActions slug="s" title="T" pages={['Contenu.']} capabilities={freeCaps} copy={copy} />,
    );
    expect(screen.getByRole('link', { name: /Télécharger/ })).toHaveAttribute(
      'href',
      '/library/s/download',
    );
  });

  it('affiche le refus de téléchargement quand non autorisé', () => {
    render(
      <ResourceActions slug="s" title="T" pages={['Contenu.']} capabilities={readOnlyCaps} copy={copy} />,
    );
    expect(screen.queryByRole('link', { name: /Télécharger/ })).not.toBeInTheDocument();
    expect(screen.getByText('Téléchargement non autorisé pour cette ressource.')).toBeInTheDocument();
  });

  it('affiche le CTA d’achat quand requis', () => {
    render(
      <ResourceActions slug="s" title="T" pages={[]} capabilities={purchaseCaps} copy={copy} />,
    );
    expect(screen.getByText('Acheter')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Lire en ligne/ })).not.toBeInTheDocument();
  });

  it('bascule le favori', () => {
    render(
      <ResourceActions slug="s" title="T" pages={['Contenu.']} capabilities={freeCaps} copy={copy} />,
    );
    const button = screen.getByRole('button', { name: /Ajouter aux favoris/ });
    fireEvent.click(button);
    expect(screen.getByRole('button', { name: /Retirer des favoris/ })).toBeInTheDocument();
  });
});

/**
 * Sélection du lecteur par FORMAT RÉEL (`file_format` renvoyé par l'API).
 *
 * Le point critique : c'est la valeur serveur qui décide, jamais l'extension
 * d'une URL. Un format inconnu retombe sur le lecteur texte — jamais un écran
 * blanc.
 */
describe('ResourceActions — sélection du lecteur', () => {
  it('monte le lecteur PDF pour un format `pdf`', async () => {
    render(
      <ResourceActions
        slug="s"
        title="T"
        pages={['Repli texte.']}
        fileUrl="/f"
        fileFormat="pdf"
        capabilities={freeCaps}
        copy={copy}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Lire en ligne/ }));

    // Le lecteur PDF est monté : sa barre d'outils est présente (le lecteur
    // texte, lui, n'a ni zoom ni plein écran).
    expect(screen.getByLabelText('Zoom avant')).toBeInTheDocument();
    expect(screen.getByLabelText('Plein écran')).toBeInTheDocument();

    // pdf.js est indisponible sous jsdom : le lecteur appelle `onFallback` et
    // le lecteur texte prend le relais — on attend la fin de l'effet pour ne
    // pas laisser d'état mis à jour après la fin du test.
    expect(await screen.findByText('Repli texte.')).toBeInTheDocument();
  });

  it('retombe sur le lecteur texte si le rendu EPUB échoue', async () => {
    render(
      <ResourceActions
        slug="s"
        title="T"
        pages={['Repli texte.']}
        fileUrl="/f"
        fileFormat="epub"
        capabilities={freeCaps}
        copy={copy}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Lire en ligne/ }));

    // Sous jsdom, `fetch` échoue : le lecteur EPUB appelle `onFallback` et le
    // lecteur texte prend le relais — jamais d'écran blanc.
    expect(await screen.findByText('Repli texte.')).toBeInTheDocument();
  });

  it('retombe sur le lecteur texte pour un format inconnu', () => {
    render(
      <ResourceActions
        slug="s"
        title="T"
        pages={['Contenu texte.']}
        fileUrl="/f"
        fileFormat="docx"
        capabilities={freeCaps}
        copy={copy}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Lire en ligne/ }));

    expect(screen.getByText('Contenu texte.')).toBeInTheDocument();
  });

  it('ne devine pas le format depuis l’extension de l’URL', () => {
    render(
      <ResourceActions
        slug="s"
        title="T"
        pages={['Contenu texte.']}
        fileUrl="/livre.pdf"
        fileFormat={null}
        capabilities={freeCaps}
        copy={copy}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Lire en ligne/ }));

    // Format serveur absent → lecteur texte, pas pdf.js.
    expect(screen.getByText('Contenu texte.')).toBeInTheDocument();
  });

  it('utilise le lecteur texte quand aucun fichier n’est disponible', () => {
    render(
      <ResourceActions slug="s" title="T" pages={['Contenu texte.']} capabilities={freeCaps} copy={copy} />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Lire en ligne/ }));

    expect(screen.getByText('Contenu texte.')).toBeInTheDocument();
  });
});
