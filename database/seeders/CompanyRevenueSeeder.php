<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompanyRevenueSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('customers') || ! Schema::hasTable('invoices')) {
            $this->command?->warn('CompanyRevenueSeeder skipped: required tables are missing.');

            return;
        }

        $now = now();
        $companyIds = DB::table('companies')->orderBy('id')->pluck('id')->take(2)->all();

        if ($companyIds === []) {
            $this->command?->warn('CompanyRevenueSeeder skipped: no companies found.');

            return;
        }

        // Seed revenue for 4 recent months (including current month)
        for ($m = 3; $m >= 0; $m--) {
            $monthDate = now()->startOfMonth()->subMonths($m);
            $monthKey = $monthDate->format('Ym');

            foreach ($companyIds as $companyId) {
                $customer = DB::table('customers')->orderBy('id')->first();
                if (! $customer) {
                    $customerId = DB::table('customers')->insertGetId([
                        'type' => 'company',
                        'name' => "Revenue Customer C{$companyId}",
                        'tax_code' => "REV-C{$companyId}",
                        'phone' => '0900000000',
                        'email' => "revenue-c{$companyId}@demo.local",
                        'address' => 'Seeded customer address',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $customerId = (int) $customer->id;
                }

                // 2 paid invoices per company per month for realistic monthly revenue
                $invoiceRows = [
                    ['seq' => 1, 'subtotal' => 120_000_000 + ($companyId * 5_000_000), 'vat_rate' => 8.0],
                    ['seq' => 2, 'subtotal' => 95_000_000 + ($companyId * 4_000_000), 'vat_rate' => 8.0],
                ];

                foreach ($invoiceRows as $row) {
                    $code = sprintf('INV-REV-C%02d-%s-%02d', $companyId, $monthKey, $row['seq']);
                    $vatAmount = round($row['subtotal'] * ($row['vat_rate'] / 100), 2);
                    $totalAmount = round($row['subtotal'] + $vatAmount, 2);
                    $issuedAt = $monthDate->copy()->addDays(8 + $row['seq'])->setTime(9, 0);
                    $paidAt = $monthDate->copy()->addDays(20 + $row['seq'])->setTime(11, 0);

                    DB::table('invoices')->updateOrInsert(
                        ['code' => $code],
                        [
                            'trip_id' => null,
                            'customer_id' => $customerId,
                            'subtotal' => $row['subtotal'],
                            'vat_rate' => $row['vat_rate'],
                            'vat_amount' => $vatAmount,
                            'total_amount' => $totalAmount,
                            'status' => 'paid',
                            'issued_at' => $issuedAt,
                            'paid_at' => $paidAt,
                            'created_at' => $now,
                            'updated_at' => $now,
                            'deleted_at' => null,
                        ]
                    );
                }
            }
        }

        $this->command?->info('CompanyRevenueSeeder completed: seeded paid revenue invoices for 4 months.');
    }
}

