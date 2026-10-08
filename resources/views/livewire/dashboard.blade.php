<div class="space-y-6" wire:poll.30s>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-400">Fleet health, refreshed {{ $refreshedAt->diffForHumans() }}.</p>
        </div>
        <span class="text-xs text-slate-500" wire:loading>Refreshing…</span>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (['healthy' => 'Healthy', 'degraded' => 'Degraded', 'silent' => 'Silent', 'disabled' => 'Disabled'] as $key => $label)
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ $healthCounts[$key] ?? 0 }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
            <div class="text-xs uppercase tracking-wide text-slate-400">Open incidents</div>
            <div class="mt-1 text-2xl font-semibold {{ $openIncidentCount > 0 ? 'text-rose-300' : '' }}">{{ $openIncidentCount }}</div>
        </div>
        <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
            <div class="text-xs uppercase tracking-wide text-slate-400">Runs (24h)</div>
            <div class="mt-1 text-2xl font-semibold">{{ number_format($runsToday) }}</div>
        </div>
        <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
            <div class="text-xs uppercase tracking-wide text-slate-400">Failures (24h)</div>
            <div class="mt-1 text-2xl font-semibold {{ $failuresToday > 0 ? 'text-rose-300' : '' }}">{{ number_format($failuresToday) }}</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-white/10 bg-slate-900/60">
            <div class="border-b border-white/10 px-4 py-3 text-sm font-semibold text-slate-200">Open incidents</div>
            <div class="divide-y divide-white/10">
                @forelse ($openIncidents as $incident)
                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                        <div>
                            <span class="text-slate-200">{{ $incident->type->label() }}</span>
                            <span class="text-slate-500">· {{ $incident->site?->name }}</span>
                        </div>
                        <span class="text-xs text-slate-500">{{ $incident->opened_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-400">No open incidents. 🎉</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-white/10 bg-slate-900/60">
            <div class="border-b border-white/10 px-4 py-3 text-sm font-semibold text-slate-200">Slowest runs (24h)</div>
            <div class="divide-y divide-white/10">
                @forelse ($slowestRuns as $run)
                    <div class="flex items-center justify-between px-4 py-3 text-sm">
                        <a href="{{ route('runs.show', $run) }}" class="text-slate-300 hover:text-white">{{ $run->site?->name }}</a>
                        <span class="text-xs text-slate-500">{{ number_format((int) $run->duration_ms) }} ms</span>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-400">No runs in the last 24 hours.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div>
        <h2 class="mb-3 text-sm font-semibold text-slate-200">Sites</h2>
        @if ($sites->isEmpty())
            <div class="rounded-xl border border-white/10 bg-slate-900/60 px-4 py-10 text-center text-slate-400">
                No sites are connected yet.
                <a href="{{ route('sites.create') }}" class="text-emerald-300 hover:underline">Connect your first site</a>.
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($sites as $site)
                    <a href="{{ route('sites.show', $site) }}" data-testid="site-card"
                       class="rounded-xl border border-white/10 bg-slate-900/60 p-4 transition hover:border-white/20">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-slate-100">{{ $site->name }}</span>
                            <x-health-badge :health="$site->health()" />
                        </div>
                        <div class="mt-2 text-xs text-slate-400">
                            Last run: {{ $site->last_run_at?->diffForHumans() ?? 'never' }}
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
