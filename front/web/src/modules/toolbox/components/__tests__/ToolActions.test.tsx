import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ToolActions } from '../ToolActions';
import { TOOLBOX_STORAGE_KEY, loadState } from '../../lib/storage';

/**
 * Tests des actions personnelles d'une page d'outil — favori + usage.
 *
 * On vérifie l'état RENDU (aria-pressed, libellé) et l'état PERSISTÉ, pas
 * seulement l'appel de fonction.
 */

const COPY = {
  addFavorite: 'Ajouter aux favoris',
  removeFavorite: 'Retirer des favoris',
};

describe('ToolActions', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('enregistre l’usage de l’outil au montage', async () => {
    render(<ToolActions slug="imc" copy={COPY} />);

    await waitFor(() => {
      expect(loadState().history).toEqual(['imc']);
    });
  });

  it('place l’outil en tête de l’historique sans doublon', async () => {
    window.localStorage.setItem(
      TOOLBOX_STORAGE_KEY,
      JSON.stringify({ favorites: [], history: ['tva', 'imc'] }),
    );

    render(<ToolActions slug="imc" copy={COPY} />);

    await waitFor(() => {
      expect(loadState().history).toEqual(['imc', 'tva']);
    });
  });

  it('affiche « Ajouter aux favoris » quand l’outil n’est pas en favori', async () => {
    render(<ToolActions slug="imc" copy={COPY} />);

    const button = await screen.findByRole('button', { name: COPY.addFavorite });
    expect(button).toHaveAttribute('aria-pressed', 'false');
  });

  it('ajoute puis retire le favori au clic', async () => {
    render(<ToolActions slug="imc" copy={COPY} />);

    const addButton = await screen.findByRole('button', { name: COPY.addFavorite });
    fireEvent.click(addButton);

    const removeButton = await screen.findByRole('button', { name: COPY.removeFavorite });
    expect(removeButton).toHaveAttribute('aria-pressed', 'true');
    expect(loadState().favorites).toEqual(['imc']);

    fireEvent.click(removeButton);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: COPY.addFavorite })).toHaveAttribute(
        'aria-pressed',
        'false',
      );
    });
    expect(loadState().favorites).toEqual([]);
  });

  it('reflète un favori déjà enregistré', async () => {
    window.localStorage.setItem(
      TOOLBOX_STORAGE_KEY,
      JSON.stringify({ favorites: ['imc'], history: [] }),
    );

    render(<ToolActions slug="imc" copy={COPY} />);

    expect(await screen.findByRole('button', { name: COPY.removeFavorite })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
  });

  it('n’enregistre pas l’usage d’un autre outil', async () => {
    render(<ToolActions slug="tva" copy={COPY} />);

    await waitFor(() => {
      expect(loadState().history).toEqual(['tva']);
    });
    expect(loadState().history).not.toContain('imc');
  });
});
