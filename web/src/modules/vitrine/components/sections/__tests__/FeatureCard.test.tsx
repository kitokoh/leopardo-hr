import React from 'react';
import { render, screen } from '@testing-library/react';
import { FeatureCard } from '../FeatureCard';

describe('FeatureCard Component', () => {
  const defaultProps = {
    icon: <span data-testid="feature-icon">🎯</span>,
    title: 'Feature Title',
    description: 'Feature description',
  };

  describe('Rendering', () => {
    it('should render feature card', () => {
      render(<FeatureCard {...defaultProps} />);
      expect(screen.getByText('Feature Title')).toBeInTheDocument();
    });

    it('should render icon', () => {
      render(<FeatureCard {...defaultProps} />);
      expect(screen.getByTestId('feature-icon')).toBeInTheDocument();
    });

    it('should render title', () => {
      render(<FeatureCard {...defaultProps} />);
      expect(screen.getByText('Feature Title')).toBeInTheDocument();
    });

    it('should render description', () => {
      render(<FeatureCard {...defaultProps} />);
      expect(screen.getByText('Feature description')).toBeInTheDocument();
    });
  });

  describe('Details', () => {
    it('should render details when provided', () => {
      const details = ['Detail 1', 'Detail 2', 'Detail 3'];
      render(
        <FeatureCard
          {...defaultProps}
          details={details}
        />
      );
      details.forEach(detail => {
        expect(screen.getByText(detail)).toBeInTheDocument();
      });
    });

    it('should not render details section when not provided', () => {
      render(<FeatureCard {...defaultProps} />);
      const detailsSection = screen.queryByText(/detail/i);
      expect(detailsSection).not.toBeInTheDocument();
    });
  });

  describe('Image', () => {
    it('should render image when provided', () => {
      render(
        <FeatureCard
          {...defaultProps}
          image="/test-image.jpg"
        />
      );
      const image = screen.getByRole('img');
      expect(image).toHaveAttribute('src', expect.stringContaining('test-image'));
    });

    it('should not render image when not provided', () => {
      render(<FeatureCard {...defaultProps} />);
      const images = screen.queryAllByRole('img');
      expect(images.length).toBe(0);
    });
  });

  describe('Variants', () => {
    it('should render with default variant', () => {
      const { container } = render(<FeatureCard {...defaultProps} variant="default" />);
      const card = container.querySelector('div');
      expect(card).toHaveClass('bg-white', 'dark:bg-slate-900');
    });

    it('should render with highlighted variant', () => {
      const { container } = render(<FeatureCard {...defaultProps} variant="highlighted" />);
      const card = container.querySelector('div');
      expect(card).toHaveClass('bg-gradient-to-br');
    });
  });

  describe('Accessibility', () => {
    it('should have proper semantic structure', () => {
      const { container } = render(<FeatureCard {...defaultProps} />);
      const article = container.querySelector('article');
      expect(article).toBeInTheDocument();
    });

    it('should have accessible heading', () => {
      const { container } = render(<FeatureCard {...defaultProps} />);
      const heading = container.querySelector('h3');
      expect(heading?.textContent).toBe('Feature Title');
    });

    it('should have accessible image alt text', () => {
      render(
        <FeatureCard
          {...defaultProps}
          image="/test-image.jpg"
        />
      );
      const image = screen.getByRole('img');
      expect(image).toHaveAttribute('alt');
    });
  });

  describe('Hover Effects', () => {
    it('should have hover classes', () => {
      const { container } = render(<FeatureCard {...defaultProps} />);
      const card = container.querySelector('article');
      expect(card).toHaveClass('hover:shadow-lg', 'transition-shadow');
    });
  });
});
