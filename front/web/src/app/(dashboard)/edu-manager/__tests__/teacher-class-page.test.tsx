import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import TeacherClassPage from '../teacher/classes/[id]/page';

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

// useParams du setup global renvoie '/' — on surcharge pour la classe #7.
jest.mock('next/navigation', () => ({
  ...jest.requireActual('next/navigation'),
  useParams: () => ({ id: '7' }),
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

const roster = [
  { id: 11, student_number: 'S-001', display_name: 'Amina Diallo' },
  { id: 12, student_number: 'S-002', display_name: 'Karim Bensaïd' },
];

const assessment = {
  id: 21,
  title: 'Devoir de maths',
  type: 'exam',
  max_score: 20,
  coefficient: '1',
  assessment_date: '2026-09-01',
  published_at: null,
  subject: { id: 3, code: 'MATH', name: 'Mathématiques' },
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  mockedApiFetch
    .mockResolvedValueOnce(jsonResponse({ data: { id: 7, name: 'CM2-A', code: 'CM2A' } })) // classe
    .mockResolvedValueOnce(jsonResponse({ data: [
      { id: 1, student: roster[0], status: 'present', attendance_date: '2026-08-30' },
      { id: 2, student: roster[1], status: 'absent', attendance_date: '2026-08-29' },
    ] })) // historique présence → roster
    .mockResolvedValueOnce(jsonResponse({ data: [assessment] })); // évaluations
});

describe('EduManager classe enseignant (EDU-012)', () => {
  it('affiche le roster dérivé et permet la saisie de présence', async () => {
    render(<TeacherClassPage />);

    expect(await screen.findByText('CM2-A')).toBeInTheDocument();
    expect((await screen.findAllByText('Amina Diallo')).length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Karim Bensaïd').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Devoir de maths')).toBeInTheDocument();

    // Saisie de présence : présent pour l'élève 11, puis enregistrer.
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: { id: 99 } }, 201));
    await userEvent.selectOptions(screen.getAllByRole('combobox')[0], 'present');
    await userEvent.click(screen.getByText('Enregistrer la présence'));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/edu-manager/classes/7/attendances',
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"student_id":11'),
        }),
      );
    });

    expect(await screen.findByText('Présence enregistrée.')).toBeInTheDocument();
  });

  it('soumet une note pour validation (grade + publish)', async () => {
    render(<TeacherClassPage />);

    expect((await screen.findAllByText('Amina Diallo')).length).toBeGreaterThanOrEqual(1);

    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: { id: 501 } }, 201)); // POST grade
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: { id: 501, status: 'published' } })); // publish

    const scoreInputs = screen.getAllByRole('spinbutton');
    await userEvent.type(scoreInputs[0], '15');
    await userEvent.click(screen.getByText('Soumettre pour validation'));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/edu-manager/assessments/21/grades',
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"score":15'),
        }),
      );
      expect(mockedApiFetch).toHaveBeenCalledWith('/edu-manager/grades/501/publish', { method: 'POST' });
    });
  });
});
