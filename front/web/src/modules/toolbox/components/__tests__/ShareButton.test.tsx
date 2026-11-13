import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ShareButton } from '../ShareButton';
import { SHARE_PARAM, decodeToolState } from '../../lib/share';

/**
 * Tests du bouton de partage — copie d'un lien portant les ENTRÉES.
 *
 * On vérifie le contenu réellement copié (décodé), pas seulement l'appel de
 * fonction : c'est le lien qui doit rejouer le calcul, pas un texte quelconque.
 */

const COPY = {
  share: 'Partager ce calcul',
  shared: 'Lien copié',
  shareFailed: 'Copie impossible — sélectionnez le lien ci-dessous :',
};

describe('ShareButton', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/toolbox/imc');
  });

  it('copie un lien qui se décode en les valeurs saisies', async () => {
    const writeText = jest.fn().mockResolvedValue(undefined);
    Object.assign(navigator, { clipboard: { writeText } });

    render(<ShareButton values={{ weight: '80', height: '180' }} copy={COPY} />);
    fireEvent.click(screen.getByRole('button', { name: COPY.share }));

    await waitFor(() => expect(writeText).toHaveBeenCalledTimes(1));

    const copied = writeText.mock.calls[0][0] as string;
    const encoded = new URL(copied).searchParams.get(SHARE_PARAM);
    expect(decodeToolState(encoded)).toEqual({ weight: '80', height: '180' });
  });

  it('confirme la copie par un libellé temporaire', async () => {
    Object.assign(navigator, {
      clipboard: { writeText: jest.fn().mockResolvedValue(undefined) },
    });

    render(<ShareButton values={{ weight: '80' }} copy={COPY} />);
    fireEvent.click(screen.getByRole('button', { name: COPY.share }));

    expect(await screen.findByRole('button', { name: COPY.shared })).toBeInTheDocument();
  });

  it('expose le lien quand le presse-papiers est refusé', async () => {
    Object.assign(navigator, {
      clipboard: { writeText: jest.fn().mockRejectedValue(new Error('denied')) },
    });

    render(<ShareButton values={{ weight: '80' }} copy={COPY} />);
    fireEvent.click(screen.getByRole('button', { name: COPY.share }));

    expect(await screen.findByText(COPY.shareFailed)).toBeInTheDocument();
    const field = screen.getByRole('textbox', { name: COPY.share }) as HTMLInputElement;
    expect(field.value).toContain(`${SHARE_PARAM}=`);
  });

  it('préserve la langue courante dans le lien partagé', async () => {
    window.history.replaceState({}, '', '/toolbox/imc?lang=en');
    const writeText = jest.fn().mockResolvedValue(undefined);
    Object.assign(navigator, { clipboard: { writeText } });

    render(<ShareButton values={{ weight: '80' }} copy={COPY} />);
    fireEvent.click(screen.getByRole('button', { name: COPY.share }));

    await waitFor(() => expect(writeText).toHaveBeenCalledTimes(1));
    const copied = writeText.mock.calls[0][0] as string;
    expect(new URL(copied).searchParams.get('lang')).toBe('en');
  });
});
