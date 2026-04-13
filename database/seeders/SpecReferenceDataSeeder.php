<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dữ liệu tham chiếu cố định bám MUST_HAVE / DATABASE_DATA_DICTIONARY (dev & QA).
 * Idempotent: dùng updateOrInsert / insertOrIgnore nơi thích hợp.
 */
class SpecReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $userId = User::query()->orderBy('id')->value('id');

        $this->seedTaxBrackets($now);
        $this->seedInsuranceRates($now);
        $this->seedChartOfAccounts($now);
        $this->seedBalancedJournalEntry($now, $userId);

        $trip = DB::table('trips')->orderBy('id')->first();
        if ($trip && Schema::hasTable('trip_status_histories')) {
            $tripNote = 'Spec seed: trip history';
            if (! DB::table('trip_status_histories')->where('trip_id', $trip->id)->where('note', $tripNote)->exists()) {
                DB::table('trip_status_histories')->insert([
                    'trip_id' => $trip->id,
                    'from_status' => null,
                    'to_status' => (string) $trip->status,
                    'changed_by' => $userId,
                    'changed_at' => $now,
                    'note' => $tripNote,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $invoice = DB::table('invoices')->orderBy('id')->first();
        if ($invoice && Schema::hasTable('invoice_status_histories')) {
            $invNote = 'Spec seed: invoice history';
            if (! DB::table('invoice_status_histories')->where('invoice_id', $invoice->id)->where('note', $invNote)->exists()) {
                DB::table('invoice_status_histories')->insert([
                    'invoice_id' => $invoice->id,
                    'from_status' => null,
                    'to_status' => (string) $invoice->status,
                    'changed_by' => $userId,
                    'changed_at' => $now,
                    'note' => $invNote,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->command?->info('Spec reference data seeded (MUST HAVE masters + demo rows).');
    }

    private function seedTaxBrackets(\DateTimeInterface $now): void
    {
        if (! Schema::hasTable('tax_brackets')) {
            return;
        }

        $effective = '2026-01-01';
        $brackets = [
            [1, 0, 5_000_000, 5, 0],
            [2, 5_000_000, 10_000_000, 10, 250_000],
            [3, 10_000_000, 18_000_000, 15, 750_000],
            [4, 18_000_000, 32_000_000, 20, 1_650_000],
            [5, 32_000_000, 52_000_000, 25, 3_250_000],
            [6, 52_000_000, 80_000_000, 30, 5_850_000],
            [7, 80_000_000, null, 35, 9_850_000],
        ];

        foreach ($brackets as [$level, $from, $to, $rate, $quick]) {
            DB::table('tax_brackets')->updateOrInsert(
                ['effective_from' => $effective, 'level' => $level],
                [
                    'effective_to' => null,
                    'income_from' => $from,
                    'income_to' => $to,
                    'tax_rate' => $rate,
                    'quick_deduction' => $quick,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedInsuranceRates(\DateTimeInterface $now): void
    {
        if (! Schema::hasTable('insurance_rates')) {
            return;
        }

        $insRow = DB::table('insurance_rates')
            ->where('effective_from', '2026-01-01')
            ->whereNull('effective_to')
            ->orderBy('id')
            ->first();

        $insPayload = [
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'social_employee_rate' => 8,
            'social_company_rate' => 17,
            'health_employee_rate' => 1.5,
            'health_company_rate' => 3,
            'unemployment_employee_rate' => 1,
            'unemployment_company_rate' => 1,
            'salary_cap_amount' => 36_000_000,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($insRow) {
            DB::table('insurance_rates')->where('id', $insRow->id)->update($insPayload);
        } else {
            DB::table('insurance_rates')->insert($insPayload);
        }
    }

    private function seedChartOfAccounts(\DateTimeInterface $now): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        $roots = [
            ['1000', 'Tài sản', 'asset'],
            ['2000', 'Nợ phải trả', 'liability'],
            ['3000', 'Vốn chủ sở hữu', 'equity'],
            ['4000', 'Doanh thu', 'revenue'],
            ['5000', 'Chi phí', 'expense'],
        ];

        foreach ($roots as [$code, $name, $type]) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'parent_id' => null,
                    'is_postable' => false,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        $assetId = DB::table('chart_of_accounts')->where('code', '1000')->value('id');
        $liabId = DB::table('chart_of_accounts')->where('code', '2000')->value('id');
        $expId = DB::table('chart_of_accounts')->where('code', '5000')->value('id');

        if ($assetId) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['code' => '1110'],
                ['name' => 'Tiền mặt', 'type' => 'asset', 'parent_id' => $assetId, 'is_postable' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null]
            );
        }

        if ($liabId) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['code' => '3341'],
                ['name' => 'Phải trả người lao động', 'type' => 'liability', 'parent_id' => $liabId, 'is_postable' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null]
            );
        }

        if ($expId) {
            DB::table('chart_of_accounts')->updateOrInsert(
                ['code' => '5210'],
                ['name' => 'Chi phí lương', 'type' => 'expense', 'parent_id' => $expId, 'is_postable' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null]
            );
        }
    }

    private function seedBalancedJournalEntry(\DateTimeInterface $now, ?int $userId): void
    {
        if (! Schema::hasTable('journal_entries') || ! Schema::hasTable('journal_entry_lines')) {
            return;
        }

        $expenseId = DB::table('chart_of_accounts')->where('code', '5210')->value('id');
        $liabId = DB::table('chart_of_accounts')->where('code', '3341')->value('id');
        if (! $expenseId || ! $liabId) {
            return;
        }

        $entryNo = 'JE-SPEC-2026-001';
        DB::table('journal_entries')->updateOrInsert(
            ['entry_no' => $entryNo],
            [
                'entry_date' => $now->toDateString(),
                'source_type' => 'manual',
                'source_id' => null,
                'status' => 'draft',
                'description' => 'Spec: bút toán cân đối demo',
                'posted_by' => null,
                'posted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $jeId = DB::table('journal_entries')->where('entry_no', $entryNo)->value('id');
        if (! $jeId) {
            return;
        }

        DB::table('journal_entry_lines')->where('journal_entry_id', $jeId)->delete();

        $amount = 1_000_000;
        DB::table('journal_entry_lines')->insert([
            [
                'journal_entry_id' => $jeId,
                'account_id' => $expenseId,
                'debit' => $amount,
                'credit' => 0,
                'line_description' => 'Chi phí lương kỳ spec',
                'line_no' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'journal_entry_id' => $jeId,
                'account_id' => $liabId,
                'debit' => 0,
                'credit' => $amount,
                'line_description' => 'Phải trả lương',
                'line_no' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
