<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Ensures every non-closure route points at an existing controller class and public method.
 * This stays valid when migrations drop tables (HTTP dispatch would 500 while wiring is still correct).
 */
final class RouteRegistrationTest extends TestCase
{
    public function test_all_non_closure_routes_resolve_to_callable_actions(): void
    {
        $failures = [];

        foreach (RouteFacade::getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }

            $pair = $this->controllerMethodPair($route);
            if ($pair === null) {
                continue;
            }

            [$class, $method] = $pair;

            if (! class_exists($class)) {
                $failures[] = $this->formatFailure($route, "class not found: {$class}");

                continue;
            }

            if (! method_exists($class, $method)) {
                $failures[] = $this->formatFailure($route, "method not found: {$class}::{$method}");

                continue;
            }

            $ref = new ReflectionMethod($class, $method);
            if (! $ref->isPublic()) {
                $failures[] = $this->formatFailure($route, "method not public: {$class}::{$method}");
            }
        }

        $this->assertSame(
            [],
            $failures,
            "Route wiring failures:\n".implode("\n", $failures)
        );
    }

    /**
     * @return array{0: class-string, 1: string}|null
     */
    private function controllerMethodPair(Route $route): ?array
    {
        $uses = $route->getAction('uses');

        if ($uses instanceof \Closure) {
            return null;
        }

        if (is_array($uses) && isset($uses[0], $uses[1]) && is_string($uses[0]) && is_string($uses[1])) {
            return [$uses[0], $uses[1]];
        }

        if (is_string($uses)) {
            return Str::parseCallback($uses, '__invoke');
        }

        return null;
    }

    private function formatFailure(Route $route, string $reason): string
    {
        $methods = implode('|', $route->methods());
        $uri = $route->uri();

        return "{$methods} {$uri} → {$reason}";
    }
}
