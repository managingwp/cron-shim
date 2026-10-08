<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CronLogEntry;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    /**
     * Fleet-wide log stream with filters and free-text search.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $entries = CronLogEntry::query()
            ->with(['site:id,name', 'run:id,run_uuid'])
            ->when($request->query('site'), fn ($query, $site) => $query->where('site_id', $site))
            ->when($request->query('level'), fn ($query, $level) => $query->where('level', $level))
            ->when($search !== '', fn ($query) => $query->where('message', 'like', "%{$search}%"))
            ->when($request->query('from'), fn ($query, $from) => $query->where('logged_at', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->where('logged_at', '<=', $to))
            ->latest('logged_at')
            ->paginate(50)
            ->withQueryString();

        return view('logs.index', [
            'entries' => $entries,
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
            'search' => $search,
            'filters' => [
                'site' => $request->query('site'),
                'level' => $request->query('level'),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ],
        ]);
    }
}
