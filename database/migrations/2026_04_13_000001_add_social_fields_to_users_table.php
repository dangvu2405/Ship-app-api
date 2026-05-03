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
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'social_provider')) {
                $table->string('social_provider', 50)->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'social_provider_id')) {
                $table->string('social_provider_id')->nullable()->after('social_provider');
            }

            if (! $this->hasIndex('users', 'users_social_provider_idx')) {
                $table->index(['social_provider', 'social_provider_id'], 'users_social_provider_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if ($this->hasIndex('users', 'users_social_provider_idx')) {
                $table->dropIndex('users_social_provider_idx');
            }

            if (Schema::hasColumn('users', 'social_provider_id')) {
                $table->dropColumn('social_provider_id');
            }

            if (Schema::hasColumn('users', 'social_provider')) {
                $table->dropColumn('social_provider');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            /** @var array<int, object> $rows */
            $rows = $connection->select("PRAGMA index_list('{$table}')");

            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        /** @var array<int, object> $rows */
        $rows = $connection->select(
            'SELECT index_name FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$connection->getDatabaseName(), $table, $indexName]
        );

        return $rows !== [];
    }
};
