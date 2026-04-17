<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema Hardening — tổng hợp tất cả các fix từ review schema.
 *
 * Thứ tự ưu tiên:
 *   🔴 CAO   : chart_of_accounts + journal_entries thêm company_id; invoices CHECK tính nhất quán
 *   🟠 TRUNG : users.driver_id UNIQUE; positions.code UNIQUE per company; payrolls thêm locked_by;
 *              vehicle_assignments CHECK dates; journal_entry_lines CHECK double-entry
 *   🟡 THẤP  : varchar → ENUM; leave_requests / overtime_requests / trip_bonus_rules CHECK; leave_balances CHECK;
 *              roles.name scoped per company; leave_types thêm company_id; password_reset_tokens hash note
 *
 * Không thể cover:
 *   - vehicle_assignments overlap (cần EXCLUDE PostgreSQL/trigger MySQL → handled by application layer)
 *   - password_reset_tokens hash: token đang lưu plain, cần application-layer migration riêng
 *   - offices.manager_id → users (breaking, cần data migration riêng)
 *   - drivers.expired_date đổi tên (breaking rename, đưa vào separate migration nếu cần)
 */
return new class extends Migration
{
    // ─────────────────────────────────────────────────────────────────────────
    // UP
    // ─────────────────────────────────────────────────────────────────────────
    public function up(): void
    {
        $isMysql = $this->isMysql();

        // ====================================================================
        // 🔴 CAO-1: chart_of_accounts — thêm company_id + đổi type → ENUM
        // ====================================================================
        Schema::table('chart_of_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('chart_of_accounts', 'company_id')) {
                // nullable để không break dữ liệu cũ (shared chart)
                $table->unsignedBigInteger('company_id')->nullable()->after('id')
                    ->comment('NULL = chart dùng chung toàn hệ thống (system default)');
                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            }
        });

        // Đổi code UNIQUE global → UNIQUE per (company_id, code)
        Schema::table('chart_of_accounts', function (Blueprint $table) use ($isMysql): void {
            // Xoá UNIQUE cũ trên code
            try {
                $table->dropUnique(['code']);
            } catch (\Throwable) {
                // Có thể đã được drop
            }
            // Thêm composite unique
            if (! $this->indexExists('chart_of_accounts', 'coa_company_code_unique')) {
                $table->unique(['company_id', 'code'], 'coa_company_code_unique');
            }
            // Index dùng cho filter
            if (! $this->indexExists('chart_of_accounts', 'coa_company_id_idx')) {
                $table->index('company_id', 'coa_company_id_idx');
            }
        });

        // Đổi type varchar(30) → ENUM (MySQL chỉ)
        if ($isMysql) {
            $this->safeDbStatement("
                ALTER TABLE chart_of_accounts
                MODIFY COLUMN `type` ENUM('asset','liability','equity','revenue','expense') NOT NULL
            ");

            $this->safeDbStatement("
                ALTER TABLE chart_of_accounts
                MODIFY COLUMN `status` ENUM('active','inactive') NOT NULL DEFAULT 'active'
            ");
        }

        // ====================================================================
        // 🔴 CAO-2: journal_entries — thêm company_id + đổi status → ENUM
        // ====================================================================
        Schema::table('journal_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('journal_entries', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id')
                    ->comment('NULL = entry cũ trước khi thêm company_id; backfill từ source');
                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
                $table->index('company_id', 'je_company_id_idx');
            }
        });

        // entry_no cũ UNIQUE global — vẫn giữ (entry_no nên unique toàn hệ thống để không nhầm reference)
        // Tuy nhiên nếu muốn unique per company: bỏ comment dưới
        // $table->dropUnique(['entry_no']);
        // $table->unique(['company_id', 'entry_no'], 'je_company_entry_no_unique');

        if ($isMysql) {
            $this->safeDbStatement("
                ALTER TABLE journal_entries
                MODIFY COLUMN `status` ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft'
            ");
        }

        // ====================================================================
        // 🔴 CAO-3: invoices — CHECK nhất quán số tiền
        // ====================================================================
        if ($isMysql) {
            // MySQL 8.0.16+ hỗ trợ CHECK constraints
            // Nếu DB < 8.0.16 thì câu này sẽ bị ignore (MySQL behaviour)
            $this->safeDbStatement("
                ALTER TABLE invoices
                ADD CONSTRAINT chk_invoice_amounts
                    CHECK (total_amount = subtotal + vat_amount)
            ");

            // CHECK vat_amount hợp lệ (chấp nhận sai lệch ±1 đơn vị nhỏ nhất do làm tròn)
            $this->safeDbStatement("
                ALTER TABLE invoices
                ADD CONSTRAINT chk_invoice_vat
                    CHECK (ABS(vat_amount - ROUND(subtotal * vat_rate / 100, 2)) <= 0.01)
            ");

            $this->safeDbStatement("
                ALTER TABLE invoices
                ADD CONSTRAINT chk_invoice_non_negative
                    CHECK (subtotal >= 0 AND vat_amount >= 0 AND total_amount >= 0)
            ");
        }

        // ====================================================================
        // 🟠 TRUNG-1: users.driver_id — thêm UNIQUE constraint (quan hệ 1-1)
        // ====================================================================
        Schema::table('users', function (Blueprint $table): void {
            if (! $this->indexExists('users', 'users_driver_id_unique')) {
                $table->unique('driver_id', 'users_driver_id_unique');
            }
        });

        // ====================================================================
        // 🟠 TRUNG-2: positions.code — đổi UNIQUE global → UNIQUE per company
        // ====================================================================
        // Migration 2026_04_16_142500 đã thêm company_id vào positions
        // Ta chỉ cần xoá unique cũ và thêm composite unique mới
        if (Schema::hasColumn('positions', 'company_id')) {
            Schema::table('positions', function (Blueprint $table): void {
                try {
                    $table->dropUnique(['code']);
                } catch (\Throwable) {
                    // index có thể tên khác
                    try {
                        $table->dropUnique('positions_code_unique');
                    } catch (\Throwable) {
                        // ignore
                    }
                }
                if (! $this->indexExists('positions', 'positions_company_code_unique')) {
                    $table->unique(['company_id', 'code'], 'positions_company_code_unique');
                }
            });
        }

        // ====================================================================
        // 🟠 TRUNG-3: payrolls — thêm locked_by
        // ====================================================================
        Schema::table('payrolls', function (Blueprint $table): void {
            if (! Schema::hasColumn('payrolls', 'locked_by')) {
                $table->unsignedBigInteger('locked_by')->nullable()->after('locked_at')
                    ->comment('User ID đã lock bảng lương');
                $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        // ====================================================================
        // 🟠 TRUNG-4: journal_entry_lines — double-entry CHECK (debit XOR credit)
        // ====================================================================
        if ($isMysql) {
            $this->safeDbStatement("
                ALTER TABLE journal_entry_lines
                ADD CONSTRAINT chk_jel_debit_credit
                    CHECK (NOT (debit > 0 AND credit > 0))
            ");

            $this->safeDbStatement("
                ALTER TABLE journal_entry_lines
                ADD CONSTRAINT chk_jel_non_negative
                    CHECK (debit >= 0 AND credit >= 0)
            ");
        }

        // ====================================================================
        // 🟠 TRUNG-5: vehicle_assignments — CHECK to_date >= from_date
        // ====================================================================
        if ($isMysql) {
            $this->safeDbStatement("
                ALTER TABLE vehicle_assignments
                ADD CONSTRAINT chk_va_dates
                    CHECK (to_date IS NULL OR to_date >= from_date)
            ");
        }

        // ====================================================================
        // 🟡 THẤP-1: leave_requests — CHECK to_date >= from_date
        // ====================================================================
        if ($isMysql && Schema::hasTable('leave_requests')) {
            $this->safeDbStatement("
                ALTER TABLE leave_requests
                ADD CONSTRAINT chk_lr_dates
                    CHECK (to_date >= from_date)
            ");

            $this->safeDbStatement("
                ALTER TABLE leave_requests
                ADD CONSTRAINT chk_lr_total_days
                    CHECK (total_days > 0)
            ");
        }

        // ====================================================================
        // 🟡 THẤP-2: leave_balances — CHECK tính hợp lệ số ngày
        // ====================================================================
        if ($isMysql && Schema::hasTable('leave_balances')) {
            $this->safeDbStatement("
                ALTER TABLE leave_balances
                ADD CONSTRAINT chk_lb_non_negative
                    CHECK (entitled_days >= 0 AND used_days >= 0 AND carried_forward_days >= 0)
            ");

            $this->safeDbStatement("
                ALTER TABLE leave_balances
                ADD CONSTRAINT chk_lb_used_not_exceed
                    CHECK (used_days <= entitled_days + carried_forward_days)
            ");
        }

        // ====================================================================
        // 🟡 THẤP-3: leave_types — thêm company_id (nullable = system-wide type)
        // ====================================================================
        if (Schema::hasTable('leave_types') && ! Schema::hasColumn('leave_types', 'company_id')) {
            Schema::table('leave_types', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->after('id')
                    ->comment('NULL = loại nghỉ phép mặc định toàn hệ thống');
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->index('company_id', 'lt_company_id_idx');
            });

            // Đổi UNIQUE(code) → UNIQUE(company_id, code)
            Schema::table('leave_types', function (Blueprint $table): void {
                try {
                    $table->dropUnique(['code']);
                } catch (\Throwable) {
                    try {
                        $table->dropUnique('leave_types_code_unique');
                    } catch (\Throwable) {
                        // ignore
                    }
                }
                if (! $this->indexExists('leave_types', 'lt_company_code_unique')) {
                    $table->unique(['company_id', 'code'], 'lt_company_code_unique');
                }
            });
        }

        // ====================================================================
        // 🟡 THẤP-4: overtime_requests — CHECK end_time > start_time
        // ====================================================================
        if ($isMysql) {
            $this->safeDbStatement("
                ALTER TABLE overtime_requests
                ADD CONSTRAINT chk_ot_time
                    CHECK (end_time > start_time)
            ");

            $this->safeDbStatement("
                ALTER TABLE overtime_requests
                ADD CONSTRAINT chk_ot_hours
                    CHECK (ot_hours > 0 AND ot_hours <= 24)
            ");
        }

        // ====================================================================
        // 🟡 THẤP-5: trip_bonus_rules — CHECK max_km > min_km
        // ====================================================================
        if ($isMysql) {
            $this->safeDbStatement("
                ALTER TABLE trip_bonus_rules
                ADD CONSTRAINT chk_tbr_km
                    CHECK (max_km IS NULL OR max_km > min_km)
            ");

            $this->safeDbStatement("
                ALTER TABLE trip_bonus_rules
                ADD CONSTRAINT chk_tbr_bonus
                    CHECK (bonus_per_km >= 0 AND min_km >= 0)
            ");
        }

        // ====================================================================
        // 🟡 THẤP-6: violations.type — đổi varchar → ENUM
        // ====================================================================
        if ($isMysql) {
            $this->safeDbStatement("
                ALTER TABLE violations
                MODIFY COLUMN `type`
                    ENUM('speeding','route_deviation','fuel_misuse','behavior','accident','other')
                    NOT NULL
            ");
        }

        // ====================================================================
        // 🟡 THẤP-7: roles — thêm company_id, đổi UNIQUE(name) → UNIQUE(company_id, name)
        // ====================================================================
        if (! Schema::hasColumn('roles', 'company_id')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->after('id')
                    ->comment('NULL = system-wide role (admin, super_admin, …)');
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            });

            Schema::table('roles', function (Blueprint $table): void {
                try {
                    $table->dropUnique(['name']);
                } catch (\Throwable) {
                    try {
                        $table->dropUnique('roles_name_unique');
                    } catch (\Throwable) {
                        // ignore
                    }
                }
                if (! $this->indexExists('roles', 'roles_company_name_unique')) {
                    $table->unique(['company_id', 'name'], 'roles_company_name_unique');
                }
            });
        }

        // ====================================================================
        // 🟡 THẤP-8: trip_status_histories / invoice_status_histories
        //            đổi from_status/to_status varchar → ENUM
        // ====================================================================
        if ($isMysql) {
            // trip_status_histories — mirror ENUM từ trips.status
            $this->safeDbStatement("
                ALTER TABLE trip_status_histories
                MODIFY COLUMN `from_status`
                    ENUM('pending','assigned','in_progress','completed','cancelled')
                    NULL,
                MODIFY COLUMN `to_status`
                    ENUM('pending','assigned','in_progress','completed','cancelled')
                    NOT NULL
            ");

            // invoice_status_histories — mirror ENUM từ invoices.status
            $this->safeDbStatement("
                ALTER TABLE invoice_status_histories
                MODIFY COLUMN `from_status`
                    ENUM('draft','issued','paid','cancelled')
                    NULL,
                MODIFY COLUMN `to_status`
                    ENUM('draft','issued','paid','cancelled')
                    NOT NULL
            ");
        }

        // ====================================================================
        // 🟡 THẤP-9: password_reset_tokens — thêm expires_at
        //   (token vẫn plain; nếu muốn hash thì cần application-layer migration riêng)
        // ====================================================================
        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('password_reset_tokens', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('created_at')
                    ->comment('Nếu NULL, application tự tính từ created_at + TTL config');
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOWN
    // ─────────────────────────────────────────────────────────────────────────
    public function down(): void
    {
        $isMysql = $this->isMysql();

        // password_reset_tokens
        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            if (Schema::hasColumn('password_reset_tokens', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });

        // ENUM reversals (MySQL)
        if ($isMysql) {
            $this->safeDbStatement("ALTER TABLE invoice_status_histories MODIFY COLUMN `from_status` VARCHAR(30) NULL");
            $this->safeDbStatement("ALTER TABLE invoice_status_histories MODIFY COLUMN `to_status` VARCHAR(30) NOT NULL");
            $this->safeDbStatement("ALTER TABLE trip_status_histories MODIFY COLUMN `from_status` VARCHAR(30) NULL");
            $this->safeDbStatement("ALTER TABLE trip_status_histories MODIFY COLUMN `to_status` VARCHAR(30) NOT NULL");
            $this->safeDbStatement("ALTER TABLE violations MODIFY COLUMN `type` VARCHAR(50) NOT NULL");
            $this->safeDbStatement("ALTER TABLE journal_entries MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'draft'");
            $this->safeDbStatement("ALTER TABLE chart_of_accounts MODIFY COLUMN `type` VARCHAR(30) NOT NULL");
            $this->safeDbStatement("ALTER TABLE chart_of_accounts MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active'");
        }

        // roles — revert company_id
        // NOTE: Must drop FK first (separate closure), then drop unique index.
        // MySQL error 1553: cannot drop index used as backing index for a FK constraint.
        if (Schema::hasColumn('roles', 'company_id')) {
            // Step 1: drop FK (releases lock on the backing unique index)
            Schema::table('roles', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['company_id']);
                } catch (\Throwable) {}
            });
            // Step 2: now safe to drop unique index, drop column, restore old unique
            Schema::table('roles', function (Blueprint $table): void {
                try {
                    $table->dropUnique('roles_company_name_unique');
                } catch (\Throwable) {}
                $table->dropColumn('company_id');
                if (! $this->indexExists('roles', 'roles_name_unique')) {
                    $table->unique('name', 'roles_name_unique');
                }
            });
        }

        // leave_types — revert company_id (same two-step pattern)
        if (Schema::hasTable('leave_types') && Schema::hasColumn('leave_types', 'company_id')) {
            Schema::table('leave_types', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['company_id']);
                } catch (\Throwable) {}
            });
            Schema::table('leave_types', function (Blueprint $table): void {
                try {
                    $table->dropIndex('lt_company_id_idx');
                } catch (\Throwable) {}
                try {
                    $table->dropUnique('lt_company_code_unique');
                } catch (\Throwable) {}
                $table->dropColumn('company_id');
                if (! $this->indexExists('leave_types', 'leave_types_code_unique')) {
                    $table->unique('code', 'leave_types_code_unique');
                }
            });
        }

        // payrolls — drop locked_by
        if (Schema::hasColumn('payrolls', 'locked_by')) {
            Schema::table('payrolls', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['locked_by']);
                } catch (\Throwable) {}
                $table->dropColumn('locked_by');
            });
        }

        // positions — revert to global unique code
        // Note: positions always has company_id from migration 2026_04_16_142500;
        // we only remove the composite unique we added and restore the original code-only unique.
        Schema::table('positions', function (Blueprint $table): void {
            try {
                $table->dropUnique('positions_company_code_unique');
            } catch (\Throwable) {}
            // Restore original unique only if it doesn't already exist
            if (! $this->indexExists('positions', 'positions_code_unique')) {
                try {
                    $table->unique('code', 'positions_code_unique');
                } catch (\Throwable) {}
            }
        });

        // users — drop driver_id unique
        Schema::table('users', function (Blueprint $table): void {
            try {
                $table->dropUnique('users_driver_id_unique');
            } catch (\Throwable) {}
        });

        // MySQL CHECK constraints — drop
        if ($isMysql) {
            foreach ([
                ['invoices', 'chk_invoice_amounts'],
                ['invoices', 'chk_invoice_vat'],
                ['invoices', 'chk_invoice_non_negative'],
                ['journal_entry_lines', 'chk_jel_debit_credit'],
                ['journal_entry_lines', 'chk_jel_non_negative'],
                ['vehicle_assignments', 'chk_va_dates'],
                ['leave_requests', 'chk_lr_dates'],
                ['leave_requests', 'chk_lr_total_days'],
                ['leave_balances', 'chk_lb_non_negative'],
                ['leave_balances', 'chk_lb_used_not_exceed'],
                ['overtime_requests', 'chk_ot_time'],
                ['overtime_requests', 'chk_ot_hours'],
                ['trip_bonus_rules', 'chk_tbr_km'],
                ['trip_bonus_rules', 'chk_tbr_bonus'],
            ] as [$tbl, $constraint]) {
                $this->safeDbStatement("ALTER TABLE `{$tbl}` DROP CHECK `{$constraint}`");
            }
        }

        // journal_entries — drop company_id (two-step: FK first, then index)
        if (Schema::hasColumn('journal_entries', 'company_id')) {
            // Step 1: drop FK so the backing index (je_company_id_idx) is released
            Schema::table('journal_entries', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['company_id']);
                } catch (\Throwable) {}
            });
            // Step 2: safe to drop index and column
            Schema::table('journal_entries', function (Blueprint $table): void {
                try {
                    $table->dropIndex('je_company_id_idx');
                } catch (\Throwable) {}
                $table->dropColumn('company_id');
            });
        }

        // chart_of_accounts — revert company_id + UNIQUE (two-step: FK first, then index)
        if (Schema::hasColumn('chart_of_accounts', 'company_id')) {
            Schema::table('chart_of_accounts', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['company_id']);
                } catch (\Throwable) {}
            });
            Schema::table('chart_of_accounts', function (Blueprint $table): void {
                try {
                    $table->dropUnique('coa_company_code_unique');
                } catch (\Throwable) {}
                try {
                    $table->dropIndex('coa_company_id_idx');
                } catch (\Throwable) {}
                $table->dropColumn('company_id');
                if (! $this->indexExists('chart_of_accounts', 'chart_of_accounts_code_unique')) {
                    $table->unique('code');
                }
            });
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function isMysql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }

    /**
     * Thực thi raw SQL, bỏ qua lỗi "duplicate constraint" hay "doesn't exist".
     * MySQL trả về nhiều Errno tuỳ version — catch \Throwable là an toàn nhất.
     */
    private function safeDbStatement(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            // Log cảnh báo nhưng không làm migration fail
            // (vd: CHECK đã tồn tại, ENUM value chưa migrate đủ data)
            \Illuminate\Support\Facades\Log::warning(
                '[schema_hardening] Statement skipped: ' . $e->getMessage(),
                ['sql' => trim($sql)]
            );
        }
    }

    /**
     * Kiểm tra index/unique có tồn tại không (MySQL).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        if (! $this->isMysql()) {
            return false; // SQLite không cần check
        }
        try {
            $result = DB::select(
                "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
                [$indexName]
            );

            return count($result) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
};
