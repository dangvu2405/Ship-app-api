<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flips the Driver–User ownership so Driver owns the FK (1:1, drivers.user_id → users.id).
 * Previously: users.driver_id → drivers.id  (User belongsTo Driver)
 * After:      drivers.user_id → users.id     (Driver belongsTo User / User hasOne Driver)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1 — add user_id to drivers (nullable during migration)
        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Step 2 — migrate data: for every user that has a driver_id, set drivers.user_id
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                UPDATE drivers d
                INNER JOIN users u ON u.driver_id = d.id
                SET d.user_id = u.id
                WHERE u.driver_id IS NOT NULL
            SQL);
        } else {
            // SQLite / PostgreSQL compatible
            DB::statement(<<<'SQL'
                UPDATE drivers
                SET user_id = (
                    SELECT id FROM users WHERE users.driver_id = drivers.id LIMIT 1
                )
                WHERE EXISTS (
                    SELECT 1 FROM users WHERE users.driver_id = drivers.id
                )
            SQL);
        }

        // Step 3 — enforce 1:1 with unique index on drivers.user_id
        Schema::table('drivers', function (Blueprint $table) {
            $table->unique('user_id');
        });

        // Step 4 — drop the old FK and column from users.
        if (DB::getDriverName() === 'sqlite') {
            // SQLite 3.35+ ALTER TABLE DROP COLUMN rejects columns that appear in any FK
            // definition, even with foreign_keys=OFF. The only reliable fix is to manually
            // recreate the table without the column (which is what Laravel does pre-3.35).
            $this->dropColumnSqlite('users', 'driver_id');
        } else {
            Schema::table('users', function (Blueprint $table) {
                if ($this->foreignKeyExists('users', 'users_driver_id_foreign')) {
                    $table->dropForeign(['driver_id']);
                }
                $table->dropColumn('driver_id');
            });
        }
    }

    public function down(): void
    {
        // Restore driver_id on users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('driver_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('drivers')
                ->nullOnDelete();
        });

        // Migrate data back
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                UPDATE users u
                INNER JOIN drivers d ON d.user_id = u.id
                SET u.driver_id = d.id
                WHERE d.user_id IS NOT NULL
            SQL);
        } else {
            DB::statement(<<<'SQL'
                UPDATE users
                SET driver_id = (
                    SELECT id FROM drivers WHERE drivers.user_id = users.id LIMIT 1
                )
                WHERE EXISTS (
                    SELECT 1 FROM drivers WHERE drivers.user_id = users.id
                )
            SQL);
        }

        // Drop user_id from drivers
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    private function dropColumnSqlite(string $table, string $column): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        $tempTable = $table.'__drop__temp';

        // Build column definitions from PRAGMA (avoids fragile SQL text parsing).
        $masterSql = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$table])->sql ?? '';
        $isAutoincrement = (bool) preg_match('/\bautoincrement\b/i', $masterSql);

        $cols = collect(DB::select("PRAGMA table_info(\"{$table}\")"))->reject(fn ($c) => $c->name === $column);

        $pkCols = $cols->where('pk', '>', 0);

        $colDefs = $cols->map(function ($c) use ($pkCols, $isAutoincrement) {
            $def = '"'.$c->name.'" '.$c->type;
            if ($c->pk > 0 && $pkCols->count() === 1) {
                $def .= ' primary key'.($isAutoincrement ? ' autoincrement' : '');

                return $def;
            }
            if ($c->notnull) {
                $def .= ' not null';
            }
            if ($c->dflt_value !== null) {
                $def .= ' default '.$c->dflt_value;
            }

            return $def;
        });

        if ($pkCols->count() > 1) {
            $pkList = $pkCols->sortBy('pk')->map(fn ($c) => '"'.$c->name.'"')->implode(', ');
            $colDefs->push("primary key ({$pkList})");
        }

        // Rebuild FK constraints, excluding the dropped column.
        $fkDefs = collect(DB::select("PRAGMA foreign_key_list(\"{$table}\")"))->reject(fn ($r) => $r->from === $column)
            ->groupBy('id')
            ->map(function ($rows) {
                $rows = $rows->sortBy('seq');
                $from = $rows->pluck('from')->map(fn ($c) => '"'.$c.'"')->implode(', ');
                $to = $rows->pluck('to')->map(fn ($c) => '"'.$c.'"')->implode(', ');
                $ref = $rows->first()->table;
                $del = strtolower($rows->first()->on_delete);
                $upd = strtolower($rows->first()->on_update);
                $sql = "foreign key ({$from}) references \"{$ref}\" ({$to})";
                if ($del !== 'no action') {
                    $sql .= " on delete {$del}";
                }
                if ($upd !== 'no action') {
                    $sql .= " on update {$upd}";
                }

                return $sql;
            });

        $createSql = 'create table "'.$tempTable.'" ('.$colDefs->merge($fkDefs)->implode(', ').')';
        $keepCols = $cols->pluck('name')->map(fn ($c) => '"'.$c.'"')->implode(', ');

        DB::statement($createSql);
        DB::statement("INSERT INTO \"{$tempTable}\" ({$keepCols}) SELECT {$keepCols} FROM \"{$table}\"");
        DB::statement("DROP TABLE \"{$table}\"");
        DB::statement("ALTER TABLE \"{$tempTable}\" RENAME TO \"{$table}\"");

        // Recreate indexes that do not reference the dropped column.
        foreach (DB::select("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name=? AND sql IS NOT NULL", [$table]) as $idx) {
            if (! preg_match('/"'.preg_quote($column, '/').'"/', $idx->sql)) {
                DB::statement($idx->sql);
            }
        }

        DB::statement('PRAGMA foreign_keys = ON');
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', $constraint)
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->exists();
        }

        return false; // SQLite: column just gets dropped
    }
};
