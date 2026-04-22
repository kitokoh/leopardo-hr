@props(['status', 'label' => null])

@php
    $map = [
        'present' => ['color' => 'success', 'label' => 'Présent'],
        'late' => ['color' => 'warning', 'label' => 'En retard'],
        'absent' => ['color' => 'danger', 'label' => 'Absent'],
        'early_leave' => ['color' => 'warning', 'label' => 'Parti tôt'],
        'half_day' => ['color' => 'info', 'label' => 'Demi-journée'],
        'holiday' => ['color' => 'info', 'label' => 'Férié'],
        'weekend' => ['color' => 'info', 'label' => 'Week-end'],
        'on_leave' => ['color' => 'info', 'label' => 'En congé'],
        'pending' => ['color' => 'warning', 'label' => 'En attente'],
        'sent' => ['color' => 'info', 'label' => 'Envoyée'],
        'accepted' => ['color' => 'success', 'label' => 'Acceptée'],
        'expired' => ['color' => 'danger', 'label' => 'Expirée'],
        'revoked' => ['color' => 'slate-500', 'label' => 'Révoquée'],
        'active' => ['color' => 'success', 'label' => 'Actif'],
        'suspended' => ['color' => 'warning', 'label' => 'Suspendu'],
        'archived' => ['color' => 'slate-500', 'label' => 'Archivé'],
    ];
    $config = $map[$status] ?? ['color' => 'slate-500', 'label' => $status];
    $displayLabel = $label ?? $config['label'];
    $classes = match ($config['color']) {
        'success' => 'bg-success/10 text-success border-success/30',
        'warning' => 'bg-warning/10 text-warning border-warning/30',
        'danger' => 'bg-danger/10 text-danger border-danger/30',
        'info' => 'bg-info/10 text-info border-info/30',
        default => 'bg-slate-500/10 text-slate-400 border-slate-500/30',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold {$classes}"]) }}>
    {{ $displayLabel }}
</span>
