<?php
/**
 * Notifications endpoints (any authenticated user).
 */

declare(strict_types=1);

function handle_notifications(string $M, array $seg): void
{
    require_once __DIR__ . '/crud.php';
    $u = current_user();
    if (!$u) fail('Unauthorized', 401);

    // GET /api/v1/notifications — list recent 50
    if ($M === 'GET' && empty($seg)) {
        $rows = all_rows('notifications');
        $rows = array_reverse(array_slice(array_reverse($rows), 0, 50));
        foreach ($rows as &$r) { $r['read'] = (bool) $r['read']; $r = decode_json_cols('notifications', $r); unset($r); }
        ok($rows);
    }

    // PUT /api/v1/notifications/read-all — mark all as read
    if ($M === 'PUT' && ($seg[0] ?? '') === 'read-all') {
        db()->query('UPDATE notifications SET `read` = 1');
        ok(true);
    }

    // PUT /api/v1/notifications/:id — mark single as read
    if ($M === 'PUT' && isset($seg[0]) && $seg[0] !== 'read-all') {
        $row = find_row('notifications', $seg[0]);
        if (!$row) fail('Not found', 404);
        db()->prepare('UPDATE notifications SET `read` = 1 WHERE id = ?')->execute([$seg[0]]);
        ok(true);
    }

    // DELETE /api/v1/notifications/:id — delete single
    if ($M === 'DELETE' && isset($seg[0])) {
        $row = find_row('notifications', $seg[0]);
        if (!$row) fail('Not found', 404);
        collection_delete('notifications', $seg[0]);
        ok(true);
    }

    fail('Not found', 404);
}
