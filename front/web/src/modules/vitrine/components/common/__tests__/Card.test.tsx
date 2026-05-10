import React from 'react';
import { render, screen } from '@testing-library/react';
import { Card } from '../Card';

describe('Card Component', () => {
  describe('Rendering', () => {
    it('should render card with children', () => {
      render(<Card>Card content</Card>);
      expect(screen.getByText('Card content')).toBeInTheDocument();
    });

    it('should render with default variant', () => {
      render(<Card>Default</Card>);
      const card = screen.getByText('Default').closest('div');
      expect(card).toHaveClass('bg-white', 'dark:bg-slate-900', 'rounded-xl');
    });

    it('should render with elevated variant', () => {
      render(<Card variant="elevated">Elevated</Card>);
      const card = screen.getByText('Elevated').closest('div');
      expect(card).toHaveClass('shadow-lg', 'rounded-2xl');
    });

    it('should render with outlined variant', () => {
      render(<Card variant="outlined">Outlined</Card>);
      const card = screen.getByText('Outlined').closest('div');
      expect(card).toHaveClass('border', 'border-slate-200', 'dark:border-slate-800');
    });
  });

  describe('Hover Effects', () => {
    it('should apply hover effect when hover prop is true', () => {
      render(<Card hover>Hoverable</Card>);
      const card = screen.getByText('Hoverable').closest('div');
      expect(card).toHaveClass('hover:shadow-lg', 'transition-shadow');
    });

    it('should not apply hover effect when hover prop is false', () => {
      render(<Card hover={false}>Not hoverable</Card>);
      const card = screen.getByText('Not hoverable').closest('div');
      expect(card).not.toHaveClass('hover:shadow-lg');
    });
  });

  describe('Custom className', () => {
    it('should accept custom className', () => {
      render(<Card className="custom-class">Custom</Card>);
      const card = screen.getByText('Custom').closest('div');
      expect(card).toHaveClass('custom-class');
    });
  });

  describe('Complex Content', () => {
    it('should render complex nested content', () => {
      render(
        <Card>
          <div>
            <h2>Title</h2>
            <p>Description</p>
          </div>
        </Card>
      );
      expect(screen.getByText('Title')).toBeInTheDocument();
      expect(screen.getByText('Description')).toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    it('should maintain semantic structure', () => {
      const { container } = render(
        <Card>
          <h2>Card Title</h2>
          <p>Card content</p>
        </Card>
      );
      const heading = container.querySelector('h2');
      expect(heading).toBeInTheDocument();
    });
  });
});
