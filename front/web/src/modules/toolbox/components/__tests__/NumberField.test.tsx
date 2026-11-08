import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { NumberField } from '../NumberField';

/**
 * Tests du champ numérique — rendu, saisie, unité, bornes, erreur accessible.
 */
describe('NumberField', () => {
  it('affiche le libellé et l’unité', () => {
    render(<NumberField id="f1" label="Poids (kg)" value="70" onChange={() => {}} unit="kg" />);
    expect(screen.getByLabelText('Poids (kg)')).toBeInTheDocument();
    expect(screen.getByText('kg')).toBeInTheDocument();
  });

  it('remonte la saisie via onChange', () => {
    const onChange = jest.fn();
    render(<NumberField id="f2" label="Valeur" value="" onChange={onChange} />);
    fireEvent.change(screen.getByLabelText('Valeur'), { target: { value: '12,5' } });
    expect(onChange).toHaveBeenCalledWith('12,5');
  });

  it('expose l’erreur avec role=alert et aria-invalid', () => {
    render(
      <NumberField id="f3" label="Valeur" value="x" onChange={() => {}} error="Saisissez une valeur valide." />,
    );
    const input = screen.getByLabelText('Valeur');
    expect(input).toHaveAttribute('aria-invalid', 'true');
    const alert = screen.getByRole('alert');
    expect(alert).toHaveTextContent('Saisissez une valeur valide.');
    expect(input).toHaveAttribute('aria-describedby', 'f3-error');
  });

  it('affiche les bornes quand aucune erreur n’est présente', () => {
    render(<NumberField id="f4" label="Taille" value="175" onChange={() => {}} min={30} max={300} />);
    expect(screen.getByText('30 – 300')).toBeInTheDocument();
  });
});
