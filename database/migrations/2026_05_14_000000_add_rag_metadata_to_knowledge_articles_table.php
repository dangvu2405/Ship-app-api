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
            if (! Schema::hasColumn('knowledge_articles', 'status')) {
                $table->string('status', 20)->default('published')->index();
            }

            if (! Schema::hasColumn('knowledge_articles', 'source')) {
                $table->string('source', 120)->nullable();
            }

            if (! Schema::hasColumn('knowledge_articles', 'metadata')) {
                $table->json('metadata')->nullable();
            }

            if (! Schema::hasColumn('knowledge_articles', 'embedding')) {
                $table->json('embedding')->nullable();
            }

            if (! Schema::hasColumn('knowledge_articles', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table): void {
            foreach (['created_by', 'embedding', 'metadata', 'source', 'status'] as $column) {
                if (Schema::hasColumn('knowledge_articles', $column)) {
                    if ($column === 'created_by') {
                        $table->dropConstrainedForeignId('created_by');
                        continue;
                    }

                    $table->dropColumn($column);
                }
            }
        });
    }
};
