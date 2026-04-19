@extends('layouts.app')

@section('title', 'Biometrie et bornes')

@section('content')
    <div class="space-y-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Biometrie et bornes d entree</h1>
                <p class="mt-1 text-sm text-slate-400">Validez les demandes visage / empreinte des collaborateurs et preparez les appareils de pointage a l entree.</p>
            </div>
        </div>

        @if (session('kiosk_credentials'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-6 text-emerald-100">
                <div class="text-sm font-semibold uppercase tracking-wide">Nouveaux identifiants borne</div>
                <div class="mt-3 text-sm">Code appareil: <strong>{{ session('kiosk_credentials.device_code') }}</strong></div>
                <div class="mt-2 text-sm">Token de sync: <strong>{{ session('kiosk_credentials.sync_token') }}</strong></div>
                <div class="mt-3 text-sm text-emerald-200/90">Conserve ce token tout de suite dans `zkteco-kiosk/config.json`. Il ne sera plus re-affiche en clair apres cette page.</div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6 lg:col-span-2">
                <h2 class="text-lg font-semibold">Demandes en attente</h2>
                <div class="mt-4 space-y-4">
                    @forelse($requests as $item)
                        <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="font-medium">Collaborateur #{{ $item->employee_id }} - {{ strtoupper($item->status) }}</div>
                                    <div class="mt-1 text-sm text-slate-400">Soumis le {{ optional($item->submitted_at)->format('Y-m-d H:i') ?? '-' }}</div>
                                    <div class="mt-2 text-sm text-slate-300">Visage: {{ $item->requested_face_enabled ? 'Oui' : 'Non' }} | Empreinte: {{ $item->requested_fingerprint_enabled ? 'Oui' : 'Non' }}</div>
                                    <div class="mt-1 text-sm text-slate-400">Ref visage: {{ $item->requested_face_reference_path ?? '-' }}</div>
                                    <div class="mt-1 text-sm text-slate-400">Ref empreinte: {{ $item->requested_fingerprint_reference_path ?? $item->requested_fingerprint_device_id ?? '-' }}</div>
                                    <div class="mt-1 text-sm text-slate-400">Note collaborateur: {{ $item->employee_note ?? '-' }}</div>
                                </div>
                                <div class="min-w-64 space-y-3">
                                    <form method="POST" action="{{ route('biometrics.requests.approve', $item->id) }}" class="space-y-2">
                                        @csrf
                                        <textarea name="manager_note" rows="2" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" placeholder="Note manager / RH"></textarea>
                                        <button type="submit" class="w-full rounded-lg bg-emerald-500 px-4 py-2 text-sm font-medium text-slate-950" @disabled($item->status !== 'pending')>Approuver</button>
                                    </form>
                                    <form method="POST" action="{{ route('biometrics.requests.reject', $item->id) }}" class="space-y-2">
                                        @csrf
                                        <textarea name="manager_note" rows="2" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" placeholder="Motif de rejet"></textarea>
                                        <button type="submit" class="w-full rounded-lg bg-rose-500 px-4 py-2 text-sm font-medium text-white" @disabled($item->status !== 'pending')>Rejeter</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-800 px-4 py-8 text-center text-slate-400">
                            Aucune demande biometrie pour le moment.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                    <h2 class="text-lg font-semibold">Nouvelle borne</h2>
                    <form method="POST" action="{{ route('biometrics.kiosks.store') }}" class="mt-4 space-y-3">
                        @csrf
                        <input name="name" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" placeholder="Nom de la borne">
                        <input name="location_label" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" placeholder="Entree principale / site">
                        <select name="biometric_mode" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2">
                            <option value="fingerprint">Empreinte</option>
                            <option value="face">Visage</option>
                            <option value="mixed">Mixte</option>
                        </select>
                        <button type="submit" class="w-full rounded-lg bg-emerald-500 px-4 py-3 font-medium text-slate-950">Creer la borne</button>
                    </form>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                    <h2 class="text-lg font-semibold">Bornes existantes</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($kiosks as $kiosk)
                            <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4">
                                <div class="font-medium">{{ $kiosk->name }}</div>
                                <div class="mt-1 text-sm text-slate-400">{{ $kiosk->location_label ?? '-' }}</div>
                                <div class="mt-1 text-sm text-slate-300">Code appareil: <strong>{{ $kiosk->device_code }}</strong></div>
                                <div class="mt-1 text-sm text-slate-400">Mode: {{ $kiosk->biometric_mode }}</div>
                                <a href="{{ route('kiosk.show', $kiosk->device_code) }}" target="_blank" class="mt-3 inline-flex rounded-lg border border-slate-700 px-3 py-2 text-sm">Ouvrir l interface borne</a>
                            </div>
                        @empty
                            <div class="text-sm text-slate-400">Aucune borne creee.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
