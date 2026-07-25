import React from 'react';
import { render, screen } from '@testing-library/react';
import { HeroProductVisual } from '../HeroProductVisual';

/**
 * PA2-MKT-001 — the hero must show a real product screenshot instead of
 * leaving the visitor with only copy before the email capture.
 */
describe('HeroProductVisual', () => {
  it('renders the real product screenshot with the provided alt text', () => {
    render(
      <HeroProductVisual
        src="/screenshots/web-dashboard.png"
        alt="Leopardo HR admin dashboard screenshot"
      />
    );

    const image = screen.getByAltText('Leopardo HR admin dashboard screenshot');
    expect(image).toBeInTheDocument();
    expect(image.tagName.toLowerCase()).toBe('img');
  });
});
