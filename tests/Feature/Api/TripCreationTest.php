<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TripCreationTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_PRICE = 1500000;
    private const SURCHARGE_AMOUNT = 200000;
    private const TOTAL_REVENUE = self::BASE_PRICE + self::SURCHARGE_AMOUNT;

    public function test_it_can_create_a_trip_with_valid_data(): void
    {
        $tripData = [
            'customer_id'    => $this->customer->id,
            'received_date'  => '2026-05-10',
            'scheduled_date' => '2026-05-11',
            'base_price'     => self::BASE_PRICE,
            'stops'          => [
                [
                    'stop_type' => 'pickup',
                    'sequence'  => 1,
                    'address'   => '123 Nguyen Hue, District 1, HCMC',
                ],
                [
                    'stop_type' => 'delivery',
                    'sequence'  => 2,
                    'address'   => '456 Le Loi, District 3, HCMC',
                ],
            ],
            'surcharges' => [
                [
                    'name'   => 'Waiting fee',
                    'amount' => self::SURCHARGE_AMOUNT,
                ],
            ],
        ];

        $response = $this->postJson('/api/trips', $tripData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'base_price',
                    'surcharge_amount',
                    'total_revenue',
                    'stops',
                    'surcharges',
                ],
            ])
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_revenue', (int) self::TOTAL_REVENUE)
            ->assertJsonCount(2, 'data.stops')
            ->assertJsonCount(1, 'data.surcharges');

        $this->assertDatabaseHas('trips', [
            'customer_id'   => $this->customer->id,
            'company_id'    => $this->company->id,
            'total_revenue' => self::TOTAL_REVENUE,
        ]);

        $this->assertDatabaseHas('trip_stops', [
            'address' => '123 Nguyen Hue, District 1, HCMC',
        ]);

        $this->assertDatabaseHas('trip_surcharges', [
            'name'   => 'Waiting fee',
            'amount' => self::SURCHARGE_AMOUNT,
        ]);
    }

    public function test_store_requires_customer_id(): void
    {
        $response = $this->postJson('/api/trips', [
            'received_date'  => '2026-05-10',
            'scheduled_date' => '2026-05-11',
            'base_price'     => 1000000,
            'stops'          => [
                ['stop_type' => 'pickup', 'sequence' => 1, 'address' => 'HCM'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_requires_at_least_one_stop(): void
    {
        $response = $this->postJson('/api/trips', [
            'customer_id'    => $this->customer->id,
            'received_date'  => '2026-05-10',
            'scheduled_date' => '2026-05-11',
            'base_price'     => 1000000,
            'stops'          => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stops']);
    }

    public function test_store_validates_stop_type_enum(): void
    {
        $response = $this->postJson('/api/trips', [
            'customer_id'    => $this->customer->id,
            'received_date'  => '2026-05-10',
            'scheduled_date' => '2026-05-11',
            'base_price'     => 1000000,
            'stops'          => [
                ['stop_type' => 'invalid', 'sequence' => 1, 'address' => 'Somewhere'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stops.0.stop_type']);
    }

    public function test_store_requires_positive_base_price(): void
    {
        $response = $this->postJson('/api/trips', [
            'customer_id'    => $this->customer->id,
            'received_date'  => '2026-05-10',
            'scheduled_date' => '2026-05-11',
            'base_price'     => -1,
            'stops'          => [
                ['stop_type' => 'pickup', 'sequence' => 1, 'address' => 'HCM'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['base_price']);
    }

    public function test_store_rejects_nonexistent_customer(): void
    {
        $response = $this->postJson('/api/trips', [
            'customer_id'    => 99999,
            'received_date'  => '2026-05-10',
            'scheduled_date' => '2026-05-11',
            'base_price'     => 1000000,
            'stops'          => [
                ['stop_type' => 'pickup', 'sequence' => 1, 'address' => 'HCM'],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_surcharge_totals_are_calculated_correctly(): void
    {
        $response = $this->postJson('/api/trips', [
            'customer_id'    => $this->customer->id,
            'received_date'  => '2026-05-10',
            'scheduled_date' => '2026-05-11',
            'base_price'     => 2000000,
            'stops'          => [
                ['stop_type' => 'pickup', 'sequence' => 1, 'address' => 'A'],
                ['stop_type' => 'delivery', 'sequence' => 2, 'address' => 'B'],
            ],
            'surcharges' => [
                ['name' => 'Toll',    'amount' => 100000],
                ['name' => 'Parking', 'amount' => 50000],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.surcharge_amount', 150000)
            ->assertJsonPath('data.total_revenue', 2150000);
    }

    public function test_index_returns_only_own_company_trips(): void
    {
        $this->postJson('/api/trips', [
            'customer_id'    => $this->customer->id,
            'received_date'  => '2026-05-01',
            'scheduled_date' => '2026-05-02',
            'base_price'     => 500000,
            'stops'          => [
                ['stop_type' => 'pickup', 'sequence' => 1, 'address' => 'HCM'],
                ['stop_type' => 'delivery', 'sequence' => 2, 'address' => 'HN'],
            ],
        ]);

        $response = $this->getJson('/api/trips');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/trips');

        $response->assertUnauthorized();
    }
}
