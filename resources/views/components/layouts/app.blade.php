<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans text-slate-100 antialiased">
    <div class="min-h-full">
        <header class="border-b border-white/10 bg-slate-900/60">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
                <div class="flex items-center gap-6">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold">
                        <span class="grid size-7 place-items-center rounded bg-emerald-500 text-slate-950">⏱</span>
                        <span>{{ config('app.name') }}</span>
                    </a>
                    <nav class="hidden items-center gap-1 md:flex">
                        @foreach ([
                            ['route' => 'dashboard', 'label' => 'Dashboard'],
                            ['route' => 'sites.index', 'label' => 'Sites'],
                            ['route' => 'runs.index', 'label' => 'Runs'],
                            ['route' => 'logs.index', 'label' => 'Logs'],
                            ['route' => 'incidents.index', 'label' => 'Incidents'],
                            ['route' => 'settings.index', 'label' => 'Settings'],
                        ] as $item)
                            @if (\Illuminate\Support\Facades\Route::has($item['route']))
                                <a href="{{ route($item['route']) }}"
                                   @class([
                                       'rounded px-3 py-1.5 text-sm transition',
                                       'bg-white/10 text-white' => request()->routeIs($item['route']),
                                       'text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs($item['route']),
                                   ])>
                                    {{ $item['label'] }}
                                </a>
                            @endif
                        @endforeach
                    </nav>
                </div>
                @auth
                    <form method="POST" action="{{ route('logout') }}" class="flex items-center gap-3">
                        @csrf
                        <span class="hidden text-sm text-slate-400 sm:inline">{{ auth()->user()->email }}</span>
                        <button type="submit" class="rounded border border-white/15 px-3 py-1.5 text-sm text-slate-200 transition hover:bg-white/5">
                            Log out
                        </button>
                    </form>
                @endauth
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
