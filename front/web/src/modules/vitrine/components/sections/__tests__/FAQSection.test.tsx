import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { FAQSection } from '../FAQSection';

const itemsWithoutIds = [
  { question: 'Question un ?', answer: 'Réponse un.' },
  { question: 'Question deux ?', answer: 'Réponse deux.' },
];

// Issue #4321 — les items FAQ sans `id` (pages modules : /employes, /documents,
// /comptabilite, /marketing) doivent pouvoir s'ouvrir/se fermer : le toggle,
// la rotation du chevron et la visibilité de la réponse utilisaient des
// identifiants différents (`item.id ?? index` vs `item.id` seul).
describe('FAQSection', () => {
  it('ouvre et ferme une réponse pour un item sans id', () => {
    render(
      <FAQSection title="FAQ" subtitle="Sous-titre" items={itemsWithoutIds} />,
    );

    const button = screen.getByRole('button', { name: /Question un/i });
    expect(button.getAttribute('aria-expanded')).toBe('false');
    expect(screen.queryByText('Réponse un.')).not.toBeInTheDocument();

    fireEvent.click(button);
    expect(button.getAttribute('aria-expanded')).toBe('true');
    expect(screen.getByText('Réponse un.')).toBeInTheDocument();

    fireEvent.click(button);
    expect(button.getAttribute('aria-expanded')).toBe('false');
  });

  it("n'ouvre qu'un seul item à la fois (chevron/toggle par itemKey)", () => {
    render(
      <FAQSection title="FAQ" subtitle="Sous-titre" items={itemsWithoutIds} />,
    );

    const first = screen.getByRole('button', { name: /Question un/i });
    const second = screen.getByRole('button', { name: /Question deux/i });

    fireEvent.click(first);
    fireEvent.click(second);

    expect(first.getAttribute('aria-expanded')).toBe('false');
    expect(second.getAttribute('aria-expanded')).toBe('true');
    expect(screen.getByText('Réponse deux.')).toBeInTheDocument();
  });

  it('expose aria-controls pointant vers la région de réponse', () => {
    render(
      <FAQSection title="FAQ" subtitle="Sous-titre" items={itemsWithoutIds} />,
    );

    const button = screen.getByRole('button', { name: /Question un/i });
    fireEvent.click(button);

    const controls = button.getAttribute('aria-controls');
    expect(controls).toBeTruthy();
    expect(document.getElementById(controls as string)).not.toBeNull();
  });
});
