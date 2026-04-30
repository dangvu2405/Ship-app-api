<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Location;
use App\Models\Role;
use App\Models\RouteTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class LocationsRouteTemplatesApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        return $user;
    }

    public function test_t04_01_create_location_with_gps(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/v1/locations', [
            'name' => 'Kho Ha Noi',
            'address' => '123 Pho Hue, Ha Noi',
            'lat' => 21.027764,
            'lng' => 105.83416,
        ], $this->tenant_headers($company));

        $response->assertStatus(201)->assertJsonPath('data.name', 'Kho Ha Noi');
    }

    public function test_t04_02_invalid_lat_lng_returns_422(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/v1/locations', [
            'name' => 'Kho Sai',
            'address' => 'Quan 1, HCM',
            'lat' => 999,
            'lng' => 220,
        ], $this->tenant_headers($company));

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_t04_03_create_route_template_auto_fills_distance(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        $origin = Location::query()->create([
            'company_id' => $company->id,
            'name' => 'Kho A',
            'address' => 'Ha Noi',
            'lat' => 21.027764,
            'lng' => 105.83416,
        ]);
        $destination = Location::query()->create([
            'company_id' => $company->id,
            'name' => 'Kho B',
            'address' => 'Hai Phong',
            'lat' => 20.844911,
            'lng' => 106.688087,
        ]);

        $response = $this->postJson('/api/v1/route-templates', [
            'name' => 'HN-HP',
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
        ], $this->tenant_headers($company));

        $response->assertStatus(201);
        $this->assertGreaterThan(0, (float) $response->json('data.distance_km'));
    }

    public function test_t04_04_location_index_supports_keyword_search(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        Location::query()->create([
            'company_id' => $company->id,
            'name' => 'Kho Da Nang',
            'address' => 'Hai Chau',
        ]);
        Location::query()->create([
            'company_id' => $company->id,
            'name' => 'Kho Can Tho',
            'address' => 'Ninh Kieu',
        ]);

        $response = $this->getJson('/api/v1/locations?search=Da Nang', $this->tenant_headers($company));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Kho Da Nang');
    }

    public function test_t04_05_delete_location_used_in_route_template_sets_null(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        $origin = Location::query()->create([
            'company_id' => $company->id,
            'name' => 'Kho A',
            'address' => 'Ha Noi',
            'lat' => 21.027764,
            'lng' => 105.83416,
        ]);
        $destination = Location::query()->create([
            'company_id' => $company->id,
            'name' => 'Kho B',
            'address' => 'Hai Phong',
            'lat' => 20.844911,
            'lng' => 106.688087,
        ]);

        $template = RouteTemplate::query()->create([
            'company_id' => $company->id,
            'name' => 'HN-HP',
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
        ]);

        $this->deleteJson('/api/v1/locations/'.$origin->id, [], $this->tenant_headers($company))
            ->assertStatus(200);

        $template->refresh();
        $this->assertNull($template->origin_location_id);
        $this->assertSame($destination->id, $template->destination_location_id);
    }
}
