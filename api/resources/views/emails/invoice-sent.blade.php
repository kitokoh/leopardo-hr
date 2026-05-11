<!doctype html>
<html lang="{{ $locale }}" @if($locale === 'ar') dir="rtl" @endif>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 30px; }
        .logo { font-size: 20px; font-weight: bold; color: #2563eb; margin-bottom: 20px; }
        .invoice-box { background: #f0f7ff; border: 1px solid #2563eb; border-radius: 6px; padding: 15px; margin: 15px 0; }
        .footer { margin-top: 30px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 15px; }
    </style>
</head>
<body>
<div class="container">
    <div class="logo">Leopardo RH</div>

    @if($locale === 'ar')
        <h2>فاتورتك جاهزة</h2>
        <p>مرحبًا، فيما يلي تفاصيل فاتورتك من <strong>{{ $company->name }}</strong>.</p>
    @elseif($locale === 'en')
        <h2>Your invoice is ready</h2>
        <p>Hello, here are the details of your invoice for <strong>{{ $company->name }}</strong>.</p>
    @else
        <h2>Votre facture est prête</h2>
        <p>Bonjour, voici les détails de votre facture pour <strong>{{ $company->name }}</strong>.</p>
    @endif

    <div class="invoice-box">
        <div><strong>{{ $locale === 'ar' ? 'رقم الفاتورة' : ($locale === 'en' ? 'Invoice Number' : 'Numéro') }} :</strong> {{ $invoice->invoice_number ?? '—' }}</div>
        <div><strong>{{ $locale === 'ar' ? 'المبلغ' : ($locale === 'en' ? 'Amount' : 'Montant') }} :</strong> {{ number_format($invoice->total ?? $invoice->amount ?? 0, 2) }} {{ $invoice->currency ?? 'EUR' }}</div>
        <div><strong>{{ $locale === 'ar' ? 'تاريخ الاستحقاق' : ($locale === 'en' ? 'Due Date' : 'Échéance') }} :</strong> {{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</div>
    </div>

    <div class="footer">
        @if($locale === 'ar')
            <p>هذه رسالة تلقائية من Leopardo RH.</p>
        @elseif($locale === 'en')
            <p>This is an automated message from Leopardo RH.</p>
        @else
            <p>Ceci est un message automatique de Leopardo RH.</p>
        @endif
    </div>
</div>
</body>
</html>
