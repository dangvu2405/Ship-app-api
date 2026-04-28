<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class HandleApiErrors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Never reshape preflight responses; CORS headers must stay untouched.
        if ($request->isMethod('OPTIONS')) {
            return $response;
        }
        
        // If response is an error, format it
        if ($response->getStatusCode() >= 400) {
            $content = json_decode($response->getContent(), true);
            
            if (!isset($content['success'])) {
                $formatted = response()->json([
                    'success' => false,
                    'message' => $content['message'] ?? 'An error occurred',
                    'errors' => $content['errors'] ?? null,
                ], $response->getStatusCode());

                // Preserve headers like CORS from upstream middlewares.
                foreach ($response->headers->allPreserveCaseWithoutCookies() as $name => $values) {
                    foreach ($values as $value) {
                        $formatted->headers->set($name, $value, false);
                    }
                }

                return $formatted;
            }
        }
        
        return $response;
    }
}
