<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSite
{
    /**
     * Authenticate an ingest request from a site's reporting client.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maxKb = (int) config('cronshim.ingest.max_body_kb');

        if ($maxKb > 0 && strlen($request->getContent()) > $maxKb * 1024) {
            return $this->reject($request, 'Payload too large.', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $uuid = $request->header('X-Shim-Site');
        $token = $request->bearerToken();

        if (empty($uuid) || empty($token)) {
            return $this->reject($request, 'Missing site credentials.', Response::HTTP_UNAUTHORIZED);
        }

        $site = Site::query()->where('uuid', $uuid)->first();

        if ($site === null) {
            return $this->reject($request, 'Unknown site.', Response::HTTP_UNAUTHORIZED, (string) $uuid);
        }

        if (! $site->is_active) {
            return $this->reject($request, 'Site is disabled.', Response::HTTP_FORBIDDEN, $site->uuid);
        }

        if (! Hash::check($token, $site->ingest_token_hash)) {
            return $this->reject($request, 'Invalid token.', Response::HTTP_UNAUTHORIZED, $site->uuid);
        }

        if (! $this->signatureIsValid($request, $site)) {
            return $this->reject($request, 'Invalid signature.', Response::HTTP_UNAUTHORIZED, $site->uuid);
        }

        $request->attributes->set('site', $site);

        return $next($request);
    }

    private function reject(Request $request, string $message, int $status, ?string $uuid = null): Response
    {
        Log::warning('Ingest rejected: '.$message, [
            'ip' => $request->ip(),
            'site_uuid' => $uuid,
        ]);

        return response()->json(['message' => $message], $status);
    }

    /**
     * Verify the optional HMAC signature over the raw request body.
     */
    private function signatureIsValid(Request $request, Site $site): bool
    {
        $signature = $request->header('X-Shim-Signature');
        $required = (bool) config('cronshim.ingest.require_signature');

        if (empty($signature)) {
            return ! $required;
        }

        $provided = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;
        $expected = hash_hmac('sha256', $request->getContent(), (string) $site->signing_secret);

        return hash_equals($expected, $provided);
    }
}
