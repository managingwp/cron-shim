{{-- Page layout used by Livewire full-page components (see config/livewire.php). --}}
<x-layouts.app :title="$title ?? null">
    {{ $slot }}
</x-layouts.app>
