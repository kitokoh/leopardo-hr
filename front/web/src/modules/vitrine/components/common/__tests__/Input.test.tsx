import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Input } from '../Input';

describe('Input Component', () => {
  describe('Rendering', () => {
    it('should render input element', () => {
      render(<Input />);
      expect(screen.getByRole('textbox')).toBeInTheDocument();
    });

    it('should render with placeholder', () => {
      render(<Input placeholder="Enter text" />);
      expect(screen.getByPlaceholderText('Enter text')).toBeInTheDocument();
    });

    it('should render with different types', () => {
      const { rerender } = render(<Input type="email" />);
      let input = screen.getByRole('textbox') as HTMLInputElement;
      expect(input.type).toBe('email');

      rerender(<Input type="password" />);
      input = screen.getByRole('textbox') as HTMLInputElement;
      expect(input.type).toBe('password');

      rerender(<Input type="number" />);
      input = screen.getByRole('textbox') as HTMLInputElement;
      expect(input.type).toBe('number');
    });
  });

  describe('Value Management', () => {
    it('should update value on input', async () => {
      render(<Input />);
      const input = screen.getByRole('textbox') as HTMLInputElement;
      await userEvent.type(input, 'test value');
      expect(input.value).toBe('test value');
    });

    it('should accept initial value', () => {
      render(<Input value="initial" onChange={() => {}} />);
      const input = screen.getByRole('textbox') as HTMLInputElement;
      expect(input.value).toBe('initial');
    });
  });

  describe('Disabled State', () => {
    it('should be disabled when disabled prop is true', () => {
      render(<Input disabled />);
      const input = screen.getByRole('textbox');
      expect(input).toBeDisabled();
    });

    it('should not accept input when disabled', async () => {
      render(<Input disabled />);
      const input = screen.getByRole('textbox') as HTMLInputElement;
      await userEvent.type(input, 'test');
      expect(input.value).toBe('');
    });
  });

  describe('Error State', () => {
    it('should display error message', () => {
      render(<Input error="This field is required" />);
      expect(screen.getByText('This field is required')).toBeInTheDocument();
    });

    it('should apply error styling', () => {
      render(<Input error="Error" />);
      const input = screen.getByRole('textbox');
      expect(input).toHaveClass('border-red-500');
    });

    it('should not display error when not provided', () => {
      render(<Input />);
      const errorElements = screen.queryAllByText(/error/i);
      expect(errorElements.length).toBe(0);
    });
  });

  describe('With Icon', () => {
    it('should render with icon', () => {
      const TestIcon = () => <span data-testid="test-icon">@</span>;
      render(<Input icon={<TestIcon />} />);
      expect(screen.getByTestId('test-icon')).toBeInTheDocument();
    });
  });

  describe('Callbacks', () => {
    it('should call onChange callback', async () => {
      const handleChange = jest.fn();
      render(<Input onChange={handleChange} />);
      const input = screen.getByRole('textbox');
      await userEvent.type(input, 'a');
      expect(handleChange).toHaveBeenCalled();
    });

    it('should call onFocus callback', async () => {
      const handleFocus = jest.fn();
      render(<Input onFocus={handleFocus} />);
      const input = screen.getByRole('textbox');
      await userEvent.click(input);
      expect(handleFocus).toHaveBeenCalled();
    });

    it('should call onBlur callback', async () => {
      const handleBlur = jest.fn();
      render(<Input onBlur={handleBlur} />);
      const input = screen.getByRole('textbox');
      await userEvent.click(input);
      await userEvent.tab();
      expect(handleBlur).toHaveBeenCalled();
    });
  });

  describe('Accessibility', () => {
    it('should be keyboard accessible', async () => {
      render(<Input />);
      const input = screen.getByRole('textbox');
      input.focus();
      expect(input).toHaveFocus();
    });

    it('should have focus visible state', () => {
      render(<Input />);
      const input = screen.getByRole('textbox');
      expect(input).toHaveClass('focus:outline-none', 'focus:ring-2');
    });

    it('should work with label', () => {
      render(
        <div>
          <label htmlFor="test-input">Email</label>
          <Input id="test-input" />
        </div>
      );
      const label = screen.getByText('Email');
      expect(label).toBeInTheDocument();
    });
  });

  describe('Custom className', () => {
    it('should accept custom className', () => {
      render(<Input className="custom-class" />);
      const input = screen.getByRole('textbox');
      expect(input).toHaveClass('custom-class');
    });
  });
});
