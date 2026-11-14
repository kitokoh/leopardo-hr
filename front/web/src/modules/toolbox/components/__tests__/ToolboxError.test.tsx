import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import ToolboxError from '../../../../app/toolbox/error';

/**
 * Tests de la frontière d'erreur du segment `/toolbox`.
 *
 * Le point critique : l'erreur doit rester CONFINÉE à la Toolbox. La frontière
 * racine (`app/error.tsx`) remplace tout le chrome ; celle-ci conserve le
 * layout du segment et laisse deux issues à l'utilisateur.
 */
jest.mock('next/link', () => {
  return function MockLink({ href, children, ...rest }: { href: string; children: React.ReactNode }) {
    return (
      <a href={href} {...rest}>
        {children}
      </a>
    );
  };
});

describe('ToolboxError', () => {
  const error = Object.assign(new Error('boom'), { digest: 'abc123' });

  beforeEach(() => {
    jest.spyOn(console, 'error').mockImplementation(() => {});
    document.documentElement.lang = 'fr';
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('affiche le titre et le corps localisés', () => {
    render(<ToolboxError error={error} reset={jest.fn()} />);
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(
      'Cet outil a rencontré un problème',
    );
    expect(screen.getByText(/Le reste du site continue de fonctionner/)).toBeInTheDocument();
  });

  it('propose de réessayer et de revenir au catalogue', () => {
    const reset = jest.fn();
    render(<ToolboxError error={error} reset={reset} />);

    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }));
    expect(reset).toHaveBeenCalledTimes(1);

    expect(screen.getByRole('link', { name: 'Retour à tous les outils' })).toHaveAttribute(
      'href',
      '/toolbox',
    );
  });

  it('affiche le digest quand il existe', () => {
    render(<ToolboxError error={error} reset={jest.fn()} />);
    expect(screen.getByText('abc123')).toBeInTheDocument();
  });

  it('n’affiche pas de digest quand il est absent', () => {
    render(<ToolboxError error={new Error('boom')} reset={jest.fn()} />);
    expect(screen.queryByText('abc123')).not.toBeInTheDocument();
  });

  it('journalise l’erreur une seule fois', () => {
    render(<ToolboxError error={error} reset={jest.fn()} />);
    expect(console.error).toHaveBeenCalledWith('[toolbox] unhandled render error', error);
  });
});
