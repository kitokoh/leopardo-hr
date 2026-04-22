@extends('layouts.app')

@section('title', 'Invitations')

@section('content')
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Invitations envoyees</h1>
            <div class="mt-1 text-sm text-slate-400">Gerez les invitations de votre entreprise (renvoyer un lien si l'email a ete perdu).</div>
        </div>

        @if ($errors->any())
            <div class="rounded-md border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="rounded-lg border border-slate-800 bg-slate-900/40 p-4">
            <table class="min-w-full text-sm">
                <thead class="text-left text-slate-400">
                    <tr>
                        <th class="py-2 pr-4">Email</th>
                        <th class="py-2 pr-4">Role</th>
                        <th class="py-2 pr-4">Statut</th>
                        <th class="py-2 pr-4">Expire</th>
                        <th class="py-2 pr-4">Envoye</th>
                        <th class="py-2 pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($invitations as $invitation)
                        <tr>
                            <td class="py-2 pr-4">{{ $invitation->email }}</td>
                            <td class="py-2 pr-4">
                                {{ $invitation->role }}@if ($invitation->manager_role) / {{ $invitation->manager_role }}@endif
                            </td>
                            <td class="py-2 pr-4">
                                @if ($invitation->accepted_at)
                                    <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs text-emerald-300">activee</span>
                                @elseif ($invitation->expires_at && $invitation->expires_at->isPast())
                                    <span class="rounded-full bg-rose-500/20 px-2 py-0.5 text-xs text-rose-300">expiree</span>
                                @else
                                    <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-xs text-amber-300">en attente</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4">{{ optional($invitation->expires_at)->format('Y-m-d') }}</td>
                            <td class="py-2 pr-4">{{ optional($invitation->last_sent_at)->format('Y-m-d H:i') ?? '-' }}</td>
                            <td class="py-2 pr-4">
                                @if (! $invitation->accepted_at)
                                    <form method="POST" action="{{ route('hr.invitations.resend', $invitation->id) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md bg-emerald-500 px-3 py-1 text-xs font-medium text-slate-950 hover:bg-emerald-400">
                                            Renvoyer
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-3 text-slate-400">Aucune invitation pour cette societe.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
