<?php
/**
 * Menu items — dynamic navigation management.
 */

declare(strict_types=1);

function handle_menu(string $M, array $seg): void
{
    require_once __DIR__ . '/crud.php';

    // Public read (for frontend rendering)
    if ($M === 'GET' && !isset($seg[0])) {
        $items = collection_list('menu_items');
        usort($items, function ($a, $b) {
            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
        });
        // Public gets only active items; admin gets all
        $u = null;
        try { $u = current_user(); } catch (\Throwable $e) {}
        if (!$u || !can($u, 'editor')) {
            $items = array_filter($items, function ($i) {
                return ($i['is_active'] ?? 1) == 1;
            });
        }
        ok(array_values($items));
    }

    // Single item read
    if ($M === 'GET' && isset($seg[0])) {
        ok(find_row('menu_items', $seg[0]) ?: fail('Not found', 404));
    }

    // Writes require editor+
    $u = current_user();
    if (!can($u, 'editor')) fail('Forbidden', 403);

    if ($M === 'POST') {
        $b = read_body();
        $title = trim((string) ($b['title'] ?? ''));
        $url   = trim((string) ($b['url'] ?? '#'));
        if ($title === '') fail('Title required', 400);
        $row = collection_insert('menu_items', [
            'title' => sanitize($title),
            'url' => sanitize($url),
            'sort_order' => (int) ($b['sort_order'] ?? 0),
            'is_active' => isset($b['is_active']) ? (int) $b['is_active'] : 1,
        ]);
        audit_log('create', 'menu_items', $row['id']);
        ok($row, 201);
    }

    if ($M === 'PUT' && isset($seg[0])) {
        $row = find_row('menu_items', $seg[0]);
        if (!$row) fail('Not found', 404);
        $b = read_body();
        if (isset($b['title'])) $b['title'] = sanitize((string) $b['title']);
        if (isset($b['url'])) $b['url'] = sanitize((string) $b['url']);
        $row = collection_update('menu_items', $row['id'], $b);
        audit_log('update', 'menu_items', $row['id']);
        ok($row);
    }

    if ($M === 'DELETE' && isset($seg[0])) {
        $row = find_row('menu_items', $seg[0]);
        if (!$row) fail('Not found', 404);
        collection_delete('menu_items', $row['id']);
        audit_log('delete', 'menu_items', $row['id']);
        ok(true);
    }

    fail('Not found', 404);
}
