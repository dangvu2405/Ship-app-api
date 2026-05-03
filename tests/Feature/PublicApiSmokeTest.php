<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Minimal HTTP smoke for endpoints that do not depend on org tables removed by later migrations.
 */
final class PublicApiSmokeTest extends TestCase
{
    public function test_api_root_and_health_return_success_json(): void
    {
        $this->getJson('/api')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/health')->assertOk()->assertJsonPath('success', true);
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $this->get('/up')->assertOk();
    }
}
