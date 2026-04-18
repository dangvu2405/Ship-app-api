<?php

namespace Tests;

use App\Models\Company;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Headers so EnsureTenantContext resolves the same company as tenant-scoped data.
     *
     * @return array<string, string>
     */
    protected function tenant_headers(Company $company): array
    {
        return ['X-Tenant-ID' => (string) $company->id];
    }
}
