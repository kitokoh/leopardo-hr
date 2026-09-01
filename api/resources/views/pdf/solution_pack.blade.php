<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>Leopardo — {{ $solutionName ?? $solutionCode }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; line-height: 1.6; }
        h1 { color: #059669; font-size: 22px; margin-bottom: 2px; }
        .subtitle { color: #64748b; font-size: 13px; margin-top: 0; }
        ul.packages { list-style: none; padding: 0; margin: 16px 0; }
        ul.packages li {
            border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; margin-bottom: 8px;
        }
        .label { font-weight: bold; }
        .type {
            float: right; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px;
            color: #059669; border: 1px solid #059669; border-radius: 999px; padding: 2px 8px;
        }
        h2 { font-size: 16px; color: #0f172a; margin-top: 24px; }
        ol { padding-left: 20px; }
        .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e2e8f0; color: #94a3b8; font-size: 10px; }
    </style>
</head>
<body>
    <h1>{{ __('solutions.pdf.title') }}</h1>
    <p class="subtitle">{{ $solutionName }} ({{ $solutionCode }})</p>

    @if (empty($packages))
        <p>{{ __('solutions.pdf.empty') }}</p>
    @else
        <ul class="packages">
            @foreach ($packages as $package)
                <li>
                    <span class="label">{{ __('solutions.' . $package['label_key']) }}</span>
                    <span class="type">{{ $package['type'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <h2>{{ __('solutions.pdf.next_steps') }}</h2>
    <ol>
        <li>{{ __('solutions.pdf.next_step_account') }}</li>
        <li>{{ __('solutions.pdf.next_step_install') }}</li>
        <li>{{ __('solutions.pdf.next_step_edge') }}</li>
    </ol>

    <p class="footer">{{ __('solutions.pdf.footer') }}</p>
</body>
</html>
