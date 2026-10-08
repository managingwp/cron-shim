<x-layouts.app title="Settings">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Settings</h1>
            <p class="mt-1 text-sm text-slate-400">Notification channels for incident alerts.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-white/10 bg-slate-900/60">
                <div class="border-b border-white/10 px-4 py-3 text-sm font-semibold text-slate-200">Channels</div>
                <div class="divide-y divide-white/10">
                    @forelse ($channels as $channel)
                        <div class="flex items-center justify-between px-4 py-3">
                            <div>
                                <div class="text-sm font-medium text-slate-100">{{ $channel->name }}</div>
                                <div class="text-xs text-slate-400">
                                    {{ $channel->type->label() }}
                                    @if ($channel->type === \App\Enums\NotificationChannelType::Email)
                                        · {{ implode(', ', $channel->config['recipients'] ?? []) }}
                                    @else
                                        · {{ \Illuminate\Support\Str::limit($channel->config['webhook_url'] ?? '', 40) }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('settings.channels.test', $channel) }}">
                                    @csrf
                                    <button class="rounded-md border border-white/15 px-3 py-1.5 text-xs text-slate-200 transition hover:bg-white/5">Test</button>
                                </form>
                                <form method="POST" action="{{ route('settings.channels.destroy', $channel) }}" onsubmit="return confirm('Remove this channel?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-md border border-rose-500/40 px-3 py-1.5 text-xs text-rose-200 transition hover:bg-rose-500/10">Remove</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-sm text-slate-400">No channels yet. Add one to receive incident alerts.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-slate-900/60 p-5">
                <h2 class="text-sm font-semibold text-slate-200">Add a channel</h2>
                <form method="POST" action="{{ route('settings.channels.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs text-slate-400" for="name">Name</label>
                        <input id="name" name="name" value="{{ old('name') }}" required class="w-full rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-400" for="type">Type</label>
                        <select id="type" name="type" class="w-full rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                            <option value="email" @selected(old('type') === 'email')>Email</option>
                            <option value="slack" @selected(old('type') === 'slack')>Slack</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-400" for="recipients">Email recipients (one per line)</label>
                        <textarea id="recipients" name="recipients" rows="2" class="w-full rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm">{{ old('recipients') }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-400" for="webhook_url">Slack webhook URL</label>
                        <input id="webhook_url" name="webhook_url" value="{{ old('webhook_url') }}" placeholder="https://hooks.slack.com/services/…" class="w-full rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm">
                    </div>
                    <button class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400">Add channel</button>
                </form>
            </div>
        </div>

        <div class="rounded-xl border border-white/10 bg-slate-900/60">
            <div class="border-b border-white/10 px-4 py-3 text-sm font-semibold text-slate-200">Recent deliveries</div>
            <div class="divide-y divide-white/10">
                @forelse ($logs as $log)
                    <div class="flex items-center justify-between px-4 py-2 text-sm">
                        <span class="text-slate-300">{{ $log->subject }}</span>
                        <span class="text-xs {{ $log->status === 'sent' ? 'text-emerald-300' : 'text-rose-300' }}">
                            {{ $log->status }} · {{ $log->channel?->name ?? '—' }} · {{ $log->created_at->diffForHumans() }}
                        </span>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-sm text-slate-400">No notifications sent yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
