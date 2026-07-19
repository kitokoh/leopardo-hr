<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\I18nCatalog::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 12px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 22px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d9dee8; padding: 6px; text-align: left; }
        th { background: #eef4f1; }
        .muted { color: #64748b; }
        .metrics { margin-top: 16px; }
        .metric { display: inline-block; width: 23%; margin-right: 1%; padding: 8px; background: #f6f8fb; }
        .metric strong { display: block; font-size: 16px; }
        .note { margin-top: 8px; padding: 8px; background: #fff7ed; border: 1px solid #fed7aa; }
    </style>
</head>
<body>
    <h1>{{ __('pdf.attendance_report_title') }}</h1>
    <div class="muted">{{ $report['company']['name'] }} - {{ $report['period']['date_from'] }} au {{ $report['period']['date_to'] }}</div>

    <div class="metrics">
        <div class="metric"><span>{{ __('pdf.attendance_report_metric_employees') }}</span><strong>{{ $report['totals']['employees'] }}</strong></div>
        <div class="metric"><span>{{ __('pdf.attendance_report_metric_hours') }}</span><strong>{{ $report['totals']['worked_hours'] }}</strong></div>
        <div class="metric"><span>{{ __('pdf.attendance_report_metric_overtime') }}</span><strong>{{ $report['totals']['overtime_hours'] }}</strong></div>
        <div class="metric"><span>{{ __('pdf.attendance_report_metric_late_minutes') }}</span><strong>{{ $report['totals']['late_minutes'] }}</strong></div>
    </div>

    <div class="note">
        {{ __('pdf.attendance_report_estimated_note', ['gross' => $report['totals']['estimated_gross_payroll'], 'overtime' => $report['totals']['estimated_overtime_pay'], 'currency' => $report['company']['currency']]) }}
    </div>

    <h2>{{ __('pdf.attendance_report_detail_section') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('pdf.attendance_report_column_matricule') }}</th>
                <th>{{ __('pdf.attendance_report_column_name') }}</th>
                <th>{{ __('pdf.attendance_report_column_days') }}</th>
                <th>{{ __('pdf.attendance_report_column_hours') }}</th>
                <th>{{ __('pdf.attendance_report_column_overtime') }}</th>
                <th>{{ __('pdf.attendance_report_column_late_minutes') }}</th>
                <th>{{ __('pdf.attendance_report_column_missing_checkouts') }}</th>
                <th>{{ __('pdf.attendance_report_column_corrections') }}</th>
                <th>{{ __('pdf.attendance_report_column_estimate') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['employees'] as $employee)
                <tr>
                    <td>{{ $employee['matricule'] }}</td>
                    <td>{{ $employee['name'] }}</td>
                    <td>{{ $employee['worked_days'] }}</td>
                    <td>{{ $employee['worked_hours'] }}</td>
                    <td>{{ $employee['overtime_hours'] }}</td>
                    <td>{{ $employee['late_minutes'] }}</td>
                    <td>{{ $employee['missing_check_outs'] }}</td>
                    <td>{{ $employee['manual_corrections'] }}</td>
                    <td>{{ $employee['estimated_gross_amount'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
