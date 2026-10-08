<x-layouts.app title="Incidents">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Incidents</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $incidents->total() }} incident(s).</p>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs text-slate-400" for="status">Status</label>
                <select id="status" name="status" class="rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                    @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'all' => 'All'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-400" for="site">Site</label>
                <select id="site" name="site" class="rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                    <option value="">All sites</option>
                    @foreach ($sites as $site)
                        <option value="{{ $site->id }}" @selected((string) ($filters['site'] ?? '') === (string) $site->id)>{{ $site->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-400" for="type">Type</label>
                <select id="type" name="type" class="rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                    <option value="">Any type</option>
                    @foreach (\App\Enums\IncidentType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-md border border-white/15 px-3 py-2 text-sm text-slate-200 transition hover:bg-white/5">Filter</button>
            <a href="{{ route('incidents.index') }}" class="py-2 text-sm text-slate-400 hover:text-slate-200">Clear</a>
        </form>

        <div class="space-y-3">
            @forelse ($incidents as $incident)
                <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-3">
                                <span class="font-semibold text-slate-100">{{ $incident->type->label() }}</span>
                                @if ($incident->isOpen())
                                    <span class="inline-flex items-center rounded-full border border-rose-500/30 bg-rose-500/15 px-2 py-0.5 text-xs font-medium text-rose-300">Open</span>
                                @else
                                    <span class="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-300">Resolved</span>
                                @endif
                                @if ($incident->acknowledged_at)
                                    <span class="text-xs text-slate-500">acknowledged</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-slate-400">
                                <a href="{{ route('sites.show', $incident->site) }}" class="text-emerald-300 hover:underline">{{ $incident->site?->name }}</a>
                                · opened {{ $incident->opened_at->diffForHumans() }}
                                @if ($incident->resolved_at)
                                    · resolved {{ $incident->resolved_at->diffForHumans() }}
                                @endif
                            </p>
                            @if (! empty($incident->details))
                                <pre class="mt-2 overflow-x-auto rounded bg-slate-950 p-2 text-xs text-slate-400">{{ json_encode($incident->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            @if ($incident->isOpen())
                                <form method="POST" action="{{ route('incidents.acknowledge', $incident) }}">
                                    @csrf
                                    <button class="rounded-md border border-white/15 px-3 py-1.5 text-slate-200 transition hover:bg-white/5">Acknowledge</button>
                                </form>
                                <form method="POST" action="{{ route('incidents.resolve', $incident) }}">
                                    @csrf
                                    <button class="rounded-md border border-emerald-500/40 px-3 py-1.5 text-emerald-200 transition hover:bg-emerald-500/10">Resolve</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-white/10 bg-slate-900/60 px-4 py-10 text-center text-slate-400">No incidents match these filters.</div>
            @endforelse
        </div>

        {{ $incidents->links() }}
    </div>
</x-layouts.app>
