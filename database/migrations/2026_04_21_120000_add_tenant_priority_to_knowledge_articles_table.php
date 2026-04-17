<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('tenant_priority')
                ->default(0)
                ->after('company_id')
                ->index()
                ->comment('Độ ưu tiên tài liệu trong tenant, số lớn ưu tiên cao');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table): void {
            $table->dropColumn('tenant_priority');
        });
    }
};
