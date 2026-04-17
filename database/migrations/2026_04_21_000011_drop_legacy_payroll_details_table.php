<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the legacy payroll_details table.
 *
 * This table was created by the original MVP migration (2026_02_03) before the
 * driver-centric payroll engine was introduced.  All payroll detail data now
 * lives in payroll_lines.  The table has no corresponding Eloquent model and
 * is not referenced anywhere in the application code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payroll_details');
    }

    public function down(): void
    {
        // Intentionally left empty — restoring the legacy table would serve no
        // purpose and the old migration still exists if it is ever needed.
    }
};
