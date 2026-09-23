import React from 'react';
import { render, screen } from '@testing-library/react';
import { ZKTecoHookSection } from '../ZKTecoHookSection';
import { VerticalsSection } from '../VerticalsSection';

/**
 * #8072 — le hook ZKTeco/pointage biométrique et les verticales doivent
 * apparaître en sections dédiées (et non noyés dans une carte de features
 * ou un ticker illisible), avec des liens internes vers les pages existantes.
 */
describe('ZKTecoHookSection (#8072)', () => {
  it('affiche le titre hook ZKTeco → paie en temps réel (FR)', () => {
    render(<ZKTecoHookSection locale="fr" />);
    expect(
      screen.getByText(/pointeuses ZKTeco connectées à la paie en temps réel/i),
    ).toBeInTheDocument();
  });

  it('porte les 3 bénéfices attendus', () => {
    render(<ZKTecoHookSection locale="fr" />);
    expect(screen.getByText('Fin du pointage papier')).toBeInTheDocument();
    expect(screen.getByText('Présence en direct')).toBeInTheDocument();
    expect(screen.getByText(/Heures → paie sans ressaisie/)).toBeInTheDocument();
  });

  it('lie la démo et les apps mobiles', () => {
    render(<ZKTecoHookSection locale="fr" />);
    expect(screen.getByRole('link', { name: /Voir le pointage en action/i })).toHaveAttribute(
      'href',
      '/demo',
    );
    expect(
      screen.getByRole('link', { name: /Découvrir les apps mobiles/i }),
    ).toHaveAttribute('href', '/mobile');
  });
});

describe('VerticalsSection (#8072)', () => {
  it('affiche les 4 verticales en cartes', () => {
    render(<VerticalsSection locale="fr" />);
    expect(screen.getByText('Restaurants')).toBeInTheDocument();
    expect(screen.getByText('Agences de voyage')).toBeInTheDocument();
    expect(screen.getByText('Écoles & formation')).toBeInTheDocument();
    expect(screen.getByText('Stations-service')).toBeInTheDocument();
  });

  it('la carte restaurants pointe vers /restaurateur (page orpheline relinkée)', () => {
    render(<VerticalsSection locale="fr" />);
    expect(
      screen.getByRole('link', { name: /Restaurants — Voir la page restaurateurs/i }),
    ).toHaveAttribute('href', '/restaurateur');
  });
});
