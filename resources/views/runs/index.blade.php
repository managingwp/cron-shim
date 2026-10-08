<x-layouts.app title="Runs">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Runs</h1>
                <p class="mt-1 text-sm text-slate-400">{{ $runs->total() }} run(s) across all sites.</p>
            </div>
            <a href="{{ route('runs.index', array_merge(request()->query(), ['export' => 'csv'])) }}"
               class="rounded-md border border-white/15 px-3 py-2 text-sm text-slate-200 transition hover:bg-white/5">Export CSV</a>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3">
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
                <label class="mb-1 block text-xs text-slate-400" for="status">Status</label>
                <select id="status" name="status" class="rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                    <option value="">Any status</option>
                    @foreach (\App\Enums\RunStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-400" for="from">From</label>
                <input id="from" name="from" type="date" value="{{ $filters['from'] }}" class="rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-400" for="to">To</label>
                <input id="to" name="to" type="date" value="{{ $filters['to'] }}" class="rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm">
            </div>
            <button class="rounded-md border border-white/15 px-3 py-2 text-sm text-slate-200 transition hover:bg-white/5">Filter</button>
            <a href="{{ route('runs.index') }}" class="py-2 text-sm text-slate-400 hover:text-slate-200">Clear</a>
        </form>

        <div class="overflow-hidden rounded-xl border border-white/10">
            <table class="min-w-full divide-y divide-white/10 text-sm">
                <thead class="bg-white/5 text-left text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Site</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Started</th>
                        <th class="px-4 py-3">Duration</th>
                        <th class="px-4 py-3">Jobs</th>
                        <th class="px-4 py-3">Exit</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse ($runs as $run)
                        <tr class="hover:bg-white/5">
                            <td class="px-4 py-3">{{ $run->site?->name ?? '—' }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$run->status" /></td>
                            <td class="px-4 py-3 text-slate-300">{{ $run->started_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ number_format((int) $run->duration_ms) }} ms</td>
                            <td class="px-4 py-3 text-slate-400">{{ $run->jobs_run }} ({{ $run->jobs_failed }} failed)</td>
                            <td class="px-4 py-3 text-slate-400">{{ $run->exit_code }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('runs.show', $run) }}" class="text-emerald-300 hover:underline">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">No runs match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $runs->links() }}
    </div>
</x-layouts.app>
