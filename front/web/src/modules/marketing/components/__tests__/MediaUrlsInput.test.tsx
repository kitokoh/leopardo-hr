import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MediaUrlsInput } from '@/modules/marketing/components/MediaUrlsInput';

/**
 * #7755 — champ « URLs de médias » du composer (envoyé en media_paths).
 */
describe('MediaUrlsInput', () => {
  it('adds only valid http(s) urls, without duplicates', async () => {
    const user = userEvent.setup();
    const onChange = jest.fn();
    render(<MediaUrlsInput value={[]} onChange={onChange} />);

    const field = screen.getByTestId('media-urls-input-field');
    const add = screen.getByTestId('media-urls-input-add');

    // Invalid URL: add stays disabled.
    await user.type(field, 'not-a-url');
    expect(add).toBeDisabled();
    expect(onChange).not.toHaveBeenCalled();

    await user.clear(field);
    await user.type(field, 'https://cdn.example.com/visual.jpg');
    expect(add).toBeEnabled();
    await user.click(add);

    expect(onChange).toHaveBeenCalledWith(['https://cdn.example.com/visual.jpg']);
  });

  it('adds the draft url on Enter and clears the field', async () => {
    const user = userEvent.setup();
    const onChange = jest.fn();
    render(<MediaUrlsInput value={[]} onChange={onChange} />);

    const field = screen.getByTestId('media-urls-input-field');
    await user.type(field, 'https://cdn.example.com/video.mp4{enter}');

    expect(onChange).toHaveBeenCalledWith(['https://cdn.example.com/video.mp4']);
    expect(field).toHaveValue('');
  });

  it('ignores a url already present in the list', async () => {
    const user = userEvent.setup();
    const onChange = jest.fn();
    render(<MediaUrlsInput value={['https://cdn.example.com/a.jpg']} onChange={onChange} />);

    await user.type(screen.getByTestId('media-urls-input-field'), 'https://cdn.example.com/a.jpg{enter}');
    expect(onChange).not.toHaveBeenCalled();
  });

  it('removes a url from the list', async () => {
    const user = userEvent.setup();
    const onChange = jest.fn();
    render(
      <MediaUrlsInput
        value={['https://cdn.example.com/a.jpg', 'https://cdn.example.com/b.jpg']}
        onChange={onChange}
      />,
    );

    const removeButtons = screen.getAllByRole('button', { name: /retirer|remove/i });
    await user.click(removeButtons[0]);

    expect(onChange).toHaveBeenCalledWith(['https://cdn.example.com/b.jpg']);
  });
});
