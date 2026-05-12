<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripCreationTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_PRICE = 1500000;
    private const SURCHARGE_AMOUNT = 200000;
    private const TOTAL_REVENUE = self::BASE_PRICE + self::SURCHARGE_AMOUNT;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_create_a_trip_with_valid_data(): void
    {
        $tripData = [
            'customer_id' => $this->customer->id,
            'received_date' => '2026-05-10',
            'scheduled_date' => '2026-05-11',
            'base_price' => self::BASE_PRICE,
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
                    'amount' => self::SURCHARGE_AMOUNT,
                ]
            ]
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
                ]
            ])
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.total_revenue', self::TOTAL_REVENUE)
            ->assertJsonCount(2, 'data.stops')
            ->assertJsonCount(1, 'data.surcharges');

        $this->assertDatabaseHas('trips', [
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total_revenue' => self::TOTAL_REVENUE,
        ]);

        $this->assertDatabaseHas('trip_stops', [
            'address' => '123 Nguyen Hue, District 1, HCMC',
        ]);

        $this->assertDatabaseHas('trip_surcharges', [
            'name' => 'Waiting fee',
            'amount' => self::SURCHARGE_AMOUNT,
        ]);
    }
}
