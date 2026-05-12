<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if (Schema::hasColumn('users', 'driver_id')) {
            if ($dbDriver === 'sqlite') {
                $this->dropColumnSqliteRaw('users', 'driver_id');
            } else {
                Schema::table('users', function (Blueprint $table) {
                    if ($this->foreignKeyExists('users', 'users_driver_id_foreign')) {
                        $table->dropForeign(['driver_id']);
                    }
                    $table->dropColumn('driver_id');
                });
            }
        }

        if (Schema::hasColumn('drivers', 'user_id')) {
            if ($dbDriver === 'sqlite') {
                $this->dropColumnSqliteRaw('drivers', 'user_id');
            } else {
                Schema::table('drivers', function (Blueprint $table) {
                    if ($this->foreignKeyExists('drivers', 'drivers_user_id_foreign')) {
                        $table->dropForeign(['user_id']);
                    }
                    if ($this->indexExists('drivers', 'drivers_user_id_unique')) {
                        $table->dropUnique(['user_id']);
                    }
                    $table->dropColumn('user_id');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('status')->constrained('drivers')->nullOnDelete();
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->unique('user_id');
        });
    }

    /**
     * Drop a column from SQLite by recreating the table without it.
     * SQLite ALTER TABLE DROP COLUMN fails when the dropped column appears
     * in a FOREIGN KEY definition anywhere in the schema.
     */
    private function dropColumnSqliteRaw(string $tableName, string $dropCol): void
    {
        $row = DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type='table' AND name=?",
            [$tableName]
        );
        if (! $row) {
            return;
        }

        $originalSql = $row->sql;
        $columns = Schema::getColumnListing($tableName);
        $keepCols = array_values(array_filter($columns, fn ($c) => $c !== $dropCol));

        $tmpName = $tableName.'_sever_tmp';

        // Build new CREATE TABLE statement:
        // 1. Replace table name with tmp name
        // 2. Remove the column definition and its FK constraint line
        $newSql = $this->removeColumnFromCreate($originalSql, $dropCol, $tmpName);

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement($newSql);

        $colsCsv = implode(', ', array_map(fn ($c) => '"'.$c.'"', $keepCols));
        DB::statement("INSERT INTO \"{$tmpName}\" ({$colsCsv}) SELECT {$colsCsv} FROM \"{$tableName}\"");

        DB::statement("DROP TABLE \"{$tableName}\"");
        DB::statement("ALTER TABLE \"{$tmpName}\" RENAME TO \"{$tableName}\"");

        // Rebuild indexes
        $indexes = DB::select(
            "SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name=? AND sql IS NOT NULL",
            [$tableName]
        );
        foreach ($indexes as $idx) {
            try {
                DB::statement($idx->sql);
            } catch (\Throwable) {
                // skip if already exists or references dropped column
            }
        }

        DB::statement('PRAGMA foreign_keys = ON');
    }

    private function removeColumnFromCreate(string $sql, string $dropCol, string $newName): string
    {
        // Replace table name
        $sql = preg_replace(
            '/^CREATE\s+TABLE\s+(?:"[^"]+"|\'[^\']+\'|\w+)/i',
            "CREATE TABLE \"{$newName}\"",
            $sql,
            1
        );

        // The CREATE TABLE is a single line in SQLite — parse by commas at depth 0
        // Find the opening paren
        $parenStart = strpos($sql, '(');
        $parenEnd = strrpos($sql, ')');
        $inner = substr($sql, $parenStart + 1, $parenEnd - $parenStart - 1);

        // Split clauses by comma (respecting nested parens)
        $clauses = $this->splitClauses($inner);

        $kept = [];
        foreach ($clauses as $clause) {
            $trimmed = trim($clause);
            // Skip the column itself
            if (preg_match('/^["`\']?' . preg_quote($dropCol, '/') . '["`\']?\s+/i', $trimmed)) {
                continue;
            }
            // Skip FK constraint referencing this column
            if (preg_match('/FOREIGN\s+KEY\s*\(\s*["`\']?' . preg_quote($dropCol, '/') . '["`\']?\s*\)/i', $trimmed)) {
                continue;
            }
            $kept[] = $clause;
        }

        $prefix = substr($sql, 0, $parenStart + 1);
        $suffix = ')';

        return $prefix . implode(',', $kept) . $suffix;
    }

    private function splitClauses(string $inner): array
    {
        $clauses = [];
        $depth = 0;
        $current = '';
        for ($i = 0, $len = strlen($inner); $i < $len; $i++) {
            $c = $inner[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $clauses[] = $current;
                $current = '';
                continue;
            }
            $current .= $c;
        }
        if (trim($current) !== '') {
            $clauses[] = $current;
        }

        return $clauses;
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', $constraint)
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->exists();
        }

        return false;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', $indexName)
                ->exists();
        }

        return false;
    }
};
