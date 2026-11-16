import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { TvaMultiTauxForm } from '../tools/TvaMultiTauxForm';
import { getToolboxCopy } from '../../data/copy';
import { MAX_SHARED_LINES } from '../../tools/tva-multi-taux/lines';
import { decodeToolState } from '../../lib/share';

/**
 * Tests de composant du formulaire TVA multi-taux (issue #9531).
 *
 * Ces tests reproduisent les deux défauts RÉELS constatés avant correctif,
 * cette fois au niveau de l'interface :
 * - supprimer une ligne laissait des clés orphelines ; la relecture ordonnée
 *   faisait réapparaître un duplicata de la ligne suivante et le TOTAL TTC
 *   affiché était faux ;
 * - au-delà de `MAX_SHARED_LINES`, l'URL partagée ne transportait qu'une partie
 *   des lignes, sans avertissement.
 */

const copy = getToolboxCopy('fr').tools['tva-multi-taux'];
const common = getToolboxCopy('fr').common;

/** Valeurs affichées, dans l'ordre des lignes. */
function displayed(label: string): string[] {
  return screen
    .getAllByLabelText(label)
    .map((element) => (element as HTMLInputElement).value);
}

/** Paramètre `?s=` courant, décodé. */
function sharedState() {
  const raw = new URLSearchParams(window.location.search).get('s');
  return decodeToolState(raw);
}

function addLines(count: number) {
  const button = screen.getByRole('button', { name: copy.fields.addLine });
  for (let index = 0; index < count; index += 1) fireEvent.click(button);
}

describe('TvaMultiTauxForm — suppression de ligne', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/toolbox/tva-multi-taux');
  });

  it('supprimer la 1re de 2 lignes laisse UNE seule ligne (défaut corrigé)', () => {
    render(<TvaMultiTauxForm copy={copy} common={common} locale="fr" />);
    expect(displayed(copy.fields.amount)).toEqual(['1000', '200']);

    fireEvent.click(screen.getAllByRole('button', { name: copy.fields.removeLine })[0]);

    // Avant correctif : ['200', '200'] — un duplicata, pas une suppression.
    expect(displayed(copy.fields.amount)).toEqual(['200']);
    expect(displayed(copy.fields.rate)).toEqual(['5.5']);
  });

  it('supprimer la ligne du milieu de 3 lignes conserve les deux autres', () => {
    render(<TvaMultiTauxForm copy={copy} common={common} locale="fr" />);
    addLines(1); // 3e ligne : 0 @ 5,5 (reprend le taux de la dernière ligne)

    expect(displayed(copy.fields.amount)).toEqual(['1000', '200', '0']);
    expect(displayed(copy.fields.rate)).toEqual(['20', '5.5', '5.5']);
    fireEvent.click(screen.getAllByRole('button', { name: copy.fields.removeLine })[1]);

    expect(displayed(copy.fields.amount)).toEqual(['1000', '0']);
    expect(displayed(copy.fields.rate)).toEqual(['20', '5.5']);
  });

  it('met à jour le total TTC après suppression (pas de total fantôme)', () => {
    render(<TvaMultiTauxForm copy={copy} common={common} locale="fr" />);
    // 1000 @ 20 % + 200 @ 5,5 % = 1200 + 200 + 11 = 1411
    expect(screen.getByText(/1\s?411,00/)).toBeInTheDocument();

    // On supprime la ligne à 5,5 % : total = 1000 + 200 = 1200.
    fireEvent.click(screen.getAllByRole('button', { name: copy.fields.removeLine })[1]);
    expect(screen.getByText(/1\s?200,00/)).toBeInTheDocument();
    expect(screen.queryByText(/1\s?411,00/)).not.toBeInTheDocument();
  });

  it('ne laisse aucune clé orpheline dans l’URL partagée', () => {
    render(<TvaMultiTauxForm copy={copy} common={common} locale="fr" />);
    fireEvent.click(screen.getAllByRole('button', { name: copy.fields.removeLine })[0]);

    const state = sharedState();
    expect(state).toEqual({ amount0: '200', rate0: '5.5' });
  });

  it('ne permet pas de supprimer la dernière ligne restante', () => {
    render(<TvaMultiTauxForm copy={copy} common={common} locale="fr" />);
    fireEvent.click(screen.getAllByRole('button', { name: copy.fields.removeLine })[0]);

    const [onlyButton] = screen.getAllByRole('button', { name: copy.fields.removeLine });
    expect(onlyButton).toBeDisabled();
  });
});

