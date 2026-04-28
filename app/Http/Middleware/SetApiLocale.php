<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetApiLocale
{
    /**
     * @var array<int, string>
     */
    private array $supportedLocales = ['vi', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);
        app()->setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $headerLocale = $this->normalizeLocale($request->header('X-Locale'));
        if ($headerLocale !== null) {
            return $headerLocale;
        }

        $acceptLanguage = (string) $request->header('Accept-Language', '');
        if ($acceptLanguage !== '') {
            $candidates = array_map('trim', explode(',', $acceptLanguage));
            foreach ($candidates as $candidate) {
                $lang = $this->normalizeLocale(explode(';', $candidate)[0] ?? '');
                if ($lang !== null) {
                    return $lang;
                }
            }
        }

        return 'vi';
    }

    private function normalizeLocale(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($value));
        $normalized = str_replace('_', '-', $normalized);
        $prefix = explode('-', $normalized)[0] ?? '';

        if ($prefix !== '' && in_array($prefix, $this->supportedLocales, true)) {
            return $prefix;
        }

        return null;
    }
}
