/**
 * ATS (#9461) — tests de l'éditeur de CV du portail carrières.
 *
 * Le CV vit dans `localStorage` : les tests verrouillent donc la persistance,
 * la tolérance à un contenu corrompu, et le fait que l'aperçu ne s'affiche
 * qu'une fois le nom renseigné.
 */

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ResumeBuilder } from '@/app/[companySlug]/careers/cv/ResumeBuilder';

const STORAGE_KEY = 'leopardo.candidate.cv.v1';

beforeEach(() => {
  window.localStorage.clear();
  jest.clearAllMocks();
});

describe('ResumeBuilder', () => {
  it('affiche une invitation tant que le nom est vide', () => {
    render(<ResumeBuilder companySlug="acme" />);

    expect(screen.getByText(/start by entering your name/i)).toBeInTheDocument();
  });

  it('affiche le nom dans l apercu des qu il est saisi', async () => {
    const user = userEvent.setup();
    render(<ResumeBuilder companySlug="acme" />);

    await user.type(screen.getByLabelText(/full name/i), 'Nadia Candidate');

    expect(await screen.findByRole('heading', { name: 'Nadia Candidate' })).toBeInTheDocument();
  });

  it('enregistre le CV dans le navigateur', async () => {
    const user = userEvent.setup();
    render(<ResumeBuilder companySlug="acme" />);

    await user.type(screen.getByLabelText(/full name/i), 'Nadia Candidate');

    await waitFor(
      () => {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        expect(stored).not.toBeNull();
        expect(JSON.parse(stored as string).fullName).toBe('Nadia Candidate');
      },
      { timeout: 2000 }
    );
  });

  it('recharge un CV deja enregistre', async () => {
    window.localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        fullName: 'Karim Existant',
        headline: 'Developpeur',
        email: '',
        phone: '',
        location: '',
        summary: '',
        skills: '',
        experience: [],
        education: [],
      })
    );

    render(<ResumeBuilder companySlug="acme" />);

    expect(await screen.findByRole('heading', { name: 'Karim Existant' })).toBeInTheDocument();
  });

  it('repart d un CV vide si le contenu enregistre est corrompu', async () => {
    // Un JSON invalide ne doit pas faire planter la page pour un candidat qui
    // revient : on repart d'un CV vide.
    window.localStorage.setItem(STORAGE_KEY, '{ceci nest pas du json');

    render(<ResumeBuilder companySlug="acme" />);

    expect(await screen.findByText(/start by entering your name/i)).toBeInTheDocument();
  });

  it('ajoute et supprime une experience', async () => {
    const user = userEvent.setup();
    render(<ResumeBuilder companySlug="acme" />);

    await user.click(screen.getByRole('button', { name: /add experience/i }));

    expect(screen.getByLabelText(/role/i)).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /remove/i }));

    expect(screen.queryByLabelText(/role/i)).not.toBeInTheDocument();
  });

  it('decoupe les competences sur les virgules dans l apercu', async () => {
    const user = userEvent.setup();
    render(<ResumeBuilder companySlug="acme" />);

    await user.type(screen.getByLabelText(/full name/i), 'Nadia');
    await user.type(screen.getByLabelText(/skills/i), 'PHP, Laravel , PostgreSQL');

    expect(await screen.findByText('Laravel')).toBeInTheDocument();
    expect(screen.getByText('PostgreSQL')).toBeInTheDocument();
  });

  it('desactive le telechargement tant que le CV est vide', () => {
    render(<ResumeBuilder companySlug="acme" />);

    expect(screen.getByRole('button', { name: /download as pdf/i })).toBeDisabled();
  });
});
