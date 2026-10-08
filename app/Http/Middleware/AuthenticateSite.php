<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            return response()->json(['message' => 'Payload too large.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $uuid = $request->header('X-Shim-Site');
        $token = $request->bearerToken();

        if (empty($uuid) || empty($token)) {
            return response()->json(['message' => 'Missing site credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        $site = Site::query()->where('uuid', $uuid)->first();

        if ($site === null) {
            return response()->json(['message' => 'Unknown site.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! $site->is_active) {
            return response()->json(['message' => 'Site is disabled.'], Response::HTTP_FORBIDDEN);
        }

        if (! Hash::check($token, $site->ingest_token_hash)) {
            return response()->json(['message' => 'Invalid token.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->signatureIsValid($request, $site)) {
            return response()->json(['message' => 'Invalid signature.'], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set('site', $site);

        return $next($request);
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
