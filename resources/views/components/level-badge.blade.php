@props(['level'])

@php
    $value = $level instanceof \App\Enums\LogLevel ? $level->value : (string) $level;
    $classes = match ($value) {
        'debug' => 'text-slate-400',
        'info' => 'text-sky-300',
        'notice' => 'text-teal-300',
        'warning' => 'text-amber-300',
        'error' => 'text-rose-300',
        'critical' => 'text-fuchsia-300',
        default => 'text-slate-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "font-mono text-xs font-semibold uppercase {$classes}"]) }}>
    {{ $value }}
</span>
