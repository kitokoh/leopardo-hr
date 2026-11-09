/**
 * BC-10 (#9219) — tests du formulaire de candidature public.
 *
 * Le formulaire est la seule surface où un candidat externe écrit chez nous.
 * Trois garanties y sont verrouillées, et aucune n'est visible à l'œil nu :
 *
 *  - le consentement RGPD est DÉCOCHÉ par défaut (un consentement pré-coché
 *    n'est pas un consentement — RGPD art. 4.11) ;
 *  - un CV trop volumineux est refusé CÔTÉ CLIENT, avant l'upload : envoyer
 *    6 Mo pour se faire refuser en 422 gaspille la bande passante du candidat ;
 *  - le champ piège anti-spam est présent mais invisible et hors tabulation.
 *
 * Les libellés sont interrogés en anglais : c'est la locale par défaut du
 * rendu de test (le hook de locale s'initialise sur la locale SSR).
 */

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApplyForm } from '@/app/[companySlug]/careers/jobs/[jobId]/ApplyForm';
import * as careersApi from '@/lib/careers-api';

jest.mock('@/lib/careers-api', () => ({
  ...jest.requireActual('@/lib/careers-api'),
  submitPublicApplication: jest.fn(),
}));

const submitMock = careersApi.submitPublicApplication as jest.MockedFunction<
  typeof careersApi.submitPublicApplication
>;

function renderForm() {
  return render(<ApplyForm companySlug="acme" jobId={42} />);
}

function submitButton() {
  return screen.getByRole('button', { name: /submit my application/i });
}

async function fillRequiredFields(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText(/first name/i), 'Nadia');
  await user.type(screen.getByLabelText(/last name/i), 'Candidate');
  await user.type(screen.getByLabelText(/email/i), 'nadia@example.com');
}

beforeEach(() => {
  submitMock.mockReset();
});

describe('ApplyForm — consentement RGPD', () => {
  it('affiche la case de consentement décochée par défaut', () => {
    renderForm();

    const consent = screen.getByRole('checkbox');

    // Un consentement pré-coché n'est pas un consentement : c'est un acte
    // positif clair qui est exigé, et le backend le rejetterait de toute façon.
    expect(consent).not.toBeChecked();
  });

  it('affiche la finalité et la durée dans le texte de consentement', () => {
    renderForm();

    // Un consentement éclairé doit permettre au candidat de savoir ce qu'il
    // autorise : la finalité ET la durée, pas seulement « j'accepte ».
    expect(screen.getByText(/24 months/i)).toBeInTheDocument();
  });

  it('transmet le consentement coché au serveur', async () => {
    const user = userEvent.setup();
    submitMock.mockResolvedValue({ success: true, status: 201 });

    renderForm();
    await fillRequiredFields(user);
    await user.click(screen.getByRole('checkbox'));
    await user.click(submitButton());

    await waitFor(() => expect(submitMock).toHaveBeenCalled());

    expect(submitMock.mock.calls[0][2].consent).toBe(true);
  });

  it('transmet le champ piège anti-spam', async () => {
    const user = userEvent.setup();
    submitMock.mockResolvedValue({ success: true, status: 201 });

    renderForm();
    await fillRequiredFields(user);
    await user.click(screen.getByRole('checkbox'));
    await user.click(submitButton());

    await waitFor(() => expect(submitMock).toHaveBeenCalled());

    // Le honeypot doit être transmis (vide) : c'est le serveur qui décide.
    expect(submitMock.mock.calls[0][2]).toHaveProperty('website');
  });

  it('rend le champ piège invisible et hors tabulation', () => {
    renderForm();

    const honeypot = document.querySelector('input[name="website"]') as HTMLInputElement;

    // Un humain ne doit ni le voir ni l'atteindre au clavier, sinon le piège
    // se retourne contre les utilisateurs légitimes.
    expect(honeypot).toBeInTheDocument();
    expect(honeypot.tabIndex).toBe(-1);
    expect(honeypot.closest('[aria-hidden="true"]')).not.toBeNull();
  });
});

