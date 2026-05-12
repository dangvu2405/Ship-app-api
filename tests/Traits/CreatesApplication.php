<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Laravel\Sanctum\Sanctum;

trait CreatesApplication
{
    protected Company $company;
    protected User $user;
    protected Customer $customer;

    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($this->user);

        return $app;
    }
}
