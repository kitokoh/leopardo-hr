import React from 'react';
import { render, screen } from '@testing-library/react';
import { Textarea } from '../Textarea';

/**
 * #9232 — parité d'accessibilité entre `Textarea` et `Input`.
 *
 * Avant ce correctif, `Textarea` n'exposait ni `aria-invalid` ni
 * `aria-describedby` : un lecteur d'écran lisait le champ sans jamais
 * signaler qu'il était en erreur, et le message d'erreur n'était rattaché
 * à aucun champ. Ces tests verrouillent le comportement.
 */
describe('Textarea — accessibilité', () => {
  it('rattache le message d’erreur au champ via aria-describedby', () => {
    render(<Textarea label="Lettre de motivation" error="Ce champ est requis" />);

    const field = screen.getByLabelText(/Lettre de motivation/);
    const error = screen.getByRole('alert');

    expect(error).toHaveTextContent('Ce champ est requis');
    expect(field).toHaveAttribute('aria-invalid', 'true');
    expect(field).toHaveAttribute('aria-describedby', error.id);
  });

  it('annonce le message d’erreur via role="alert"', () => {
    render(<Textarea label="Message" error="Trop long" />);

    expect(screen.getByRole('alert')).toHaveTextContent('Trop long');
  });

  it('rattache le texte d’aide quand il n’y a pas d’erreur', () => {
    render(<Textarea label="Message" helperText="500 caractères maximum" />);

    const field = screen.getByLabelText(/Message/);
    const helper = screen.getByText('500 caractères maximum');

    expect(field).not.toHaveAttribute('aria-invalid');
    expect(field).toHaveAttribute('aria-describedby', helper.id);
  });

  it('n’expose ni aria-invalid ni aria-describedby sans erreur ni aide', () => {
    render(<Textarea label="Message" />);

    const field = screen.getByLabelText(/Message/);

    expect(field).not.toHaveAttribute('aria-invalid');
    expect(field).not.toHaveAttribute('aria-describedby');
  });

  it('marque le champ requis pour les technologies d’assistance', () => {
    render(<Textarea label="Message" required />);

    expect(screen.getByLabelText(/Message/)).toBeRequired();
  });
});
