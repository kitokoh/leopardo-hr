import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { PostEditor } from '@/modules/marketing/components/PostEditor';

describe('PostEditor', () => {
  it('disables submit until content and at least one platform are set', async () => {
    const user = userEvent.setup();
    render(<PostEditor onSubmit={jest.fn()} />);

    const submit = screen.getByTestId('post-editor-submit');
    expect(submit).toBeDisabled();

    await user.type(screen.getByTestId('post-editor-content'), 'Hello world');
    expect(submit).toBeDisabled();

    await user.click(screen.getByTestId('post-editor-platform-linkedin'));
    expect(submit).toBeEnabled();
  });

  it('calls onSubmit with content, selected platforms and null scheduledAt when left empty', async () => {
    const user = userEvent.setup();
    const onSubmit = jest.fn();
    render(<PostEditor onSubmit={onSubmit} />);

    await user.type(screen.getByTestId('post-editor-content'), 'A new post');
    await user.click(screen.getByTestId('post-editor-platform-linkedin'));
    await user.click(screen.getByTestId('post-editor-platform-twitter'));
    await user.click(screen.getByTestId('post-editor-submit'));

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1));
    expect(onSubmit).toHaveBeenCalledWith({
      content: 'A new post',
      targetPlatforms: ['linkedin', 'twitter'],
      scheduledAt: null,
    });
  });

  it('resets its fields after a successful submit', async () => {
    const user = userEvent.setup();
    const onSubmit = jest.fn();
    render(<PostEditor onSubmit={onSubmit} />);

    await user.type(screen.getByTestId('post-editor-content'), 'Reset me');
    await user.click(screen.getByTestId('post-editor-platform-linkedin'));
    await user.click(screen.getByTestId('post-editor-submit'));

    await waitFor(() => expect(screen.getByTestId('post-editor-content')).toHaveValue(''));
    expect(screen.getByTestId('post-editor-submit')).toBeDisabled();
  });

  it('renders a cancel button and invokes onCancel when clicked', async () => {
    const user = userEvent.setup();
    const onCancel = jest.fn();
    render(<PostEditor onSubmit={jest.fn()} onCancel={onCancel} />);

    await user.click(screen.getByRole('button', { name: /annuler/i }));
    expect(onCancel).toHaveBeenCalledTimes(1);
  });

  it('pre-fills the scheduled date from initialScheduledAt', () => {
    render(<PostEditor onSubmit={jest.fn()} initialScheduledAt="2026-08-01T09:00" />);

    expect(screen.getByTestId('post-editor-scheduled-at')).toHaveValue('2026-08-01T09:00');
  });
});
