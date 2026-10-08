<x-layouts.app title="Logs">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Logs</h1>
            <p class="mt-1 text-sm text-slate-400">{{ $entries->total() }} matching log entries.</p>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs text-slate-400" for="q">Search</label>
                <input id="q" name="q" value="{{ $search }}" placeholder="Message contains…"
                       class="rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm">
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
                <label class="mb-1 block text-xs text-slate-400" for="level">Level</label>
                <select id="level" name="level" class="rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                    <option value="">Any level</option>
                    @foreach (\App\Enums\LogLevel::cases() as $level)
                        <option value="{{ $level->value }}" @selected(($filters['level'] ?? '') === $level->value)>{{ $level->label() }}</option>
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
            <a href="{{ route('logs.index') }}" class="py-2 text-sm text-slate-400 hover:text-slate-200">Clear</a>
        </form>

        <div class="overflow-hidden rounded-xl border border-white/10">
            <table class="min-w-full divide-y divide-white/10 text-sm">
                <thead class="bg-white/5 text-left text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">Site</th>
                        <th class="px-4 py-3">Level</th>
                        <th class="px-4 py-3">Message</th>
                        <th class="px-4 py-3">Run</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse ($entries as $entry)
                        <tr class="hover:bg-white/5">
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ $entry->logged_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $entry->site?->name ?? '—' }}</td>
                            <td class="px-4 py-3"><x-level-badge :level="$entry->level" /></td>
                            <td class="px-4 py-3 text-slate-200">{{ \Illuminate\Support\Str::limit($entry->message, 160) }}</td>
                            <td class="px-4 py-3">
                                @if ($entry->run)
                                    <a href="{{ route('runs.show', $entry->run) }}" class="text-emerald-300 hover:underline">View</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No log entries match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $entries->links() }}
    </div>
</x-layouts.app>
