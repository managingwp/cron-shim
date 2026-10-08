@props(['status'])

@php
    $value = $status instanceof \App\Enums\RunStatus ? $status->value : (string) $status;
    $label = $status instanceof \App\Enums\RunStatus ? $status->label() : ucfirst($value);
    $classes = match ($value) {
        'success' => 'border-emerald-500/30 bg-emerald-500/15 text-emerald-300',
        'warning' => 'border-amber-500/30 bg-amber-500/15 text-amber-300',
        'failed' => 'border-rose-500/30 bg-rose-500/15 text-rose-300',
        default => 'border-slate-500/30 bg-slate-500/15 text-slate-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium {$classes}"]) }}>
    {{ $label }}
</span>
