<x-layouts.app :title="$site->name">
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ $site->name }}</h1>
                    <x-health-badge :health="$site->health()" />
                </div>
                <p class="mt-1 text-sm text-slate-400">
                    {{ $site->url ?: 'No URL' }} · {{ ucfirst($site->environment) }} · {{ $site->timezone }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <a href="{{ route('sites.edit', $site) }}" class="rounded-md border border-white/15 px-3 py-2 text-slate-200 transition hover:bg-white/5">Edit</a>
                <form method="POST" action="{{ route('sites.toggle', $site) }}">
                    @csrf
                    <button class="rounded-md border border-white/15 px-3 py-2 text-slate-200 transition hover:bg-white/5">
                        {{ $site->is_active ? 'Disable' : 'Enable' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('sites.rotate', $site) }}" onsubmit="return confirm('Rotate credentials? The current token and secret will stop working.')">
                    @csrf
                    <button class="rounded-md border border-amber-500/40 px-3 py-2 text-amber-200 transition hover:bg-amber-500/10">Rotate keys</button>
                </form>
                <form method="POST" action="{{ route('sites.destroy', $site) }}" onsubmit="return confirm('Delete this site and all of its runs, logs, and incidents?')">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-md border border-rose-500/40 px-3 py-2 text-rose-200 transition hover:bg-rose-500/10">Delete</button>
                </form>
            </div>
        </div>

        @if (session('credentials'))
            <div class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-5">
                <h2 class="text-sm font-semibold text-emerald-200">Client credentials — shown once</h2>
                <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-950 p-4 text-xs text-slate-200">SHIM_HUB_URL="{{ config('app.url') }}"
SHIM_SITE_UUID="{{ $site->uuid }}"
SHIM_TOKEN="{{ session('credentials')['token'] }}"
SHIM_SECRET="{{ session('credentials')['secret'] }}"</pre>
                <p class="mt-3 text-xs text-emerald-200/80">Example crontab entry:</p>
                <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-300">*/{{ $site->expected_interval_minutes }} * * * * www-data /usr/bin/php /usr/local/bin/cron-shim.php --env-file=/etc/cron-shim.env</pre>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Last run</div>
                <div class="mt-1 text-lg font-semibold">{{ $site->last_run_at?->diffForHumans() ?? 'never' }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Last status</div>
                <div class="mt-1 text-lg font-semibold">{{ $site->last_status?->label() ?? '—' }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Total runs</div>
                <div class="mt-1 text-lg font-semibold">{{ number_format($site->runs_count) }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Incidents</div>
                <div class="mt-1 text-lg font-semibold">{{ number_format($site->incidents_count) }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-white/10 bg-slate-900/60 p-5">
            <h2 class="text-sm font-semibold text-slate-200">Configuration</h2>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-400">Site UUID</dt><dd class="font-mono text-xs text-slate-200">{{ $site->uuid }}</dd></div>
                <div><dt class="text-slate-400">Expected interval</dt><dd class="text-slate-200">{{ $site->expected_interval_minutes }} minutes (+{{ $site->grace_minutes }}m grace)</dd></div>
                <div><dt class="text-slate-400">Last seen IP</dt><dd class="text-slate-200">{{ $site->last_seen_ip ?: '—' }}</dd></div>
                <div><dt class="text-slate-400">Created</dt><dd class="text-slate-200">{{ $site->created_at->toFormattedDateString() }}</dd></div>
                @if ($site->notes)
                    <div class="sm:col-span-2"><dt class="text-slate-400">Notes</dt><dd class="whitespace-pre-line text-slate-200">{{ $site->notes }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-white/10 bg-slate-900/60">
                <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-200">Recent runs</h2>
                    <a href="{{ route('runs.index', ['site' => $site->id]) }}" class="text-xs text-emerald-300 hover:underline">View all</a>
                </div>
                <div class="divide-y divide-white/10">
                    @forelse ($site->runs as $run)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <div class="flex items-center gap-3">
                                <x-status-badge :status="$run->status" />
                                <a href="{{ route('runs.show', $run) }}" class="text-slate-300 hover:text-white">{{ $run->started_at?->diffForHumans() ?? '—' }}</a>
                            </div>
                            <span class="text-xs text-slate-500">{{ number_format((int) $run->duration_ms) }} ms</span>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-sm text-slate-400">No runs reported yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-slate-900/60">
                <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-200">Open incidents</h2>
                    @if (\Illuminate\Support\Facades\Route::has('incidents.index'))
                        <a href="{{ route('incidents.index', ['site' => $site->id]) }}" class="text-xs text-emerald-300 hover:underline">View all</a>
                    @endif
                </div>
                <div class="divide-y divide-white/10">
                    @forelse ($openIncidents as $incident)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="text-slate-200">{{ $incident->type->label() }}</span>
                            <span class="text-xs text-slate-500">since {{ $incident->opened_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-sm text-slate-400">No open incidents.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
