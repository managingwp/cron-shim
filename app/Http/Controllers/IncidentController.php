<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    /**
     * Incident feed with status, site, and type filters.
     */
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'open');

        $incidents = Incident::query()
            ->with('site:id,name')
            ->when($status === 'open', fn ($query) => $query->open())
            ->when($status === 'resolved', fn ($query) => $query->resolved())
            ->when($request->query('site'), fn ($query, $site) => $query->where('site_id', $site))
            ->when($request->query('type'), fn ($query, $type) => $query->where('type', $type))
            ->latest('opened_at')
            ->paginate(30)
            ->withQueryString();

        return view('incidents.index', [
            'incidents' => $incidents,
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'status' => $status,
                'site' => $request->query('site'),
                'type' => $request->query('type'),
            ],
        ]);
    }

    public function resolve(Incident $incident): RedirectResponse
    {
        if ($incident->isOpen()) {
            $incident->update([
                'status' => IncidentStatus::Resolved,
                'resolved_at' => now(),
            ]);
        }

        return back()->with('status', 'Incident resolved.');
    }

    public function acknowledge(Incident $incident): RedirectResponse
    {
        $incident->update([
            'acknowledged_at' => now(),
            'last_notified_at' => now(),
        ]);

        return back()->with('status', 'Incident acknowledged.');
    }
}
