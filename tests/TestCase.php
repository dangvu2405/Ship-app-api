<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Traits\CreatesApplication;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected ?Company $company = null;
    protected ?User $user = null;
    protected ?Customer $customer = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('companies')) {
            $this->setUpTenant();
        }
    }

    protected function setUpTenant(): void
    {
        $this->company  = Company::factory()->create();
        $this->user     = User::factory()->create(['role' => 'admin']);
        $this->user->setAttribute('company_id', $this->company->id);
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($this->user);
        $this->withHeader('X-Tenant-ID', (string) $this->company->id);
    }
}
