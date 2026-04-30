<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Office;
use App\Models\PricingRule;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\TransportRequest;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class QuotationWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);

        return $user;
    }

    public function test_admin_can_run_transport_request_to_quotation_approval_flow(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $office = Office::factory()->create(['company_id' => $company->id]);

        $transportResponse = $this->postJson('/api/transport-requests', [
            'customer_id' => $customer->id,
            'pickup_location' => 'HCM',
            'delivery_location' => 'Da Nang',
            'cargo_weight' => 1200,
            'requested_delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending_pricing',
        ], $this->tenant_headers($company));
        $transportResponse->assertStatus(201);
        $transportRequestId = (int) $transportResponse->json('data.id');

        $pricingRule = PricingRule::create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'name' => 'Default pricing',
            'base_freight' => 1000000,
            'rate_per_km' => 10000,
            'fuel_adjustment' => 100000,
            'max_discount_percent' => 10,
            'minimum_margin_percent' => 5,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ]);

        $quotationResponse = $this->postJson('/api/quotations', [
            'transport_request_id' => $transportRequestId,
            'pricing_rule_id' => $pricingRule->id,
            'distance_km' => 50,
            'cost_amount' => 900000,
        ], $this->tenant_headers($company));
        $quotationResponse->assertStatus(201);
        $quotationId = (int) $quotationResponse->json('data.id');

        $pricingResponse = $this->patchJson('/api/quotations/'.$quotationId.'/pricing', [
            'distance_km' => 50,
            'base_freight' => 1500000,
            'surcharges_total' => 100000,
            'special_fees_total' => 0,
            'discount_total' => 50000,
            'vat_amount' => 150000,
            'cost_amount' => 1200000,
            'items' => [
                [
                    'type' => 'surcharge',
                    'label' => 'BOT fee',
                    'amount' => 100000,
                ],
            ],
        ], $this->tenant_headers($company));

        $pricingResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_approval');

        $approveResponse = $this->postJson('/api/quotations/'.$quotationId.'/approve', [], $this->tenant_headers($company));
        $approveResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('transport_requests', [
            'id' => $transportRequestId,
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_reject_quotation_with_reason(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $transportRequest = TransportRequest::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'code' => 'TRQ-REJECT-01',
            'pickup_location' => 'A',
            'delivery_location' => 'B',
            'cargo_weight' => 100,
            'requested_delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending_approval',
        ]);
        $quotation = Quotation::create([
            'company_id' => $company->id,
            'transport_request_id' => $transportRequest->id,
            'code' => 'QT-REJECT-01',
            'status' => 'pending_approval',
        ]);

        $response = $this->postJson('/api/quotations/'.$quotation->id.'/reject', [
            'reason' => 'Margin below policy',
        ], $this->tenant_headers($company));

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_trip_creation_requires_approved_quotation_if_provided(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);
        $driver = Driver::factory()->create(['office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $transportRequest = TransportRequest::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'code' => 'TRQ-TRIP-01',
            'pickup_location' => 'A',
            'delivery_location' => 'B',
            'cargo_weight' => 100,
            'requested_delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending_approval',
        ]);
        $quotation = Quotation::create([
            'company_id' => $company->id,
            'transport_request_id' => $transportRequest->id,
            'code' => 'QT-TRIP-01',
            'status' => 'pending_approval',
        ]);

        $response = $this->postJson('/api/trips', [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'transport_request_id' => $transportRequest->id,
            'quotation_id' => $quotation->id,
            'code' => 'TRP-QT-001',
            'start_point' => 'HN',
            'end_point' => 'HCM',
            'status' => 'pending',
            'distance_km' => 1200,
            'price' => 15000000,
        ], $this->tenant_headers($company));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quotation_id']);

        $this->assertDatabaseMissing('trips', ['code' => 'TRP-QT-001']);
    }
}

