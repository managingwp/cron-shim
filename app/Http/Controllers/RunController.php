<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CronRun;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunController extends Controller
{
    /**
     * Fleet-wide run history with filters, search, and CSV export.
     */
    public function index(Request $request): View|StreamedResponse
    {
        $query = $this->filtered($request)->with('site:id,name')->latest('started_at');

        if ($request->query('export') === 'csv') {
            return $this->export($query);
        }

        return view('runs.index', [
            'runs' => $query->paginate(30)->withQueryString(),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $this->filters($request),
        ]);
    }

    public function show(CronRun $run): View
    {
        $run->load([
            'site',
            'logEntries' => fn ($query) => $query->orderBy('logged_at'),
        ]);

        return view('runs.show', compact('run'));
    }

    /**
     * @return Builder<CronRun>
     */
    private function filtered(Request $request): Builder
    {
        return CronRun::query()
            ->when($request->query('site'), fn ($query, $site) => $query->where('site_id', $site))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('from'), fn ($query, $from) => $query->where('started_at', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->where('started_at', '<=', $to));
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'site' => $request->query('site'),
            'status' => $request->query('status'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];
    }

    /**
     * @param  Builder<CronRun>  $query
     */
    private function export(Builder $query): StreamedResponse
    {
        $filename = 'cron-runs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Site', 'Run UUID', 'Status', 'Started', 'Finished',
                'Duration (ms)', 'Exit code', 'Jobs run', 'Jobs failed', 'Summary',
            ]);

            $query->chunk(500, function ($runs) use ($handle): void {
                foreach ($runs as $run) {
                    fputcsv($handle, [
                        $run->site?->name,
                        $run->run_uuid,
                        $run->status->value,
                        $run->started_at?->toIso8601String(),
                        $run->finished_at?->toIso8601String(),
                        $run->duration_ms,
                        $run->exit_code,
                        $run->jobs_run,
                        $run->jobs_failed,
                        $run->summary,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
