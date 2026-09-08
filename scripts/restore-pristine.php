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
    // Re-apply the real-photo migration (media rows + category covers) —
    // the pristine snapshot predates it, so it is not part of _cols.
    db()->exec(file_get_contents(dirname(__DIR__) . '/sql/collections-real-photos.sql'));
    // Restore known-good live settings (the snapshot does not carry settings,
    // and the regression gates overwrite it with test fixtures).
    $s = get_settings();
    foreach ([
        'siteName'  => 'MEB Premium',
        'phone'     => '+79000000000',
        'email'     => 'admin@meb.local',
        'logo'      => '/uploads/branding/tky9wd14922.png',
        'favicon'   => '/uploads/branding/tky9wd14922.png',
        'copyright' => '',
        'socials'   => [],
    ] as $k => $v) {
        $s[$k] = $v;
    }
    save_settings($s);
    echo "restored settings + photo migration OK\n";
    echo "RESTORE OK\n";
} catch (\Throwable $e) {
    db()->rollBack();
    echo "RESTORE FAILED: " . $e->getMessage() . "\n";
}