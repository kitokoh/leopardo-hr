/**
 * ATS (#9461) — tests du suivi de candidature public.
 *
 * Cette page rend l'état d'un dossier à partir d'une adresse email. Les tests
 * verrouillent ce que le candidat voit, et surtout ce qu'il ne voit pas : le
 * backend ne renvoie déjà pas les notes internes, mais l'écran ne doit pas non
 * plus les afficher s'il les recevait.
 */

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { TrackApplicationsForm } from '@/app/[companySlug]/careers/applications/TrackApplicationsForm';
import * as trackingApi from '@/lib/candidate-tracking-api';

jest.mock('@/lib/candidate-tracking-api', () => ({
  ...jest.requireActual('@/lib/candidate-tracking-api'),
  trackApplications: jest.fn(),
}));

const trackMock = trackingApi.trackApplications as jest.MockedFunction<
  typeof trackingApi.trackApplications
>;

function renderForm() {
  return render(<TrackApplicationsForm companySlug="acme" />);
}

beforeEach(() => {
  jest.clearAllMocks();
});

describe('TrackApplicationsForm', () => {
  it('refuse une adresse invalide sans appeler le serveur', async () => {
    const user = userEvent.setup();
    renderForm();

    await user.type(screen.getByLabelText(/email address/i), 'pas-un-email');
    await user.click(screen.getByRole('button', { name: /show my applications/i }));

    expect(trackMock).not.toHaveBeenCalled();
    expect(await screen.findByRole('alert')).toHaveTextContent(/valid email/i);
  });

  it('affiche les candidatures trouvees avec leur statut', async () => {
    trackMock.mockResolvedValue({
      success: true,
      retentionDays: 730,
      applications: [
        {
          reference: 'CAND-000042',
          job_title: 'Developpeur Backend',
          job_location: 'Alger',
          contract_type: 'cdi',
          status: 'screening',
          applied_at: '2026-11-01T10:00:00+00:00',
          last_update: '2026-11-05T10:00:00+00:00',
        },
      ],
    });

    const user = userEvent.setup();
    renderForm();

    await user.type(screen.getByLabelText(/email address/i), 'nadia@example.com');
    await user.click(screen.getByRole('button', { name: /show my applications/i }));

    expect(await screen.findByText('Developpeur Backend')).toBeInTheDocument();
    expect(screen.getByText('CAND-000042')).toBeInTheDocument();
    expect(screen.getByText(/under review/i)).toBeInTheDocument();
    expect(screen.getByText(/730 days/i)).toBeInTheDocument();
  });

  it('affiche un message explicite quand aucune candidature ne correspond', async () => {
    trackMock.mockResolvedValue({ success: true, applications: [], retentionDays: 730 });

    const user = userEvent.setup();
    renderForm();

    await user.type(screen.getByLabelText(/email address/i), 'inconnu@example.com');
    await user.click(screen.getByRole('button', { name: /show my applications/i }));

    expect(await screen.findByText(/no application found/i)).toBeInTheDocument();
  });

  it('ne pretend pas qu il n y a pas de candidature quand le serveur echoue', async () => {
    // Une panne reseau ne doit pas etre presentee comme « aucune candidature » :
    // le candidat croirait son dossier perdu.
    trackMock.mockResolvedValue({ success: false, applications: [], retentionDays: null });

    const user = userEvent.setup();
    renderForm();

    await user.type(screen.getByLabelText(/email address/i), 'nadia@example.com');
    await user.click(screen.getByRole('button', { name: /show my applications/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent(/failed/i);
    expect(screen.queryByText(/no application found/i)).not.toBeInTheDocument();
  });

  it('transmet l email saisi au client de suivi', async () => {
    trackMock.mockResolvedValue({ success: true, applications: [], retentionDays: 730 });

    const user = userEvent.setup();
    renderForm();

    await user.type(screen.getByLabelText(/email address/i), '  nadia@example.com  ');
    await user.click(screen.getByRole('button', { name: /show my applications/i }));

    await waitFor(() => {
      expect(trackMock).toHaveBeenCalledWith('acme', 'nadia@example.com');
    });
  });
});
