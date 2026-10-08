<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-100 antialiased">
    <div class="grid min-h-full place-items-center px-4">
        <div class="w-full max-w-sm">
            <div class="mb-6 flex items-center justify-center gap-2 text-lg font-semibold">
                <span class="grid size-8 place-items-center rounded bg-emerald-500 text-slate-950">⏱</span>
                <span>{{ config('app.name') }}</span>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5 rounded-xl border border-white/10 bg-slate-900/60 p-6">
                @csrf

                <div>
                    <label for="email" class="mb-1 block text-sm text-slate-300">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                           class="w-full rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    @error('email')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm text-slate-300">Password</label>
                    <input id="password" name="password" type="password" required
                           class="w-full rounded-md border border-white/10 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    @error('password')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="remember" value="1" class="rounded border-white/20 bg-slate-950">
                    Remember me
                </label>

                <button type="submit" class="w-full rounded-md bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400">
                    Log in
                </button>
            </form>
        </div>
    </div>
</body>
</html>
