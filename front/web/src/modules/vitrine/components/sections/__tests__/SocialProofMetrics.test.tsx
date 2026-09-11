import React from 'react';
import { render, screen } from '@testing-library/react';
import { SocialProofMetrics } from '../SocialProofMetrics';

/**
 * PA2-MKT-006 — this section must never show a customer/uptime figure
 * that isn't real (PILOTAGE.md confirms 0 paying customers to date, and
 * no SLA/uptime monitor has ever been configured). It must instead show
 * verifiable engineering facts about the product.
 */
describe('SocialProofMetrics', () => {
  it('never shows a fabricated customer count or unmeasured SLA figure', () => {
    render(<SocialProofMetrics locale="en" />);

    expect(screen.queryByText(/500\+/)).not.toBeInTheDocument();
    expect(screen.queryByText(/50K\+/)).not.toBeInTheDocument();
    expect(screen.queryByText(/99\.9%/)).not.toBeInTheDocument();
    expect(screen.queryByText(/Active companies/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/Employees managed/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/SLA uptime/i)).not.toBeInTheDocument();
  });

  it('shows real, verifiable engineering metrics for the English locale', () => {
    render(<SocialProofMetrics locale="en" />);

    // Audit 2026-09-10 : les libellés sont alignés sur le registre des métriques
    // (21 pays au catalogue de paie, ~1 000 fichiers de tests backend) — les
    // anciennes formulations (« dedicated payroll rules », « Automated backend
    // tests ») ne correspondaient pas à ce qui est mesuré.
    expect(screen.getByText('Countries in the payroll catalog')).toBeInTheDocument();
    expect(screen.getByText('Product languages (FR/EN/TR/AR)')).toBeInTheDocument();
    expect(screen.getByText('Product surfaces (web, mobile, kiosk)')).toBeInTheDocument();
    expect(screen.getByText('Backend test files')).toBeInTheDocument();
  });
});
