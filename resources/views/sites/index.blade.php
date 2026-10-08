<x-layouts.app title="Sites">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Sites</h1>
                <p class="mt-1 text-sm text-slate-400">{{ $sites->total() }} site(s) tracked.</p>
            </div>
            <a href="{{ route('sites.create') }}" class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400">
                Connect a site
            </a>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="mb-1 block text-xs text-slate-400" for="q">Search</label>
                <input id="q" name="q" value="{{ $search }}" placeholder="Name or URL"
                       class="rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-400" for="status">Status</label>
                <select id="status" name="status" class="rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    @foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Disabled'] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="dir" value="{{ $dir }}">
            <button class="rounded-md border border-white/15 px-3 py-2 text-sm text-slate-200 transition hover:bg-white/5">Filter</button>
            @if ($search !== '' || $status !== 'all')
                <a href="{{ route('sites.index') }}" class="py-2 text-sm text-slate-400 hover:text-slate-200">Clear</a>
            @endif
        </form>

        <div class="overflow-hidden rounded-xl border border-white/10">
            <table class="min-w-full divide-y divide-white/10 text-sm">
                <thead class="bg-white/5 text-left text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">
                            <a href="{{ route('sites.index', array_merge(request()->query(), ['sort' => 'name', 'dir' => ($sort === 'name' && $dir === 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-slate-200">Site</a>
                        </th>
                        <th class="px-4 py-3">Health</th>
                        <th class="px-4 py-3">
                            <a href="{{ route('sites.index', array_merge(request()->query(), ['sort' => 'last_run_at', 'dir' => ($sort === 'last_run_at' && $dir === 'asc') ? 'desc' : 'asc'])) }}" class="hover:text-slate-200">Last run</a>
                        </th>
                        <th class="px-4 py-3">Interval</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse ($sites as $site)
                        <tr class="hover:bg-white/5">
                            <td class="px-4 py-3">
                                <a href="{{ route('sites.show', $site) }}" class="font-medium hover:text-emerald-300">{{ $site->name }}</a>
                                <div class="text-xs text-slate-500">{{ $site->url ?: '—' }}</div>
                            </td>
                            <td class="px-4 py-3"><x-health-badge :health="$site->health()" /></td>
                            <td class="px-4 py-3 text-slate-300">
                                {{ $site->last_run_at?->diffForHumans() ?? 'never' }}
                            </td>
                            <td class="px-4 py-3 text-slate-400">{{ $site->expected_interval_minutes }}m (+{{ $site->grace_minutes }}m)</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('sites.show', $site) }}" class="text-emerald-300 hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-400">
                                No sites found.
                                <a href="{{ route('sites.create') }}" class="text-emerald-300 hover:underline">Connect your first site</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $sites->links() }}
    </div>
</x-layouts.app>
