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
