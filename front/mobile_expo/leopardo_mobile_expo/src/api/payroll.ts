import { api, unwrap, unwrapList } from './client';
import type { PaySlip, PayrollMobileSummary } from '../models/payroll';
import type { SalaryAdvance } from '../models/salaryAdvance';

/** Synthese paie mobile. GET /payroll/mobile-summary */
export async function fetchPayrollSummary(): Promise<PayrollMobileSummary> {
  const { data } = await api.get('/payroll/mobile-summary');
  return unwrap<PayrollMobileSummary>(data);
}

/** Mes bulletins de paie. GET /me/pay-slips */
export async function fetchPaySlips(): Promise<PaySlip[]> {
  const { data } = await api.get('/me/pay-slips', { params: { per_page: 50 } });
  return unwrapList<PaySlip>(data);
}

/** Mes avances sur salaire. GET /salary-advances */
export async function fetchSalaryAdvances(): Promise<SalaryAdvance[]> {
  const { data } = await api.get('/salary-advances', { params: { per_page: 50 } });
  return unwrapList<SalaryAdvance>(data);
}

export type CreateAdvanceInput = { amount: number; reason?: string };

/** Demande d'avance. POST /salary-advances */
export async function createSalaryAdvance(
  input: CreateAdvanceInput,
): Promise<SalaryAdvance> {
  const { data } = await api.post('/salary-advances', {
    amount: input.amount,
    ...(input.reason ? { reason: input.reason } : {}),
  });
  return unwrap<SalaryAdvance>(data);
}
