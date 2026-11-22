import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import EduManagerHomePage from '../page';
import FeeTypesPage from '../fee-types/page';
import GuardiansPage from '../guardians/page';
import ReportsPage from '../reports/page';

/**
 * #9789 — outils de gestion scolaire sans page jusqu'ici : types de frais,
 * responsables légaux et rapports. Ces tests vérifient que chaque écran
 * charge la BONNE ressource API, affiche les données reçues, et que les gestes
 * d'écriture (création d'un type de frais, rattachement d'un responsable)
 * partent bien vers les endpoints existants — pas de page « vitrine » vide.
 */

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status: number;
    constructor(message: string, status: number) {
      super(message);
      this.status = status;
    }
  },
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown, status = 200): Response {
  return {
    json: async () => payload,
    ok: status >= 200 && status < 300,
    status,
    headers: new Headers(),
    clone: () => jsonResponse(payload, status),
  } as unknown as Response;
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('EduManager fee types (#9789)', () => {
  it('liste les types de frais depuis /edu-manager/fee-types', async () => {
    mockedApiFetch.mockResolvedValueOnce(
      jsonResponse({
        data: [
          {
            id: 1,
            code: 'TUITION',
            label: 'Scolarité',
            amount: '15000.00',
            currency: 'DZD',
            billing_frequency: 'term',
          },
        ],
      }),
    );

    render(<FeeTypesPage />);

    expect(await screen.findByText('TUITION')).toBeInTheDocument();
    expect(screen.getByText('Scolarité')).toBeInTheDocument();
    expect(screen.getByText('15000.00 DZD')).toBeInTheDocument();

    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/edu-manager/fee-types?per_page=200',
      expect.objectContaining({ _cacheBust: true }),
    );
  });

  it('crée un type de frais via POST /edu-manager/fee-types', async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: [] }));
    render(<FeeTypesPage />);

    await screen.findByText(/Aucun type de frais/);

    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: { id: 2 } }, 201));
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: [] }));

    await userEvent.click(screen.getByText('Nouveau type de frais'));
    await userEvent.type(screen.getByLabelText('Code'), 'CANTEEN');
    await userEvent.type(screen.getByLabelText('Libellé'), 'Cantine');
    await userEvent.type(screen.getByLabelText('Montant'), '3000');
    await userEvent.type(screen.getByLabelText('Devise'), 'DZD');
    await userEvent.click(screen.getByText('Enregistrer'));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/edu-manager/fee-types',
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('CANTEEN'),
        }),
      );
    });
  });
});

describe('EduManager guardians (#9789)', () => {
  it('liste les responsables légaux et masque leur contact (PII)', async () => {
    mockedApiFetch.mockResolvedValueOnce(
      jsonResponse({
        data: [
          {
            id: 7,
            first_name: 'Parent',
            last_name: 'Démo',
            relationship_code: 'parent',
            contact_reference: '+213000000000',
          },
        ],
      }),
    );

    render(<GuardiansPage />);

    expect(await screen.findByText('Parent Démo')).toBeInTheDocument();
    // Le contact chiffré n'est jamais affiché en clair.
    expect(screen.queryByText('+213000000000')).not.toBeInTheDocument();
    expect(screen.getByText('••••')).toBeInTheDocument();

    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/edu-manager/guardians?per_page=200',
      expect.objectContaining({ _cacheBust: true }),
    );
  });

  it('rattache un responsable à un élève via POST /students/{id}/guardians', async () => {
    mockedApiFetch.mockResolvedValueOnce(
      jsonResponse({
        data: [{ id: 7, first_name: 'Parent', last_name: 'Démo', relationship_code: 'parent' }],
      }),
    );

    render(<GuardiansPage />);

    await screen.findByText('Parent Démo');

    // Ouverture du rattachement : charge la liste des élèves.
    mockedApiFetch.mockResolvedValueOnce(
      jsonResponse({ data: [{ id: 42, display_name: 'Élève Démo Un' }] }),
    );

    await userEvent.click(screen.getByTitle('Rattacher'));

    await screen.findByText('Élève Démo Un');

    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: { id: 1 } }, 201));
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: [] }));

    await userEvent.selectOptions(screen.getByLabelText('Élève'), '42');
    // Le libellé « Rattacher » existe deux fois (action de ligne + bouton de la
    // modale) : on cible le bouton de la modale, qui est le dernier rendu.
    const linkButtons = screen.getAllByRole('button', { name: 'Rattacher' });
    await userEvent.click(linkButtons[linkButtons.length - 1]);

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/edu-manager/students/42/guardians',
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"guardian_id":7'),
        }),
      );
    });
  });
});

describe('EduManager reports (#9789)', () => {
  it('charge les 4 rapports agrégés', async () => {
    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse({ data: [{ class_id: 1, present: 12 }] }))
      .mockResolvedValueOnce(jsonResponse({ data: [{ campus_id: 1, students: 30 }] }))
      .mockResolvedValueOnce(jsonResponse({ data: [{ subject: 'MATH', average: 14.5 }] }))
      .mockResolvedValueOnce(jsonResponse({ data: [{ class_id: 1, capacity: 30 }] }));

    render(<ReportsPage />);

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/edu-manager/reports/presence',
        expect.objectContaining({ _cacheBust: true }),
      );
    });

    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/edu-manager/reports/enrollment',
      expect.objectContaining({ _cacheBust: true }),
    );
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/edu-manager/reports/results',
      expect.objectContaining({ _cacheBust: true }),
    );
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/edu-manager/reports/capacity',
      expect.objectContaining({ _cacheBust: true }),
    );

    // Les colonnes sont dérivées des données réellement reçues.
    expect(await screen.findByText('present')).toBeInTheDocument();
    expect(await screen.findByText('students')).toBeInTheDocument();
  });
});

describe('EduManager home — outils de gestion (#9789)', () => {
  it('expose les raccourcis frais, responsables et rapports', async () => {
    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse({ data: [], meta: { total: 1 } }))
      .mockResolvedValueOnce(jsonResponse({ data: [], meta: { total: 2 } }))
      .mockResolvedValueOnce(jsonResponse({ data: [], meta: { total: 3 } }))
      .mockResolvedValueOnce(jsonResponse({ data: [], meta: { total: 0 } }))
      .mockResolvedValueOnce(jsonResponse({ data: [], meta: { total: 0 } }));

    render(<EduManagerHomePage />);

    expect(await screen.findByText('Types de frais')).toBeInTheDocument();
    expect(screen.getByText('Responsables légaux')).toBeInTheDocument();
    expect(screen.getByText('Rapports')).toBeInTheDocument();
  });
});
