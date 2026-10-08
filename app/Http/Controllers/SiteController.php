<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\Site;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SiteController extends Controller
{
    use AuthorizesRequests;

    /**
     * Fleet catalog with search, status filter, and sorting.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Site::class);

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $sort = (string) $request->query('sort', 'name');
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $sortable = ['name', 'last_run_at', 'created_at', 'expected_interval_minutes'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'name';
        }

        $sites = Site::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('url', 'like', "%{$search}%")))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy($sort, $dir)
            ->paginate(20)
            ->withQueryString();

        return view('sites.index', compact('sites', 'search', 'status', 'sort', 'dir'));
    }

    public function create(): View
    {
        $this->authorize('create', Site::class);

        return view('sites.create', ['site' => new Site(['timezone' => 'UTC', 'expected_interval_minutes' => 5, 'grace_minutes' => 10, 'is_active' => true])]);
    }

    public function store(StoreSiteRequest $request): RedirectResponse
    {
        $credentials = $this->newCredentials();

        $site = Site::query()->create([
            ...$request->validated(),
            'ingest_token_hash' => Hash::make($credentials['token']),
            'signing_secret' => $credentials['secret'],
        ]);

        return redirect()
            ->route('sites.show', $site)
            ->with('status', 'Site created. Store the credentials below — the token and secret are shown once.')
            ->with('credentials', $credentials);
    }

    public function show(Site $site): View
    {
        $this->authorize('view', $site);

        $site->loadCount(['runs', 'incidents']);
        $site->load(['runs' => fn ($query) => $query->latest('started_at')->limit(10)]);
        $openIncidents = $site->incidents()->open()->latest('opened_at')->limit(10)->get();

        return view('sites.show', compact('site', 'openIncidents'));
    }

    public function edit(Site $site): View
    {
        $this->authorize('update', $site);

        return view('sites.edit', compact('site'));
    }

    public function update(UpdateSiteRequest $request, Site $site): RedirectResponse
    {
        $site->update($request->validated());

        return redirect()->route('sites.show', $site)->with('status', 'Site updated.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorize('delete', $site);

        $site->delete();

        return redirect()->route('sites.index')->with('status', 'Site deleted.');
    }

    /**
     * Regenerate the site's ingest credentials, invalidating the old ones.
     */
    public function rotate(Site $site): RedirectResponse
    {
        $this->authorize('rotate', $site);

        $credentials = $this->newCredentials();

        $site->update([
            'ingest_token_hash' => Hash::make($credentials['token']),
            'signing_secret' => $credentials['secret'],
        ]);

        return redirect()
            ->route('sites.show', $site)
            ->with('status', 'Credentials rotated. Update the site client with the new values.')
            ->with('credentials', $credentials);
    }

    public function toggle(Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $site->update(['is_active' => ! $site->is_active]);

        return back()->with('status', $site->is_active ? 'Site enabled.' : 'Site disabled.');
    }

    /**
     * @return array{token: string, secret: string}
     */
    private function newCredentials(): array
    {
        return [
            'token' => Str::random(48),
            'secret' => Str::random(48),
        ];
    }
}
