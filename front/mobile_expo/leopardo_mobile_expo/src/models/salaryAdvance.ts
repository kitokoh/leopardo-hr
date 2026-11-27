export type SalaryAdvance = {
  id: number;
  employee_id: number;
  status: string;
  amount: number | null;
  currency: string;
  reason?: string | null;
  decision_comment?: string | null;
  repayment_months?: number | null;
  monthly_deduction?: number | null;
  amount_remaining?: number | null;
  requested_at?: string | null;
  created_at?: string | null;
};

export const ADVANCE_STATUS_LABELS: Record<string, string> = {
  pending: 'En attente',
  manager_approved: 'Validee manager',
  approved: 'Approuvee',
  paid: 'Payee',
  rejected: 'Refusee',
  cancelled: 'Annulee',
};

export function advanceStatusLabel(status: string): string {
  return ADVANCE_STATUS_LABELS[status] ?? status;
}
