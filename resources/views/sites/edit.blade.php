<x-layouts.app :title="'Edit '.$site->name">
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Edit {{ $site->name }}</h1>
            <p class="mt-1 text-sm text-slate-400">Update monitoring settings for this site.</p>
        </div>

        <form method="POST" action="{{ route('sites.update', $site) }}" class="space-y-6 rounded-xl border border-white/10 bg-slate-900/60 p-6">
            @csrf
            @method('PUT')
            @include('sites._form', ['site' => $site])
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400">Save changes</button>
                <a href="{{ route('sites.show', $site) }}" class="text-sm text-slate-400 hover:text-slate-200">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.app>
