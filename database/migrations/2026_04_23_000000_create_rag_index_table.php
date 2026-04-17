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
        Schema::create('rag_index', function (Blueprint $table): void {
            $table->id();

            // Source reference
            $table->string('source_table', 50)->comment('trips | drivers | payroll_lines | violations');
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('company_id')->index();

            // Searchable text in Vietnamese natural language
            $table->mediumText('content');

            // Embedding stored as JSON float array (bge-m3 = 1024 dims)
            // NULL until Ollama indexes it; fallback uses keyword-only search
            $table->json('embedding')->nullable();

            // Metadata for filtering without re-parsing content
            $table->json('metadata')->nullable();

            // Track staleness — re-index when source row is updated
            $table->timestamp('source_updated_at');

            $table->timestamps();

            $table->unique(['source_table', 'source_id'], 'uq_rag_source');
            $table->index('source_updated_at');
        });

        // MySQL FULLTEXT for hybrid search (fallback when embedding is NULL)
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE rag_index ADD FULLTEXT INDEX ft_rag_content (content)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_index');
    }
};
