import React from 'react';
import { render, screen } from '@testing-library/react';
import { HeroSection } from '../HeroSection';

describe('HeroSection Component', () => {
  const defaultProps = {
    headline: 'Test Headline',
    subheadline: 'Test Subheadline',
    ctaPrimary: {
      text: 'Get Started',
      href: '/signup',
    },
  };

  describe('Rendering', () => {
    it('should render headline', () => {
      render(<HeroSection {...defaultProps} />);
      expect(screen.getByText('Test Headline')).toBeInTheDocument();
    });

    it('should render subheadline', () => {
      render(<HeroSection {...defaultProps} />);
      expect(screen.getByText('Test Subheadline')).toBeInTheDocument();
    });

    it('should render primary CTA button', () => {
      render(<HeroSection {...defaultProps} />);
      expect(screen.getByRole('link', { name: /get started/i })).toBeInTheDocument();
    });

    it('should render secondary CTA when provided', () => {
      render(
        <HeroSection
          {...defaultProps}
          ctaSecondary={{
            text: 'Learn More',
            href: '/learn',
          }}
        />
      );
      expect(screen.getByRole('link', { name: /learn more/i })).toBeInTheDocument();
    });

    it('should render badge when provided', () => {
      render(
        <HeroSection
          {...defaultProps}
          badge={{ text: 'New Feature' }}
        />
      );
      expect(screen.getByText('New Feature')).toBeInTheDocument();
    });
  });

  describe('Statistics', () => {
    it('should render statistics when provided', () => {
      const stats = [
        { value: 1000, suffix: '+', label: 'Users', icon: <span /> },
        { value: 99, suffix: '%', label: 'Uptime', icon: <span /> },
      ];
      render(
        <HeroSection
          {...defaultProps}
          stats={stats}
        />
      );
      expect(screen.getByText(/1000/)).toBeInTheDocument();
      expect(screen.getByText(/99/)).toBeInTheDocument();
      expect(screen.getByText('Users')).toBeInTheDocument();
      expect(screen.getByText('Uptime')).toBeInTheDocument();
    });

    it('should not render statistics section when not provided', () => {
      render(<HeroSection {...defaultProps} />);
      const statsSection = screen.queryByText(/users|uptime/i);
      expect(statsSection).not.toBeInTheDocument();
    });
  });

  describe('Visual Elements', () => {
    it('should render visual element when provided', () => {
      render(
        <HeroSection
          {...defaultProps}
          visual={<div data-testid="visual-element">Visual</div>}
        />
      );
      expect(screen.getByTestId('visual-element')).toBeInTheDocument();
    });

    it('should not render visual element when not provided', () => {
      render(<HeroSection {...defaultProps} />);
      const visual = screen.queryByTestId('visual-element');
      expect(visual).not.toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    it('should have proper heading hierarchy', () => {
      const { container } = render(<HeroSection {...defaultProps} />);
      const heading = container.querySelector('h1');
      expect(heading).toBeInTheDocument();
      expect(heading?.textContent).toBe('Test Headline');
    });

    it('should have accessible CTA links', () => {
      render(<HeroSection {...defaultProps} />);
      const link = screen.getByRole('link', { name: /get started/i });
      expect(link).toHaveAttribute('href', '/signup');
    });

    it('should have proper semantic structure', () => {
      const { container } = render(<HeroSection {...defaultProps} />);
      const section = container.querySelector('section');
      expect(section).toBeInTheDocument();
    });
  });

  describe('Responsive Design', () => {
    it('should render with responsive classes', () => {
      const { container } = render(<HeroSection {...defaultProps} />);
      const section = container.querySelector('section');
      expect(section).toHaveClass('min-h-screen', 'flex', 'items-center');
    });
  });
});
