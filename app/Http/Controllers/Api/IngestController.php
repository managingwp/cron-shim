<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngestReportRequest;
use App\Models\Site;
use App\Services\IngestService;
use Illuminate\Http\JsonResponse;

class IngestController extends Controller
{
    public function __invoke(IngestReportRequest $request, IngestService $service): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('site');

        if ((string) $request->input('site_uuid') !== $site->uuid) {
            return response()->json(['message' => 'site_uuid does not match credentials.'], 422);
        }

        $result = $service->record($site, $request->validated(), $request->ip());

        if ($result['duplicate']) {
            return response()->json([
                'status' => 'duplicate',
                'run_uuid' => $result['run']->run_uuid,
            ]);
        }

        return response()->json([
            'status' => 'accepted',
            'run_uuid' => $result['run']->run_uuid,
        ], 202);
    }
}
