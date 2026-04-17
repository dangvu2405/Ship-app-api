<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index()
                ->comment('NULL = áp dụng cho toàn hệ thống');
            $table->string('category', 50)->index()
                ->comment('TRACKING | PAYROLL | FUEL | COMPLIANCE | GENERAL');
            $table->string('title');
            $table->text('content');
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE knowledge_articles ADD FULLTEXT INDEX ft_knowledge (title, content)');
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
    }
};
