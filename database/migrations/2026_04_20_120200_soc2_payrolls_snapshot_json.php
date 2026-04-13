<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            if (! Schema::hasColumn('payrolls', 'snapshot_json')) {
                $table->json('snapshot_json')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            if (Schema::hasColumn('payrolls', 'snapshot_json')) {
                $table->dropColumn('snapshot_json');
            }
        });
    }
};
