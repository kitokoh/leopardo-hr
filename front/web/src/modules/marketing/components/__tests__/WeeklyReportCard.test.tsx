import { render, screen, waitFor } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import { WeeklyReportCard } from '@/modules/marketing/components/WeeklyReportCard';

/**
 * #7755 (backend #7753) — carte « Bilan hebdo » de la page social-marketing.
 */
jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function reportResponse(summarySource: 'ai' | 'fallback'): Response {
  return {
    ok: true,
    status: 200,
    json: async () => ({
      data: {
        period: { from: '2026-09-12T00:00:00Z', to: '2026-09-19T00:00:00Z' },
        stats: {
          posts_published: 4,
          posts_failed: 1,
          posts_scheduled_upcoming: 2,
          publications_by_platform: { linkedin: 3, twitter: 1 },
          campaigns_started: 2,
          campaign_emails_sent: 120,
        },
        summary: 'Bonne semaine marketing.',
        summary_source: summarySource,
      },
    }),
  } as unknown as Response;
}

beforeEach(() => {
  jest.clearAllMocks();
});

describe('WeeklyReportCard', () => {
  it('renders the 7-day aggregates and the AI summary', async () => {
    mockedApiFetch.mockResolvedValue(reportResponse('ai'));

    render(<WeeklyReportCard />);

    await waitFor(() => expect(screen.getByTestId('weekly-report-summary')).toBeInTheDocument());
    expect(mockedApiFetch).toHaveBeenCalledWith('/marketing/reports/weekly');

    expect(screen.getByTestId('weekly-report-published')).toHaveTextContent('4');
    expect(screen.getByTestId('weekly-report-failed')).toHaveTextContent('1');
    expect(screen.getByTestId('weekly-report-upcoming')).toHaveTextContent('2');
    expect(screen.getByTestId('weekly-report-emails')).toHaveTextContent('120');
    expect(screen.getByTestId('weekly-report-platforms')).toHaveTextContent(/linkedin/i);
    expect(screen.getByTestId('weekly-report-summary')).toHaveTextContent('Bonne semaine marketing.');
  });

  it('labels the deterministic fallback summary differently from the AI one', async () => {
    mockedApiFetch.mockResolvedValue(reportResponse('fallback'));

    render(<WeeklyReportCard />);
    await waitFor(() => expect(screen.getByTestId('weekly-report-summary')).toBeInTheDocument());

    // fr catalog: « Synthèse automatique » (fallback) vs « Synthèse IA ».
    expect(screen.queryByText(/synthèse ia/i)).not.toBeInTheDocument();
  });

  it('shows an error state when the report cannot be loaded', async () => {
    mockedApiFetch.mockRejectedValue(new Error('network down'));

    render(<WeeklyReportCard />);

    await waitFor(() => expect(screen.getByTestId('weekly-report-error')).toBeInTheDocument());
  });
});
