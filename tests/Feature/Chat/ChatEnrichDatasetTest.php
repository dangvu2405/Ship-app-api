<?php

declare(strict_types=1);

/**
 * Chat enrich (ChatDataService) theo company + driver.
 *
 * Stack test mặc định (phpunit.xml / CI):
 * - DB: SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).
 * - Toàn bộ case dưới đây chạy được trên SQLite; không phụ thuộc MySQL FULLTEXT.
 * - Trong Pest, dùng `$this->mock(GeminiService::class, …)` (không dùng helper `mock()` toàn cục)
 *   để binding container khớp với TestCase.
 *
 * Khi nào nên chạy MySQL?
 * - Regression cho migration chỉ hỗ trợ MySQL (FULLTEXT, ALTER … MODIFY …).
 * - Soát song song SQLite + MySQL (job CI matrix: thêm service MySQL, `.env.testing.mysql`,
 *   `php artisan test --configuration=phpunit.mysql.xml` nếu team thêm file cấu hình).
 *
 * Pest + dataset: mỗi dòng dataset = một intent tài xế + key `data.*` kỳ vọng trong context đã lưu.
 */

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Config::set('services.rag.agent_enabled', false);
});

afterEach(function (): void {
    Config::set('services.rag.agent_enabled', false);
});

/**
 * @return object{company: Company, office: Office, driver: Driver, user: User}
 */
function seed_driver_user_in_company(): object
{
    $company = Company::factory()->create();
    $office = Office::factory()->create([
        'company_id' => $company->id,
        'code' => 'OFF010',
    ]);
    $driver = Driver::factory()->create([
        'office_id' => $office->id,
        'company_id' => $company->id,
        'status' => 'active',
        'name' => 'Tài xế Test',
    ]);
    $user = User::factory()->create([
        'driver_id' => $driver->id,
        'status' => 'active',
    ]);

    return (object) [
        'company' => $company,
        'office' => $office,
        'driver' => $driver,
        'user' => $user,
    ];
}

dataset('driver_enrich_intents', function () {
    yield 'payroll_no_lines' => [
        'Lương tháng này của tôi được tính thế nào, giải thích chi tiết?',
        'ghi_chú',
        'Chưa có bảng lương nào.',
    ];

    yield 'fuel_empty' => [
        'Chi phí nhiên liệu và xăng dầu gần đây của tôi ra sao?',
        'chi_phí_nhiên_liệu',
        'Không có dữ liệu.',
    ];

    // Tránh từ "kiểm tra" để không kích hoạt structured JSON (cần mock khác schema).
    yield 'compliance_license' => [
        'Chứng chỉ và bằng lái của tôi còn hiệu lực không, cho tôi biết giúp?',
        'tuân_thủ_chứng_chỉ',
        'Tài xế Test',
    ];

    yield 'violation_empty' => [
        'Danh sách vi phạm và phạt nguội của tôi hiện tại thế nào?',
        'vi_phạm',
        'Không có vi phạm',
    ];
});

test('driver tracking enrich persists trip snapshot on chat message', function (): void {
    $fx = seed_driver_user_in_company();
    $customer = Customer::factory()->create(['company_id' => $fx->company->id]);
    $vehicle = Vehicle::factory()->create(['office_id' => $fx->office->id]);
    $vehicle->refresh();

    Trip::factory()->create([
        'driver_id' => $fx->driver->id,
        'vehicle_id' => $vehicle->id,
        'customer_id' => $customer->id,
        'code' => 'TRIP-ENRICH-1',
        'start_point' => 'Kho A',
        'end_point' => 'Kho B',
        'status' => 'completed',
        'distance_km' => 12.5,
        'start_time' => Carbon::now()->subDay(),
        'end_time' => Carbon::now()->subDay()->addHour(),
    ]);

    Sanctum::actingAs($fx->user);
    $this->mock(GeminiService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('generateContent')
            ->once()
            ->andReturn([
                'raw' => [],
                'text' => 'OK',
            ]);
    });

    $response = $this->postJson('/api/v1/chat/messages', [
        'message' => 'Cho tôi xem các chuyến vận chuyển gần đây của tôi chi tiết?',
    ]);

    $response->assertOk()->assertJsonPath('data.cached', false);
    $trips = $response->json('data.message.context.data.chuyến_gần_nhất');
    expect($trips)->toBeArray()
        ->and($trips[0]['mã'] ?? null)->toBe('TRIP-ENRICH-1')
        ->and($response->json('data.message.context.data.tài_xế'))->toBe('Tài xế Test');
});

test('driver enrich dataset covers payroll fuel compliance violation branches', function (
    string $message,
    string $expected_key,
    string $expected_fragment,
): void {
    $fx = seed_driver_user_in_company();
    Sanctum::actingAs($fx->user);
    $this->mock(GeminiService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('generateContent')
            ->once()
            ->andReturn([
                'raw' => [],
                'text' => 'Phản hồi AI test.',
            ]);
    });

    $response = $this->postJson('/api/v1/chat/messages', [
        'message' => $message,
    ]);

    $response->assertOk()->assertJsonPath('data.cached', false);
    $block = $response->json('data.message.context.data.'.$expected_key);
    expect($block)->not->toBeNull();
    if (is_string($block)) {
        expect($block)->toContain($expected_fragment);
    } else {
        expect(json_encode($block, JSON_UNESCAPED_UNICODE))->toContain($expected_fragment);
    }
})->with('driver_enrich_intents');

test('admin with X-Company-Id enriches driver list for office code in message', function (): void {
    $company = Company::factory()->create();
    $office = Office::factory()->create([
        'company_id' => $company->id,
        'code' => 'OFF220',
    ]);
    Driver::factory()->create([
        'company_id' => $company->id,
        'office_id' => $office->id,
        'code' => 'DRV-ENR-1',
        'name' => 'Driver Enrich One',
        'status' => 'active',
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create(['status' => 'active', 'driver_id' => null]);
    $admin->roles()->syncWithoutDetaching([$adminRole->id]);

    Sanctum::actingAs($admin);
    $this->mock(GeminiService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('generateContent')
            ->once()
            ->andReturn([
                'raw' => [],
                'text' => 'OK',
            ]);
    });

    $response = $this->postJson(
        '/api/v1/chat/messages',
        [
            'message' => 'Danh sách tài xế thuộc văn phòng OFF220 đầy đủ giúp tôi?',
        ],
        ['X-Company-Id' => (string) $company->id],
    );

    $response->assertOk()->assertJsonPath('data.cached', false);
    $list = $response->json('data.message.context.data.tài_xế_theo_văn_phòng');
    expect($list)->toBeArray()
        ->and($list[0]['mã_tài_xế'] ?? null)->toBe('DRV-ENR-1');
});

test('tenant company id is resolved from driver office when posting chat', function (): void {
    $fx = seed_driver_user_in_company();
    Sanctum::actingAs($fx->user);
    $this->mock(GeminiService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('generateContent')
            ->once()
            ->andReturn([
                'raw' => [],
                'text' => 'OK',
            ]);
    });

    // Tránh từ khoá TRACKING ("chuyến", …) để rơi vào GENERAL → enrichDriverGeneral.
    $response = $this->postJson('/api/v1/chat/messages', [
        'message' => 'Bạn tóm tắt nhanh tình hình hồ sơ và trạng thái hiện tại của tôi trong hệ thống được không?',
    ]);

    $response->assertOk()->assertJsonPath('data.cached', false);
    expect($response->json('data.message.context.data.tài_xế'))->toBe('Tài xế Test')
        ->and($response->json('data.message.context.data.chuyến_tháng_này'))->toBeInt();
});