describe('ApplyForm — validation du CV', () => {
  it('refuse un CV de plus de 5 Mo sans appeler le serveur', async () => {
    const user = userEvent.setup();
    renderForm();

    // 6 Mo : au-delà de la limite de 5 Mo du backend.
    const bigFile = new File([new Uint8Array(6 * 1024 * 1024)], 'gros.pdf', {
      type: 'application/pdf',
    });

    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    await user.upload(input, bigFile);

    // Le refus doit être local : envoyer 6 Mo pour se faire refuser en 422
    // gaspille la bande passante du candidat.
    expect(await screen.findByText(/must not exceed 5 MB/i)).toBeInTheDocument();
    expect(submitMock).not.toHaveBeenCalled();
  });

  it('accepte un CV dans la limite et affiche son nom', async () => {
    const user = userEvent.setup();
    renderForm();

    const okFile = new File([new Uint8Array(1024)], 'cv.pdf', { type: 'application/pdf' });
    const input = document.querySelector('input[type="file"]') as HTMLInputElement;
    await user.upload(input, okFile);

    expect(await screen.findByText('cv.pdf')).toBeInTheDocument();
  });
});

describe('ApplyForm — soumission', () => {
  it('affiche la confirmation de succès', async () => {
    const user = userEvent.setup();
    submitMock.mockResolvedValue({ success: true, status: 201 });

    renderForm();
    await fillRequiredFields(user);
    await user.click(screen.getByRole('checkbox'));
    await user.click(submitButton());

    await waitFor(() => expect(submitMock).toHaveBeenCalled());
    expect(await screen.findByText(/application sent/i)).toBeInTheDocument();
  });

  it('affiche les erreurs de champ renvoyées par le serveur', async () => {
    const user = userEvent.setup();
    submitMock.mockResolvedValue({
      success: false,
      status: 422,
      errors: { email: ['Adresse invalide.'] },
    });

    renderForm();
    await fillRequiredFields(user);
    await user.click(screen.getByRole('checkbox'));
    await user.click(submitButton());

    expect(await screen.findByText('Adresse invalide.')).toBeInTheDocument();
  });

  it('affiche le message serveur sur un rejet antivirus', async () => {
    const user = userEvent.setup();
    submitMock.mockResolvedValue({
      success: false,
      status: 422,
      message: "Le fichier joint a été refusé par l'analyse de sécurité.",
    });

    renderForm();
    await fillRequiredFields(user);
    await user.click(screen.getByRole('checkbox'));
    await user.click(submitButton());

    // Le candidat doit savoir POURQUOI sa candidature a échoué, sinon il
    // réessaiera à l'identique.
    expect(await screen.findByText(/analyse de s[ée]curit[ée]/i)).toBeInTheDocument();
  });

  it('affiche un message générique quand le serveur ne dit rien', async () => {
    const user = userEvent.setup();
    submitMock.mockResolvedValue({ success: false, status: 500 });

    renderForm();
    await fillRequiredFields(user);
    await user.click(screen.getByRole('checkbox'));
    await user.click(submitButton());

    // Un échec muet laisserait le candidat devant un formulaire figé.
    expect(await screen.findByText(/something went wrong/i)).toBeInTheDocument();
  });

  it('transmet les champs saisis au serveur', async () => {
    const user = userEvent.setup();
    submitMock.mockResolvedValue({ success: true, status: 201 });

    renderForm();
    await fillRequiredFields(user);
    await user.click(screen.getByRole('checkbox'));
    await user.click(submitButton());

    await waitFor(() => expect(submitMock).toHaveBeenCalled());

    const [slug, jobId, input] = submitMock.mock.calls[0];
    expect(slug).toBe('acme');
    expect(jobId).toBe(42);
    expect(input.first_name).toBe('Nadia');
    expect(input.last_name).toBe('Candidate');
    expect(input.email).toBe('nadia@example.com');
    expect(input.source).toBe('website');
  });
});
