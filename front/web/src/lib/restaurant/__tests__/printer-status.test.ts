import {
  canRequeue,
  derivePrinterStatus,
  failedPrintJobs,
  PRINTER_OFFLINE_THRESHOLD_MS,
  type PrintJobLike,
} from '../printer-status';

const NOW = Date.parse('2026-10-24T12:00:00Z');

function job(overrides: Partial<PrintJobLike> = {}): PrintJobLike {
  return {
    id: 1,
    status: 'printed',
    printer_ref: 'PRN-01',
    attempts: 1,
    last_error: null,
    created_at: '2026-10-24T11:59:00Z',
    ...overrides,
  };
}

describe('derivePrinterStatus (#8607)', () => {
  it('aucun travail → not_configured (jamais ready sans preuve)', () => {
    expect(derivePrinterStatus([], NOW)).toBe('not_configured');
  });

  it('aucun travail ne porte de printer_ref → not_configured', () => {
    const jobs = [job({ printer_ref: null }), job({ id: 2, printer_ref: '' })];
    expect(derivePrinterStatus(jobs, NOW)).toBe('not_configured');
  });

  it('un travail configuré et non bloqué → ready', () => {
    expect(derivePrinterStatus([job()], NOW)).toBe('ready');
  });

  it('un travail pending au-delà du seuil → offline', () => {
    const stuck = job({
      status: 'pending',
      created_at: new Date(NOW - PRINTER_OFFLINE_THRESHOLD_MS - 1000).toISOString(),
    });
    expect(derivePrinterStatus([job(), stuck], NOW)).toBe('offline');
  });

  it('un travail pending DANS le seuil ne déclare pas offline', () => {
    const recent = job({
      status: 'pending',
      created_at: new Date(NOW - 1000).toISOString(),
    });
    expect(derivePrinterStatus([recent], NOW)).toBe('ready');
  });

  it('un travail claimed bloqué → offline', () => {
    const stuck = job({
      status: 'claimed',
      created_at: new Date(NOW - PRINTER_OFFLINE_THRESHOLD_MS - 1).toISOString(),
    });
    expect(derivePrinterStatus([stuck], NOW)).toBe('offline');
  });

  it('un travail failed n’est PAS « offline » (il est en échec, pas muet)', () => {
    const failed = job({ status: 'failed', last_error: 'paper jam' });
    expect(derivePrinterStatus([failed], NOW)).toBe('ready');
  });

  it('une date invalide ne déclare pas offline (fail-safe)', () => {
    const bad = job({ status: 'pending', created_at: 'not-a-date' });
    expect(derivePrinterStatus([bad], NOW)).toBe('ready');
  });
});

describe('failedPrintJobs / canRequeue (#8607)', () => {
  it('ne retient que les travaux en échec', () => {
    const jobs = [
      job({ id: 1, status: 'printed' }),
      job({ id: 2, status: 'failed' }),
      job({ id: 3, status: 'pending' }),
    ];
    expect(failedPrintJobs(jobs).map((j) => j.id)).toEqual([2]);
  });

  it('un travail failed ou cancelled est relançable, un printed non', () => {
    expect(canRequeue(job({ status: 'failed' }))).toBe(true);
    expect(canRequeue(job({ status: 'cancelled' }))).toBe(true);
    expect(canRequeue(job({ status: 'printed' }))).toBe(false);
    expect(canRequeue(job({ status: 'pending' }))).toBe(false);
  });
});
