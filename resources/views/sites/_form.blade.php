@php
    $site = $site ?? new App\Models\Site(['timezone' => 'UTC', 'expected_interval_minutes' => 5, 'grace_minutes' => 10, 'is_active' => true]);
    $input = 'w-full rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-emerald-400';
    $label = 'mb-1 block text-xs font-medium text-slate-400';
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="{{ $label }}" for="name">Name</label>
        <input id="name" name="name" value="{{ old('name', $site->name) }}" required class="{{ $input }}">
        @error('name') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="{{ $label }}" for="url">Site URL</label>
        <input id="url" name="url" type="url" value="{{ old('url', $site->url) }}" placeholder="https://example.com" class="{{ $input }}">
        @error('url') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="{{ $label }}" for="environment">Environment</label>
        <select id="environment" name="environment" class="{{ $input }}">
            @foreach (['production', 'staging', 'development'] as $environment)
                <option value="{{ $environment }}" @selected(old('environment', $site->environment ?? 'production') === $environment)>{{ ucfirst($environment) }}</option>
            @endforeach
        </select>
        @error('environment') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="{{ $label }}" for="timezone">Timezone</label>
        <input id="timezone" name="timezone" value="{{ old('timezone', $site->timezone ?? 'UTC') }}" class="{{ $input }}">
        @error('timezone') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="{{ $label }}" for="expected_interval_minutes">Expected interval (minutes)</label>
        <input id="expected_interval_minutes" name="expected_interval_minutes" type="number" min="1" max="1440" value="{{ old('expected_interval_minutes', $site->expected_interval_minutes ?? 5) }}" required class="{{ $input }}">
        @error('expected_interval_minutes') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="{{ $label }}" for="grace_minutes">Grace period (minutes)</label>
        <input id="grace_minutes" name="grace_minutes" type="number" min="0" max="1440" value="{{ old('grace_minutes', $site->grace_minutes ?? 10) }}" required class="{{ $input }}">
        @error('grace_minutes') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $site->is_active ?? true)) class="rounded border-white/20 bg-slate-950">
            Monitoring enabled
        </label>
    </div>

    <div class="sm:col-span-2">
        <label class="{{ $label }}" for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="3" class="{{ $input }}">{{ old('notes', $site->notes) }}</textarea>
        @error('notes') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
    </div>
</div>
