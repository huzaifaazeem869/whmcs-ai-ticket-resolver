<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\WhmcsLicense;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWhmcsLicense
{
    /**
     * Handle an incoming request and enforce active SaaS license validation.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Extract license key from header, bearer token, or request payload
        $licenseKey = $request->header('X-AI-License-Key')
            ?? $request->header('X-License-Key')
            ?? $request->bearerToken()
            ?? $request->input('license_key');

        if (empty($licenseKey)) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'LICENSE_KEY_REQUIRED',
                'message' => 'Active license key required. Provide "X-AI-License-Key" header or "license_key" payload.',
                'portal'  => 'https://huzaifaazeem.com/',
            ], 401);
        }

        // 2. Lookup license in database
        /** @var WhmcsLicense|null $license */
        $license = WhmcsLicense::where('license_key', trim((string) $licenseKey))->first();

        if (!$license) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'INVALID_LICENSE',
                'message' => 'The provided license key was not found or is invalid.',
                'portal'  => 'https://huzaifaazeem.com/',
            ], 403);
        }

        // 3. Verify status and expiration
        if (!$license->isActive()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'LICENSE_INACTIVE',
                'message' => 'Your WHMCS AI Resolver license is ' . $license->status . ' or expired. Please renew at huzaifaazeem.com.',
                'portal'  => 'https://huzaifaazeem.com/',
            ], 403);
        }

        // 4. Verify monthly quota
        if (!$license->hasQuota()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'QUOTA_EXCEEDED',
                'message' => 'Monthly ticket analysis quota exceeded (' . $license->usage_count . '/' . $license->monthly_limit . '). Upgrade your plan at huzaifaazeem.com.',
                'quota'   => [
                    'limit' => $license->monthly_limit,
                    'used'  => $license->usage_count,
                ],
                'portal'  => 'https://huzaifaazeem.com/',
            ], 429);
        }

        // 5. Attach license object to request attributes
        $request->attributes->set('license', $license);

        /** @var Response $response */
        $response = $next($request);

        // 6. Record usage only if the request was successfully processed (HTTP 200/201)
        if ($response->isSuccessful()) {
            $license->recordUsage();
        }

        // 7. Inject remaining quota metadata in response headers
        $response->headers->set('X-AI-Remaining-Quota', (string) $license->remainingQuota());
        $response->headers->set('X-AI-License-Tier', $license->tier);

        return $response;
    }
}
