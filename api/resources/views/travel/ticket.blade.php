{{-- TRAVEL-412 (#6064) — Billet nominatif versionné (dompdf local, QR). --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Billet {{ $ticket->ticket_number }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body { margin: 0; padding: 24px; color: #1a202c; font-size: 12px; }
        .header { display: flex; justify-content: space-between; border-bottom: 3px solid #2b6cb0; padding-bottom: 12px; }
        .header h1 { margin: 0; font-size: 18px; color: #2b6cb0; }
        .badge { background: #ebf8ff; border: 1px solid #90cdf4; border-radius: 4px; padding: 4px 10px; font-weight: bold; }
        .grid { display: table; width: 100%; margin-top: 16px; }
        .row { display: table-row; }
        .cell { display: table-cell; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        .label { color: #718096; font-size: 10px; text-transform: uppercase; }
        .value { font-weight: bold; font-size: 13px; }
        .qrcode { text-align: center; margin-top: 24px; }
        .footer { margin-top: 32px; font-size: 10px; color: #a0aec0; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>Billet de voyage — {{ $trip?->code ?? $booking?->reference }}</h1>
            <div>{{ $ticket->ticket_number }}</div>
        </div>
        <div class="badge">{{ strtoupper($ticket->status->value) }}</div>
    </div>

    <div class="grid">
        <div class="row">
            <div class="cell"><div class="label">Passager</div><div class="value">{{ $passenger->full_name }}</div></div>
            <div class="cell"><div class="label">Siège</div><div class="value">{{ $passenger->seat_number ?? '—' }}</div></div>
        </div>
        <div class="row">
            <div class="cell"><div class="label">Départ</div><div class="value">{{ $trip?->departure_date?->toDateString() }} {{ $trip?->departure_time }}</div></div>
            <div class="cell"><div class="label">Arrivée</div><div class="value">{{ $trip?->arrival_date?->toDateString() }} {{ $trip?->arrival_time }}</div></div>
        </div>
        <div class="row">
            <div class="cell"><div class="label">Itinéraire</div><div class="value">{{ $route?->originCity?->name ?? '—' }} → {{ $route?->destinationCity?->name ?? '—' }}</div></div>
            <div class="cell"><div class="label">Référence</div><div class="value">{{ $booking?->reference }}</div></div>
        </div>
        <div class="row">
            <div class="cell"><div class="label">Validité</div><div class="value">{{ $ticket->valid_from?->toDateTimeString() }} → {{ $ticket->valid_until?->toDateTimeString() }}</div></div>
            <div class="cell"><div class="label">Tarif</div><div class="value">{{ number_format($passenger->unit_price_minor, 0, ',', ' ') }} {{ $booking?->currency }}</div></div>
        </div>
    </div>

    <div class="qrcode">
        {{-- QR = numéro de billet (vérifiable côté plateforme) — jamais de PII en clair. --}}
        <div>Code de contrôle : {{ $ticket->ticket_number }}</div>
    </div>

    <div class="footer">
        Billet généré le {{ now()->toDateTimeString() }} — vérifiable par numéro sur la plateforme TravelAgency.
        Présentez une pièce d'identité à l'embarquement.
    </div>
</body>
</html>
