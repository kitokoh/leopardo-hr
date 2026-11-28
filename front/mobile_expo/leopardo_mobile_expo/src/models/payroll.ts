export type PaySlip = {
  id: number;
  employee_id: number;
  month: number;
  year: number;
  gross_salary: number;
  net_salary: number;
  currency: string;
  status: string;
  pdf_path?: string | null;
  validated_at?: string | null;
  period?: string;
  period_start?: string;
  period_end?: string;
};

export type PayrollMobileSummary = {
  currency?: string;
  current_month_net?: number;
  current_month_gross?: number;
  last_pay_slip?: PaySlip | null;
  pending_advances?: number;
  next_payment_date?: string | null;
  [key: string]: unknown;
};

export function formatMoney(
  amount: number | null | undefined,
  currency = 'DA',
): string {
  if (amount == null || Number.isNaN(amount)) return `— ${currency}`;
  return `${amount.toLocaleString('fr-FR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })} ${currency}`;
}

export function monthLabel(month: number, year: number): string {
  const date = new Date(year, Math.max(0, month - 1), 1);
  return date.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
}
