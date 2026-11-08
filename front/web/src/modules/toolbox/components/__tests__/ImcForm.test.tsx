import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { ImcForm } from '../tools/ImcForm';
import { getToolboxCopy } from '../../data/copy';

/**
 * Tests du formulaire IMC — calcul en direct, interprétation localisée,
 * message d'entrée invalide.
 */
const common = getToolboxCopy('fr').common;
const copy = getToolboxCopy('fr').tools.imc;

describe('ImcForm', () => {
  it('calcule l’IMC par défaut (70 kg / 175 cm → 22,9)', () => {
    render(<ImcForm copy={copy} common={common} locale="fr" />);
    expect(screen.getByText('22,9')).toBeInTheDocument();
    expect(screen.getByText('Corpulence normale')).toBeInTheDocument();
  });

  it('recalcule à la saisie', () => {
    render(<ImcForm copy={copy} common={common} locale="fr" />);
    fireEvent.change(screen.getByLabelText('Poids (kg)'), { target: { value: '100' } });
    // 100 / 1.75² = 32,7 → obésité
    expect(screen.getByText('32,7')).toBeInTheDocument();
    expect(screen.getByText('Obésité')).toBeInTheDocument();
  });

  it('affiche le message d’entrée invalide quand la saisie est vide', () => {
    render(<ImcForm copy={copy} common={common} locale="fr" />);
    fireEvent.change(screen.getByLabelText('Poids (kg)'), { target: { value: '' } });
    expect(screen.getByText(common.invalidInput)).toBeInTheDocument();
  });

  it('localise l’interprétation en anglais', () => {
    render(<ImcForm copy={getToolboxCopy('en').tools.imc} common={getToolboxCopy('en').common} locale="en" />);
    expect(screen.getByText('Normal weight')).toBeInTheDocument();
  });
});
