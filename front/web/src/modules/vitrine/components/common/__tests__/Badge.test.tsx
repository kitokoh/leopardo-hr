import React from 'react';
import { render, screen } from '@testing-library/react';
import { Badge } from '../Badge';

describe('Badge Component', () => {
  describe('Rendering', () => {
    it('should render badge with text', () => {
      render(<Badge>New</Badge>);
      expect(screen.getByText('New')).toBeInTheDocument();
    });

    it('should render with primary variant by default', () => {
      render(<Badge>Primary</Badge>);
      const badge = screen.getByText('Primary');
      expect(badge).toHaveClass('bg-emerald-100', 'text-emerald-800');
    });

    it('should render with secondary variant', () => {
      render(<Badge variant="secondary">Secondary</Badge>);
      const badge = screen.getByText('Secondary');
      expect(badge).toHaveClass('bg-slate-100', 'text-slate-800');
    });

    it('should render with success variant', () => {
      render(<Badge variant="success">Success</Badge>);
      const badge = screen.getByText('Success');
      expect(badge).toHaveClass('bg-green-100', 'text-green-800');
    });

    it('should render with warning variant', () => {
      render(<Badge variant="warning">Warning</Badge>);
      const badge = screen.getByText('Warning');
      expect(badge).toHaveClass('bg-amber-100', 'text-amber-800');
    });

    it('should render with error variant', () => {
      render(<Badge variant="error">Error</Badge>);
      const badge = screen.getByText('Error');
      expect(badge).toHaveClass('bg-red-100', 'text-red-800');
    });
  });

  describe('Sizes', () => {
    it('should render with small size', () => {
      render(<Badge size="sm">Small</Badge>);
      const badge = screen.getByText('Small');
      expect(badge).toHaveClass('px-2', 'py-1', 'text-xs');
    });

    it('should render with medium size', () => {
      render(<Badge size="md">Medium</Badge>);
      const badge = screen.getByText('Medium');
      expect(badge).toHaveClass('px-3', 'py-1.5', 'text-sm');
    });
  });

  describe('With Icon', () => {
    it('should render with icon', () => {
      const TestIcon = () => <span data-testid="test-icon">✓</span>;
      render(<Badge icon={<TestIcon />}>With Icon</Badge>);
      expect(screen.getByTestId('test-icon')).toBeInTheDocument();
      expect(screen.getByText('With Icon')).toBeInTheDocument();
    });
  });

  describe('Accessibility', () => {
    it('should have proper semantic structure', () => {
      const { container } = render(<Badge>Accessible</Badge>);
      const badge = container.querySelector('span');
      expect(badge).toBeInTheDocument();
    });

    it('should have accessible name', () => {
      render(<Badge>Accessible Badge</Badge>);
      expect(screen.getByText('Accessible Badge')).toBeInTheDocument();
    });
  });
});
