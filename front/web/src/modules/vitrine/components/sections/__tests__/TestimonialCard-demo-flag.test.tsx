import React from 'react';
import { render, screen } from '@testing-library/react';
import { TestimonialCard } from '../TestimonialCard';
import { TESTIMONIALS_ARE_DEMO } from '@/modules/vitrine/data/testimonials';

/**
 * #8070 — tant qu'il n'y a pas de clients réels (TESTIMONIALS_ARE_DEMO),
 * chaque témoignage affiché sur la vitrine doit porter la mention
 * « Exemple illustratif » (badge) et l'attribution suffixée « (exemple) ».
 * Régression interdite : retirer ces mentions réintroduirait de la fausse
 * preuve sociale malgré la passe PA2-MKT.
 */
const baseProps = {
  quote: 'Un produit qui change notre quotidien terrain.',
  author: 'Amina Diallo',
  role: 'DRH',
  company: 'TechAfrika',
  rating: 5,
};

describe('TestimonialCard — mention démo (#8070)', () => {
  // jsdom expose navigator.language en anglais : le libellé rendu dépend de la
  // locale détectée, on accepte donc la forme FR ou EN (même information).
  const badgeRe = /exemple illustratif|illustrative example/i;
  const suffixRe = /\((exemple|example)\)/i;

  it('affiche le badge « exemple illustratif » par défaut (flag global démo)', () => {
    expect(TESTIMONIALS_ARE_DEMO).toBe(true);
    render(<TestimonialCard {...baseProps} />);
    expect(screen.getByText(badgeRe)).toBeInTheDocument();
  });

  it('suffixe l’attribution « (exemple) » par défaut', () => {
    render(<TestimonialCard {...baseProps} />);
    expect(screen.getByText(suffixRe)).toBeInTheDocument();
  });

  it('n’affiche ni badge ni suffixe quand demo=false (témoignage réel)', () => {
    render(<TestimonialCard {...baseProps} demo={false} />);
    expect(screen.queryByText(badgeRe)).not.toBeInTheDocument();
    expect(screen.queryByText(suffixRe)).not.toBeInTheDocument();
  });
});
