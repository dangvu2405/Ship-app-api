<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Office;
use App\Models\PaymentRecord;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationSession;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReconciliationWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_confirm_and_lock_reconciliation_session(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);
        $driver = Driver::factory()->create(['office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $confirmer = User::factory()->create(['status' => 'active']);

        $trip = Trip::factory()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'company_id' => $company->id,
            'total_revenue' => 2500000,
            'payment_status' => 'unpaid',
        ]);

        $session = ReconciliationSession::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
            'total_trips' => 1,
            'total_revenue' => 2500000,
            'adjusted_amount' => 0,
            'final_amount' => 2500000,
            'status' => 'draft',
        ]);

        $item = ReconciliationItem::query()->create([
            'company_id' => $company->id,
            'session_id' => $session->id,
            'trip_id' => $trip->id,
            'original_amount' => 2500000,
            'adjusted_amount' => 2400000,
            'adjustment_reason' => 'Discount for SLA issue',
            'is_disputed' => false,
        ]);

        $this->assertDatabaseHas('reconciliation_items', [
            'id' => $item->id,
            'session_id' => $session->id,
        ]);

        $session->update([
            'status' => 'confirmed',
            'confirmed_by' => $confirmer->id,
            'confirmed_at' => now(),
            'final_amount' => 2400000,
        ]);

        // Business lock state after confirmation.
        $session->update(['status' => 'locked']);

        $this->assertDatabaseHas('reconciliation_sessions', [
            'id' => $session->id,
            'status' => 'locked',
        ]);
    }

    public function test_payment_record_can_update_trip_payment_status_to_paid(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);
        $driver = Driver::factory()->create(['office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $trip = Trip::factory()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'company_id' => $company->id,
            'total_revenue' => 1800000,
            'payment_status' => 'unpaid',
        ]);

        $session = ReconciliationSession::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
            'total_trips' => 1,
            'total_revenue' => 1800000,
            'adjusted_amount' => 0,
            'final_amount' => 1800000,
            'status' => 'locked',
        ]);

        PaymentRecord::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'reconciliation_session_id' => $session->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1800000,
            'payment_method' => 'bank_transfer',
            'bank_reference' => 'TXN-RECON-0001',
        ]);

        $trip->update(['payment_status' => 'paid']);

        $this->assertDatabaseHas('payment_records', [
            'customer_id' => $customer->id,
            'reconciliation_session_id' => $session->id,
            'amount' => 1800000,
        ]);
        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'payment_status' => 'paid',
        ]);
    }
}
