<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/router.php';
require dirname(__DIR__) . '/api/v1/crud.php';

$full = dirname(__DIR__) . '/uploads/backups/manual-20260907-085649.json';
$snap = json_decode((string) file_get_contents($full), true);
if (!is_array($snap) || empty($snap['_cols'])) {
    fwrite(STDERR, "INVALID backup file\n");
    exit(1);
}
db()->beginTransaction();
try {
    foreach ($snap['_cols'] as $t) {
        if (!isset($snap[$t]) || !is_array($snap[$t])) continue;
        db()->query('DELETE FROM `' . safe_ident($t) . '`');
        $n = 0;
        foreach ($snap[$t] as $row) {
            if (empty($row['id'])) continue;
            collection_insert_id($t, $row);
            $n++;
        }
        echo "  restored $t: $n rows\n";
    }
    db()->commit();
    echo "RESTORE OK\n";
} catch (\Throwable $e) {
    db()->rollBack();
    echo "RESTORE FAILED: " . $e->getMessage() . "\n";
}