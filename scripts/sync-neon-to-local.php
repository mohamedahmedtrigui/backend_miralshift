<?php
/**
 * One-way sync: Neon (real production DB, source of truth) -> local MySQL
 * "miralshift" DB (disposable, viewed in phpMyAdmin).
 *
 * Usage (from the backend_miralshift/ directory):
 *   php scripts/sync-neon-to-local.php
 *
 * Requirements:
 *   - .env.production must exist in this directory with the real Neon
 *     credentials (DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD).
 *   - Local MySQL must be running on 127.0.0.1:3306 with a "miralshift"
 *     database whose schema is already migrated (php artisan migrate).
 *
 * This is READ-ONLY on Neon and DESTRUCTIVE on the local copy (each table
 * listed below is truncated and fully replaced). Never point this script's
 * $local connection at Neon or any other real database.
 *
 * Only business tables are synced — sessions/personal_access_tokens/cache/
 * jobs are ephemeral runtime state, not business data, and are left alone.
 */

function loadEnv(string $path): array
{
    $out = [];
    foreach (file($path) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $out[trim($k)] = trim($v);
    }
    return $out;
}

$env = loadEnv(__DIR__ . '/../.env.production');
$neon = new PDO(
    "pgsql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']};sslmode=require",
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$local = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=miralshift;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// FK-safe order: role_user depends on roles+users; shifts/users depend on
// agencies; users also depends on shifts.
$tables = ['companies', 'agencies', 'zones', 'roles', 'shifts', 'users', 'role_user'];

$local->exec('SET FOREIGN_KEY_CHECKS=0');
try {
    foreach ($tables as $table) {
        $rows = $neon->query("SELECT * FROM \"$table\"")->fetchAll(PDO::FETCH_ASSOC);
        $local->exec("TRUNCATE TABLE `$table`");

        if (empty($rows)) {
            echo "$table: 0 rows (truncated, nothing to insert)\n";
            continue;
        }

        $columns = array_keys($rows[0]);
        $columnList = implode(', ', array_map(fn ($c) => "`$c`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $local->prepare("INSERT INTO `$table` ($columnList) VALUES ($placeholders)");

        foreach ($rows as $row) {
            foreach ($columns as $i => $col) {
                $value = $row[$col];
                $stmt->bindValue($i + 1, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }
            $stmt->execute();
        }
        echo "$table: " . count($rows) . " rows synced\n";
    }
} finally {
    $local->exec('SET FOREIGN_KEY_CHECKS=1');
}

echo "\n--- verification ---\n";
foreach ($tables as $table) {
    $neonCount = $neon->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
    $localCount = $local->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    $status = ($neonCount == $localCount) ? 'OK' : 'MISMATCH';
    echo "$table: neon=$neonCount local=$localCount [$status]\n";
}
