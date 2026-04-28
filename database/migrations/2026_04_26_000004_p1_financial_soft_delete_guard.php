<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1: Chặn soft-delete trên các bảng tài chính đã ở trạng thái final.
 *
 * Giải pháp: MySQL BEFORE DELETE trigger trả lỗi khi status là final.
 * Các bảng được bảo vệ:
 *   - invoices:  status IN ('paid')
 *   - payrolls:  status IN ('paid', 'locked', 'approved')
 *
 * Lưu ý: payroll_lines không có status riêng, bảo vệ thông qua payrolls.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared("
            CREATE TRIGGER `trg_invoices_no_delete_final`
            BEFORE DELETE ON `invoices`
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('paid') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Cannot delete invoice with final status: paid. Use status=cancelled instead.';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER `trg_payrolls_no_delete_final`
            BEFORE DELETE ON `payrolls`
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('paid', 'locked', 'approved') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Cannot delete payroll with final status. Reverse via adjustment instead.';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER `trg_journal_entries_no_delete_posted`
            BEFORE DELETE ON `journal_entries`
            FOR EACH ROW
            BEGIN
                IF OLD.status = 'posted' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Cannot delete posted journal entry. Reverse via credit/debit note.';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `trg_journal_entries_no_delete_posted`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trg_payrolls_no_delete_final`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trg_invoices_no_delete_final`');
    }
};
