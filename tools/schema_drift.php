#!/usr/bin/env php
<?php

/**
 * Compare CREATE TABLE names in database.md vs explicit `protected $table` in app/Models.
 * Models without $table use Laravel defaults — not inferred here; see docs/MODEL_SHIP_DB_MATRIX.md.
 *
 * Usage: php tools/schema_drift.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$dbMd = $root.'/database.md';
$modelsDir = $root.'/app/Models';

if (! is_readable($dbMd)) {
    fwrite(STDERR, "Cannot read: {$dbMd}\n");
    exit(1);
}

$content = file_get_contents($dbMd);
if ($content === false) {
    fwrite(STDERR, "Failed to read database.md\n");
    exit(1);
}

preg_match_all('/CREATE TABLE `([^`]+)`/m', $content, $m);
$tablesInDump = array_unique($m[1]);
sort($tablesInDump);
$dumpSet = array_flip($tablesInDump);

/** @var array<string, string> modelClass => explicit table or empty */
$explicitTables = [];
$noExplicitTable = [];

foreach (glob($modelsDir.'/*.php') ?: [] as $file) {
    $basename = basename($file, '.php');
    $src = file_get_contents($file);
    if ($src === false) {
        continue;
    }
    if (preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $src, $tm)) {
        $explicitTables[$basename] = $tm[1];
    } else {
        $noExplicitTable[] = $basename;
    }
}

$orphans = [];
foreach ($explicitTables as $class => $tbl) {
    if (! isset($dumpSet[$tbl])) {
        $orphans[] = "{$class} → `{$tbl}`";
    }
}

$tablesFromExplicitModels = array_flip(array_values($explicitTables));
$dbOnly = [];
foreach ($tablesInDump as $tbl) {
    if (! isset($tablesFromExplicitModels[$tbl])) {
        $dbOnly[] = $tbl;
    }
}

echo "=== tables_in_dump: ".count($tablesInDump)." ===\n";
echo "=== models with explicit \$table: ".count($explicitTables)." ===\n";
echo "=== models without explicit \$table: ".count($noExplicitTable)." (convention — see matrix) ===\n\n";

echo "--- model_orphan (explicit \$table not in database.md) ---\n";
if ($orphans === []) {
    echo "(none)\n";
} else {
    echo implode("\n", $orphans)."\n";
}

echo "\n--- db_only heuristic (dump table not used as any model's explicit \$table) ---\n";
echo "Many framework/business tables have no explicit \$table on a model; see MODEL_SHIP_DB_MATRIX §3.3.\n";
echo "Count: ".count($dbOnly)."\n";
if (count($dbOnly) <= 80) {
    echo implode("\n", $dbOnly)."\n";
}

echo "\n--- models without explicit \$table (sample) ---\n";
sort($noExplicitTable);
echo implode(', ', array_slice($noExplicitTable, 0, 40)).(count($noExplicitTable) > 40 ? '…' : '')."\n";

echo "\nDone.\n";