describe('TvaMultiTauxForm — plafond de partage', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/toolbox/tva-multi-taux');
  });

  it('borne l’ajout à MAX_SHARED_LINES lignes', () => {
    render(<TvaMultiTauxForm copy={copy} common={common} locale="fr" />);
    addLines(MAX_SHARED_LINES + 5);

    expect(displayed(copy.fields.amount)).toHaveLength(MAX_SHARED_LINES);
  });

  it('annonce le plafond au lieu d’ignorer le clic', () => {
    render(<TvaMultiTauxForm copy={copy} common={common} locale="fr" />);
    addLines(MAX_SHARED_LINES - 2); // atteint exactement le plafond

    // Le bouton reste cliquable : un bouton grisé n'expliquerait rien.
    const button = screen.getByRole('button', { name: copy.fields.addLine });
    expect(button).not.toBeDisabled();

    fireEvent.click(button);
    expect(screen.getByRole('status')).toHaveTextContent(
      copy.fields.maxLinesReached.replace('{max}', String(MAX_SHARED_LINES)),
    );
    // Le clic n'ajoute aucune ligne au-delà du plafond.
    expect(displayed(copy.fields.amount)).toHaveLength(MAX_SHARED_LINES);
  });

  it('l’URL partagée transporte TOUTES les lignes affichées', () => {
    render(<TvaMultiTauxForm copy={copy} common={common} locale="fr" />);
    addLines(MAX_SHARED_LINES - 2);

    const state = sharedState();
    expect(state).not.toBeNull();
    const amountKeys = Object.keys(state!).filter((key) => key.startsWith('amount'));
    expect(amountKeys).toHaveLength(MAX_SHARED_LINES);
  });

  it('réactive l’ajout après une suppression', () => {
    render(<TvaMultiTauxForm copy={copy} common={common} locale="fr" />);
    addLines(MAX_SHARED_LINES - 2);

    const button = screen.getByRole('button', { name: copy.fields.addLine });
    fireEvent.click(button);
    expect(screen.getByRole('status')).toBeInTheDocument();

    fireEvent.click(screen.getAllByRole('button', { name: copy.fields.removeLine })[0]);
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    expect(displayed(copy.fields.amount)).toHaveLength(MAX_SHARED_LINES - 1);
  });
});

describe('TvaMultiTauxForm — partage et validation', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/toolbox/tva-multi-taux');
  });

  it('ré-hydrate un calcul partagé (3 lignes)', () => {
    // Lien encodé par une visite précédente : 100 @ 20 %, 50 @ 10 %, 25 @ 5,5 %.
    const payload = { v: 1, s: { amount0: '100', rate0: '20', amount1: '50', rate1: '10', amount2: '25', rate2: '5.5' } };
    const encoded = btoa(JSON.stringify(payload))
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=+$/, '');
    window.history.replaceState({}, '', `/toolbox/tva-multi-taux?s=${encoded}`);

    render(<TvaMultiTauxForm copy={copy} common={common} locale="fr" />);
    expect(displayed(copy.fields.amount)).toEqual(['100', '50', '25']);
    expect(displayed(copy.fields.rate)).toEqual(['20', '10', '5.5']);
  });

  it('affiche le message d’entrée invalide pour une saisie vide', () => {
    render(<TvaMultiTauxForm copy={copy} common={common} locale="fr" />);
    fireEvent.change(screen.getAllByLabelText(copy.fields.amount)[0], {
      target: { value: '' },
    });
    expect(screen.getByText(common.invalidInput)).toBeInTheDocument();
  });
});
