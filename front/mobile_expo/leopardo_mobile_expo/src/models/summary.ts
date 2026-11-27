export type DailySummary = {
  hours_worked?: number;
  overtime_hours?: number;
  base_gain?: number;
  overtime_gain?: number;
  total_estimated?: number;
  currency?: string;
};

export type MonthlyBreakdownEntry = {
  date: string;
  hours?: number;
  overtime_hours?: number;
  base_gain?: number;
  overtime_gain?: number;
};

export type MonthlySummary = {
  employee_id?: number;
  name?: string;
  year?: number;
  month?: number;
  period?: { from?: string; to?: string };
  working_days?: number;
  days_present?: number;
  days_absent?: number;
  hours?: number;
  overtime_hours?: number;
  gross?: number;
  deductions?: number;
  net?: number;
  currency?: string;
  breakdown?: MonthlyBreakdownEntry[];
  disclaimer?: string;
};
