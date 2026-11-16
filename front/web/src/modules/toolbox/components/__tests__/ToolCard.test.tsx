import React from 'react';
import { render, screen } from '@testing-library/react';
import { ToolCard } from '../ToolCard';

/**
 * Tests de la carte d'outil — lien, libellés, catégorie.
 */
describe('ToolCard', () => {
  it('rend un lien vers la page de l’outil', () => {
    render(
      <ToolCard
        href="/toolbox/imc"
        name="IMC — Indice de masse corporelle"
        description="Calculez votre IMC."
        categoryLabel="Santé"
        openLabel="Ouvrir l’outil"
        locale="fr"
      />,
    );
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/toolbox/imc');
    expect(screen.getByText('IMC — Indice de masse corporelle')).toBeInTheDocument();
    expect(screen.getByText('Santé')).toBeInTheDocument();
    expect(screen.getByText('Ouvrir l’outil')).toBeInTheDocument();
  });

  it('propage la locale dans le lien (fr = URL canonique, sans ?lang=)', () => {
    render(
      <ToolCard
        href="/toolbox/imc"
        name="IMC"
        description="…"
        categoryLabel="Santé"
        openLabel="Ouvrir"
        locale="fr"
      />,
    );
    expect(screen.getByRole('link')).toHaveAttribute('href', '/toolbox/imc');
  });

  it('ajoute ?lang=tr au lien quand la locale n’est pas le français', () => {
    render(
      <ToolCard
        href="/toolbox/imc"
        name="IMC"
        description="…"
        categoryLabel="Sağlık"
        openLabel="Aç"
        locale="tr"
      />,
    );
    expect(screen.getByRole('link')).toHaveAttribute('href', '/toolbox/imc?lang=tr');
  });
});
