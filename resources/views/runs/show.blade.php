<x-layouts.app title="Run detail">
    <div class="space-y-6">
        <div>
            <a href="{{ route('runs.index') }}" class="text-sm text-slate-400 hover:text-slate-200">← All runs</a>
            <div class="mt-2 flex items-center gap-3">
                <h1 class="text-2xl font-semibold">{{ $run->site?->name ?? 'Unknown site' }}</h1>
                <x-status-badge :status="$run->status" />
            </div>
            <p class="mt-1 font-mono text-xs text-slate-500">{{ $run->run_uuid }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Started</div>
                <div class="mt-1 text-sm">{{ $run->started_at?->toDayDateTimeString() ?? '—' }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Duration</div>
                <div class="mt-1 text-sm">{{ number_format((int) $run->duration_ms) }} ms</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Exit code</div>
                <div class="mt-1 text-sm">{{ $run->exit_code }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Jobs</div>
                <div class="mt-1 text-sm">{{ $run->jobs_run }} run · {{ $run->jobs_failed }} failed</div>
            </div>
        </div>

        @if ($run->summary)
            <p class="text-sm text-slate-300">{{ $run->summary }}</p>
        @endif

        @if (! empty($run->meta))
            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-4">
                <div class="text-xs uppercase tracking-wide text-slate-400">Reported environment</div>
                <dl class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                    @foreach ($run->meta as $key => $value)
                        <div><dt class="inline text-slate-400">{{ $key }}:</dt> <dd class="inline">{{ is_scalar($value) ? $value : json_encode($value) }}</dd></div>
                    @endforeach
                </dl>
            </div>
        @endif

        <div class="rounded-xl border border-white/10">
            <div class="border-b border-white/10 px-4 py-3 text-sm font-semibold text-slate-200">
                Log entries ({{ $run->logEntries->count() }})
            </div>
            <div class="divide-y divide-white/10">
                @forelse ($run->logEntries as $entry)
                    <div class="flex items-start gap-4 px-4 py-3 text-sm">
                        <span class="w-40 shrink-0 text-xs text-slate-500">{{ $entry->logged_at?->toDayDateTimeString() ?? '—' }}</span>
                        <x-level-badge :level="$entry->level" class="w-16 shrink-0" />
                        <span class="whitespace-pre-wrap text-slate-200">{{ $entry->message }}</span>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-slate-400">This run reported no log entries.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
