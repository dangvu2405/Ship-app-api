<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class VehicleDocumentsApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(array $override = []): User
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(array_merge(['status' => 'active', 'role' => 'admin'], $override));
        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        return $user;
    }

    public function test_t08_01_upload_vehicle_document(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/v1/vehicle-documents', [
            'vehicle_id' => $vehicle->id,
            'doc_type' => 'registration',
            'doc_name' => 'Dang ky xe',
            'file_url' => 'https://cdn.example.com/docs/registration.pdf',
            'expiry_date' => now()->addMonths(6)->toDateString(),
        ], $this->tenant_headers($company));

        $response->assertStatus(201)->assertJsonPath('data.doc_type', 'registration');
    }

    public function test_t08_02_expiry_alert_creates_notifications(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $admin = $this->adminUser(['role' => 'admin']);
        User::factory()->create(['status' => 'active', 'role' => 'dispatcher']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/vehicle-documents', [
            'vehicle_id' => $vehicle->id,
            'doc_type' => 'inspection',
            'doc_name' => 'Dang kiem',
            'file_url' => 'https://cdn.example.com/docs/inspection.pdf',
            'expiry_date' => now()->addDays(5)->toDateString(),
            'alert_before_days' => 7,
        ], $this->tenant_headers($company))->assertStatus(201);

        $count = DB::table('notifications')
            ->where('type', 'vehicle_document_expiry')
            ->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_t08_03_vehicle_can_have_multiple_document_types(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        Sanctum::actingAs($this->adminUser());

        foreach (['registration', 'inspection', 'liability_insurance', 'photo', 'other'] as $docType) {
            VehicleDocument::query()->create([
                'company_id' => $company->id,
                'vehicle_id' => $vehicle->id,
                'doc_type' => $docType,
                'doc_name' => 'Doc '.$docType,
                'file_url' => 'https://cdn.example.com/file-'.$docType.'.pdf',
            ]);
        }

        $response = $this->getJson('/api/v1/vehicle-documents?vehicle_id='.$vehicle->id, $this->tenant_headers($company));
        $response->assertStatus(200)->assertJsonCount(5, 'data.data');
    }

    public function test_t08_04_reject_invalid_file_extension(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/v1/vehicle-documents', [
            'vehicle_id' => $vehicle->id,
            'doc_type' => 'registration',
            'doc_name' => 'Sai file',
            'file_url' => 'https://cdn.example.com/malware.exe',
        ], $this->tenant_headers($company));

        $response->assertStatus(422);
    }
}
