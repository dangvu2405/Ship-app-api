<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class CurrentSchemaAllTablesSeeder extends Seeder
{
    /** @var array<string, mixed> */
    private array $ids = [];

    public function run(): void
    {
        $this->seedBaseRecords();

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            foreach ($this->tables() as $table) {
                if ($this->shouldSkip($table) || DB::table($table)->count() > 0) {
                    continue;
                }

                try {
                    $payload = $this->payloadFor($table);
                    if ($payload !== []) {
                        DB::table($table)->insert($payload);
                        $this->command?->info("Seeded {$table}");
                    }
                } catch (\Throwable $e) {
                    $this->command?->warn("Skipping {$table}: " . $e->getMessage());
                }
            }
        } finally {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }

    private function seedBaseRecords(): void
    {
        $now = now();

        if (Schema::hasTable('companies')) {
            DB::table('companies')->updateOrInsert(
                ['code' => 'COMP001'],
                [
                    'name' => 'ABC Transport Company',
                    'tax_code' => '1234567890',
                    'address' => '123 Main Street, City',
                    'phone' => '0123456789',
                    'email' => 'info@abctransport.com',
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $this->ids['company_id'] = (int) DB::table('companies')->where('code', 'COMP001')->value('id');
        }

        if (Schema::hasTable('users')) {
            $userPayload = [
                'username' => 'admin',
                'email' => 'admin@abctransport.com',
                'password' => Hash::make('password'),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('users', 'role')) {
                $userPayload['role'] = 'admin';
            }
            if (Schema::hasColumn('users', 'full_name')) {
                $userPayload['full_name'] = 'Admin ABC Transport';
            }

            DB::table('users')->updateOrInsert(['email' => 'admin@abctransport.com'], $userPayload);
            $this->ids['user_id'] = (int) DB::table('users')->where('email', 'admin@abctransport.com')->value('id');
        }

        if (Schema::hasTable('drivers')) {
            DB::table('drivers')->updateOrInsert(
                ['code' => 'DRV001'],
                $this->filterColumns('drivers', [
                    'company_id' => $this->ids['company_id'] ?? 1,
                    'name' => 'Admin Driver',
                    'email' => 'admin.driver@abctransport.com',
                    'phone' => '0912345678',
                    'dob' => '1980-01-15',
                    'gender' => 'male',
                    'address' => '456 Manager Street',
                    'license_no' => 'DL000001',
                    'license_class' => 'B2',
                    'expired_date' => '2028-12-31',
                    'available_status' => 'available',
                    'status' => 'active',
                    'join_date' => '2020-01-01',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
            $this->ids['driver_id'] = (int) DB::table('drivers')->where('code', 'DRV001')->value('id');

            if (Schema::hasColumn('users', 'driver_id') && isset($this->ids['user_id'])) {
                DB::table('users')
                    ->where('id', $this->ids['user_id'])
                    ->update(['driver_id' => $this->ids['driver_id'], 'updated_at' => $now]);
            }
        }

        if (Schema::hasTable('vehicles')) {
            DB::table('vehicles')->updateOrInsert(
                ['plate_number' => '29A-12345'],
                $this->filterColumns('vehicles', [
                    'company_id' => $this->ids['company_id'] ?? 1,
                    'type' => 'truck',
                    'brand' => 'Toyota',
                    'model' => 'Hiace',
                    'year' => 2020,
                    'capacity' => 16,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
            $this->ids['vehicle_id'] = (int) DB::table('vehicles')->where('plate_number', '29A-12345')->value('id');
        }

        if (Schema::hasTable('customers')) {
            DB::table('customers')->updateOrInsert(
                ['email' => 'seed.customer@example.test'],
                $this->filterColumns('customers', [
                    'company_id' => $this->ids['company_id'] ?? 1,
                    'code' => 'CUS001',
                    'type' => 'company',
                    'company_name' => 'Seeded Customer Company',
                    'name' => 'Seeded Customer',
                    'tax_code' => 'SEED-CUST-001',
                    'phone' => '0900000001',
                    'address' => 'Seed address',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
            $this->ids['customer_id'] = (int) DB::table('customers')->where('email', 'seed.customer@example.test')->value('id');
        }
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return Schema::getTableListing();
    }

    private function shouldSkip(string $table): bool
    {
        return in_array($table, [
            'migrations',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'sessions',
            'personal_access_tokens',
            'password_reset_tokens',
            'refresh_tokens',
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $table): array
    {
        $payload = [];

        foreach ($this->columns($table) as $column) {
            $field = $column['name'];
            $autoIncrement = ! empty($column['auto_increment']);
            $nullable = ! empty($column['nullable']);
            $default = $column['default'];

            if ($autoIncrement) {
                continue;
            }

            if (in_array($field, ['created_at', 'updated_at'], true)) {
                $payload[$field] = now();

                continue;
            }

            if ($field === 'deleted_at') {
                $payload[$field] = null;

                continue;
            }

            if ($default !== null) {
                continue;
            }

            if ($nullable && ! $this->isImportantField($field)) {
                continue;
            }

            $payload[$field] = $this->valueFor($table, $field, (string) $column['type'], $nullable);
        }

        if ($payload === []) {
            $firstFillable = collect($this->columns($table))
                ->first(fn (array $column): bool => empty($column['auto_increment']));

            if ($firstFillable !== null) {
                $payload[$firstFillable['name']] = $this->valueFor(
                    $table,
                    $firstFillable['name'],
                    (string) $firstFillable['type'],
                    ! empty($firstFillable['nullable'])
                );
            }
        }

        return $payload;
    }

    /**
     * @return list<object>
     */
    private function columns(string $table): array
    {
        return Schema::getColumns($table);
    }

    private function isImportantField(string $field): bool
    {
        return str_ends_with($field, '_id')
            || in_array($field, ['code', 'name', 'title', 'email', 'phone', 'status', 'type'], true);
    }

    private function valueFor(string $table, string $field, string $type, bool $nullable): mixed
    {
        $fk = $this->foreignKeyValue($field);
        if ($fk !== null) {
            return $fk;
        }

        if (str_starts_with($type, 'enum(')) {
            return $this->enumValue($field, $type);
        }

        if ($field === 'id') {
            return (string) Str::uuid();
        }

        if (str_contains($type, 'json')) {
            return json_encode(['seeded' => true, 'table' => $table], JSON_UNESCAPED_SLASHES);
        }

        if (str_contains($type, 'tinyint(1)')) {
            return true;
        }

        if (preg_match('/int|year/i', $type) === 1) {
            if (str_contains($field, 'days') || str_contains($field, 'count') || str_contains($field, 'quantity')) {
                return 1;
            }

            return $field === 'year' ? (int) now()->year : 1;
        }

        if (preg_match('/decimal|double|float/i', $type) === 1) {
            if (str_contains($field, 'days')) {
                return 1;
            }

            if (str_contains($field, 'hours')) {
                return 2;
            }

            if (str_contains($field, 'rate')) {
                return 10;
            }

            if (str_contains($field, 'km') || str_contains($field, 'distance')) {
                return 10;
            }

            return 100000;
        }

        if (str_contains($type, 'date') || str_contains($type, 'timestamp')) {
            return str_contains($type, 'datetime') || str_contains($type, 'timestamp') ? now() : now()->toDateString();
        }

        if (str_contains($type, 'time')) {
            if (str_contains($field, 'end') || str_ends_with($field, '_to')) {
                return str_contains($type, 'datetime') || str_contains($type, 'timestamp')
                    ? now()->copy()->setTime(17, 0)
                    : '17:00:00';
            }

            return str_contains($type, 'datetime') || str_contains($type, 'timestamp')
                ? now()->copy()->setTime(8, 0)
                : '08:00:00';
        }

        return $this->stringValue($table, $field, $nullable);
    }

    private function foreignKeyValue(string $field): ?int
    {
        $map = [
            'company_id' => 'company_id',
            'customer_id' => 'customer_id',
            'driver_id' => 'driver_id',
            'vehicle_id' => 'vehicle_id',
            'user_id' => 'user_id',
            'created_by' => 'user_id',
            'updated_by' => 'user_id',
            'approved_by' => 'user_id',
            'cancelled_by' => 'user_id',
            'dispatcher_id' => 'user_id',
            'assigned_dispatcher_id' => 'user_id',
            'trip_id' => 'trip_id',
            'invoice_id' => 'invoice_id',
        ];

        if (! isset($map[$field])) {
            return str_ends_with($field, '_id') ? 1 : null;
        }

        return (int) ($this->ids[$map[$field]] ?? 1);
    }

    private function enumValue(string $field, string $type): string
    {
        preg_match_all("/'([^']+)'/", $type, $matches);
        $values = $matches[1] ?? [];

        foreach (['active', 'pending', 'draft', 'company', 'truck', 'manual'] as $preferred) {
            if (in_array($preferred, $values, true)) {
                return $preferred;
            }
        }

        return (string) ($values[0] ?? 'active');
    }

    private function stringValue(string $table, string $field, bool $nullable): ?string
    {
        if ($nullable && ! $this->isImportantField($field)) {
            return null;
        }

        $suffix = Str::lower(Str::random(6));

        return match (true) {
            $field === 'status' => 'active',
            $field === 'type' && $table === 'companies' => 'company',
            $field === 'type' => 'scheduled',
            $field === 'doc_type' => 'other',
            $field === 'price_unit' => 'per_trip',
            $field === 'stop_type' => 'pickup',
            $field === 'payment_method' => 'cash',
            $field === 'payment_status' => 'unpaid',
            $field === 'gender' => 'male',
            $field === 'available_status' => 'available',
            $field === 'interval_type' => 'both',
            $field === 'fuel_type' => 'diesel',
            $field === 'action' => 'approved',
            str_contains($field, 'email') => "seed-{$table}-{$suffix}@example.test",
            str_contains($field, 'phone') => '0900000000',
            str_contains($field, 'code') => 'SEED-'.Str::upper(Str::random(8)),
            str_contains($field, 'name') => 'Seed '.$table,
            str_contains($field, 'title') => 'Seed '.$table,
            str_contains($field, 'token') => hash('sha256', $table.$suffix),
            str_contains($field, 'password') => Hash::make('password'),
            str_contains($field, 'url') || str_contains($field, 'path') => 'https://example.test/seed',
            default => 'seed '.$table.' '.$field,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterColumns(string $table, array $payload): array
    {
        $columns = Schema::getColumnListing($table);

        return array_intersect_key($payload, array_flip($columns));
    }
}
