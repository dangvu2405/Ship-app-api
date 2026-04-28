<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0: Fix logic sai của chk_jel_debit_credit.
 *
 * Check cũ: NOT (debit > 0 AND credit > 0) — cho phép cả hai = 0 (dòng rỗng).
 * Check mới: đúng double-entry, bắt buộc exactly một trong hai > 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // Drop check cũ (được thêm bởi schema_hardening_fixes)
        $this->safeStatement("ALTER TABLE `journal_entry_lines` DROP CHECK `chk_jel_debit_credit`");

        // Thêm check mới: exactly one of debit/credit phải > 0
        $this->safeStatement("
            ALTER TABLE `journal_entry_lines`
            ADD CONSTRAINT `chk_jel_debit_credit`
                CHECK (
                    (debit = 0 AND credit > 0) OR (debit > 0 AND credit = 0)
                )
        ");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->safeStatement("ALTER TABLE `journal_entry_lines` DROP CHECK `chk_jel_debit_credit`");

        // Khôi phục check cũ từ schema_hardening_fixes
        $this->safeStatement("
            ALTER TABLE `journal_entry_lines`
            ADD CONSTRAINT `chk_jel_debit_credit`
                CHECK (NOT (debit > 0 AND credit > 0))
        ");
    }

    private function safeStatement(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[fix_jel_check] Skipped: ' . $e->getMessage());
        }
    }
};
