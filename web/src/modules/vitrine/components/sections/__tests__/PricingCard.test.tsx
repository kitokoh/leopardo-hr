import React from 'react';
import { render, screen } from '@testing-library/react';
import { PricingCard } from '../PricingCard';

describe('PricingCard Component', () => {
  const defaultProps = {
    name: 'Starter',
    price: 29,
    currency: 'EUR',
    period: 'month',
    description: 'Perfect for startups',
    features: ['Feature 1', 'Feature 2', 'Feature 3'],
    cta: {
      text: 'Get Started',
      href: '/signup',
    },
  };

  describe('Rendering', () => {
    it('should render pricing card', () => {
      render(<PricingCard {...defaultProps} />);
      expect(screen.getByText('Starter')).toBeInTheDocument();
    });

    it('should render plan name', () => {
      render(<PricingCard {...defaultProps} />);
      expect(screen.getByText('Starter')).toBeInTheDocument();
    });

    it('should render price', () => {
      render(<PricingCard {...defaultProps} />);
      expect(screen.getByText('29')).toBeInTheDocument();
    });

    it('should render currency', () => {
      render(<PricingCard {...defaultProps} />);
      expect(screen.getByText('EUR')).toBeInTheDocument();
    });

    it('should render period', () => {
      render(<PricingCard {...defaultProps} />);
      expect(screen.getByText(/month/i)).toBeInTheDocument();
    });

    it('should render description', () => {
      render(<PricingCard {...defaultProps} />);
      expect(screen.getByText('Perfect for startups')).toBeInTheDocument();
    });

    it('should render all features', () => {
      render(<PricingCard {...defaultProps} />);
      defaultProps.features.forEach(feature => {
        expect(screen.getByText(feature)).toBeInTheDocument();
      });
    });

    it('should render CTA button', () => {
      render(<PricingCard {...defaultProps} />);
      expect(screen.getByRole('link', { name: /get started/i })).toBeInTheDocument();
    });
  });

  describe('Highlighted Variant', () => {
    it('should render with highlighted styling when highlighted prop is true', () => {
      const { container } = render(
        <PricingCard {...defaultProps} highlighted={true} />
      );
      const card = container.querySelector('div');
      expect(card).toHaveClass('ring-2', 'ring-emerald-500');
    });

    it('should render badge when highlighted', () => {
      render(
        <PricingCard
          {...defaultProps}
          highlighted={true}
          badge="POPULAR"
        />
      );
      expect(screen.getByText('POPULAR')).toBeInTheDocument();
    });

    it('should not render badge when not highlighted', () => {
      render(
        <PricingCard
          {...defaultProps}
          highlighted={false}
          badge="POPULAR"
        />
      );
      const badge = screen.queryByText('POPULAR');
      expect(badge).not.toBeInTheDocument();
    });
  });

  describe('Feature List', () => {
    it('should render feature list with checkmarks', () => {
      const { container } = render(<PricingCard {...defaultProps} />);
      const listItems = container.querySelectorAll('li');
      expect(listItems.length).toBe(defaultProps.features.length);
    });

    it('should handle empty features list', () => {
      render(
        <PricingCard
          {...defaultProps}
          features={[]}
        />
      );
      const listItems = screen.queryAllByRole('listitem');
      expect(listItems.length).toBe(0);
    });
  });

  describe('CTA Link', () => {
    it('should have correct href', () => {
      render(<PricingCard {...defaultProps} />);
      const link = screen.getByRole('link', { name: /get started/i });
      expect(link).toHaveAttribute('href', '/signup');
    });

    it('should render CTA button with correct text', () => {
      render(<PricingCard {...defaultProps} />);
      expect(screen.getByRole('link', { name: /get started/i })).toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    it('should have proper semantic structure', () => {
      const { container } = render(<PricingCard {...defaultProps} />);
      const article = container.querySelector('article');
      expect(article).toBeInTheDocument();
    });

    it('should have accessible heading', () => {
      const { container } = render(<PricingCard {...defaultProps} />);
      const heading = container.querySelector('h3');
      expect(heading?.textContent).toBe('Starter');
    });

    it('should have accessible feature list', () => {
      const { container } = render(<PricingCard {...defaultProps} />);
      const list = container.querySelector('ul');
      expect(list).toBeInTheDocument();
    });

    it('should have accessible CTA link', () => {
      render(<PricingCard {...defaultProps} />);
      const link = screen.getByRole('link', { name: /get started/i });
      expect(link).toHaveAccessibleName();
    });
  });

  describe('Responsive Design', () => {
    it('should have responsive classes', () => {
      const { container } = render(<PricingCard {...defaultProps} />);
      const card = container.querySelector('article');
      expect(card).toHaveClass('rounded-xl', 'p-6', 'md:p-8');
    });
  });

  describe('Different Price Points', () => {
    it('should render different prices', () => {
      const { rerender } = render(<PricingCard {...defaultProps} price={29} />);
      expect(screen.getByText('29')).toBeInTheDocument();

      rerender(<PricingCard {...defaultProps} price={79} />);
      expect(screen.getByText('79')).toBeInTheDocument();

      rerender(<PricingCard {...defaultProps} price={199} />);
      expect(screen.getByText('199')).toBeInTheDocument();
    });
  });
});
