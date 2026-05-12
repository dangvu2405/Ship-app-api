<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        // Ensure user has the same company_id for multi-tenant checks
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_create_a_trip_with_valid_data(): void
    {
        $tripData = [
            'customer_id' => $this->customer->id,
            'received_date' => '2026-05-10',
            'scheduled_date' => '2026-05-11',
            'base_price' => 1500000,
            'stops' => [
                [
                    'stop_type' => 'pickup',
                    'sequence' => 1,
                    'address' => '123 Nguyen Hue, District 1, HCMC',
                ],
                [
                    'stop_type' => 'delivery',
                    'sequence' => 2,
                    'address' => '456 Le Loi, District 3, HCMC',
                ],
            ],
            'surcharges' => [
                [
                    'name' => 'Waiting fee',
                    'amount' => 200000,
                ]
            ]
        ];

        $response = $this->postJson('/api/trips', $tripData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'revenue' => [
                        'base_price',
                        'surcharge_amount',
                        'total_revenue'
                    ],
                    'stops',
                    'surcharges',
                ]
            ])
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.revenue.total_revenue', 1700000.0)
            ->assertJsonCount(2, 'data.stops')
            ->assertJsonCount(1, 'data.surcharges');

        $this->assertDatabaseHas('trips', [
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total_revenue' => 1700000,
        ]);

        $this->assertDatabaseHas('trip_stops', [
            'address' => '123 Nguyen Hue, District 1, HCMC',
        ]);

        $this->assertDatabaseHas('trip_surcharges', [
            'name' => 'Waiting fee',
            'amount' => 200000,
        ]);
    }
}
