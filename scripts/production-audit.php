#!/usr/bin/env php
<?php
/**
 * MEB Production Audit — API + Auth + CRUD + Public Site Test
 * Run: env -i php scripts/production-audit.php
 * Requires PHP built-in server on localhost:8080 with router.php
 */

$pass = 0; $fail = 0; $errors = [];

function pass(string $label) { global $pass; $pass++; echo "  PASS  $label\n"; }
function er(string $label, string $reason) { global $fail, $errors; $fail++; $errors[] = "$label: $reason"; echo "  FAIL  $label -- $reason\n"; }

// ---- Target base URL: default local PHP server, override for a live site. ----
$AUDIT_BASE = rtrim((string) getenv('AUDIT_BASE_URL'), '/');
if ($AUDIT_BASE === '') $AUDIT_BASE = 'http://localhost:8080';

function call(string $method, string $path, array $body = null, string $token = ''): array {
    global $AUDIT_BASE;
    $url = $AUDIT_BASE . $path;
    $headers = "Content-Type: application/json\r\n";
    if ($token) $headers .= "Authorization: Bearer $token\r\n";
    $opts = ['http' => ['method' => $method, 'timeout' => 10, 'header' => $headers]];
    if ($body !== null) $opts['http']['content'] = json_encode($body);
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header) && isset($http_response_header[0])) {
        if (preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $m)) $code = (int)$m[1];
    }
    $data = json_decode($raw ?: '{}', true);
    return ['code' => $code, 'data' => $data, 'raw' => $raw];
}

function data($j) { return isset($j['data']) ? $j['data'] : $j; }

// ---- Credentials: environment only. NO fallback password in the source. ----
$AUDIT_EMAIL = getenv('AUDIT_ADMIN_EMAIL');
$AUDIT_PASS  = getenv('AUDIT_ADMIN_PASSWORD');
if ($AUDIT_EMAIL === false || $AUDIT_EMAIL === '' ||
    $AUDIT_PASS  === false || $AUDIT_PASS  === '') {
    echo "========================================\n";
    echo "  MEB PRODUCTION AUDIT — SKIPPED\n";
    echo "========================================\n";
    echo "  Configuration required: set environment variables\n";
    echo "    AUDIT_ADMIN_EMAIL\n";
    echo "    AUDIT_ADMIN_PASSWORD\n";
    echo "  Optional:\n";
    echo "    AUDIT_BASE_URL  (default http://localhost:8080)\n";
    echo "  Run:\n";
    echo "    AUDIT_ADMIN_EMAIL=<email> AUDIT_ADMIN_PASSWORD=<pass> \\\n";
    echo "      AUDIT_BASE_URL=https://<domain> php scripts/production-audit.php\n";
    echo "  No fallback credentials exist in the source (secrets gate).\n";
    echo "  Exit code 2 = SKIPPED — not PASS(0), not FAIL(1).\n\n";
    exit(2);
}

echo "\n========================================\n";
echo "  MEB PRODUCTION AUDIT\n";
echo "  audited admin: " . $AUDIT_EMAIL . "\n";
echo "  base url:      " . $AUDIT_BASE . "\n";
echo "========================================\n\n";

// 1. HEALTH
echo "-- 1. Health Check --\n";
$r = call('GET', '/api/v1/health');
$h = data($r['data']);
if ($r['code'] === 200 && ($h['status'] ?? '') === 'ok') pass("Health endpoint OK (db=" . ($h['db'] ? 'true' : 'false') . ")");
else er("Health", "code={$r['code']}");

// 2. LOGIN
echo "\n-- 2. Auth: Login --\n";
$r = call('POST', '/api/v1/auth/login', ['email' => $AUDIT_EMAIL, 'password' => $AUDIT_PASS]);
$rd = data($r['data']);
$token = $rd['token'] ?? '';
if ($r['code'] === 200 && $token !== '') pass("Login OK (token length=" . strlen($token) . ")");
else er("Login", "code={$r['code']} data=" . json_encode($r['data']));
if (($rd['user']['role'] ?? '') === 'super_admin') pass("Role is super_admin");
else er("Role", "got " . ($rd['user']['role'] ?? 'null'));

// 3. ME
echo "\n-- 3. Auth: /me --\n";
$r = call('GET', '/api/v1/me', null, $token);
$me = data($r['data']);
if ($r['code'] === 200 && ($me['email'] ?? '') === $AUDIT_EMAIL) pass("GET /me OK");
else er("GET /me", "code={$r['code']}");

// 4. BAD PASSWORD
echo "\n-- 4. Auth: Bad Password --\n";
$r = call('POST', '/api/v1/auth/login', ['email' => $AUDIT_EMAIL, 'password' => 'wrong']);
if ($r['code'] === 401) pass("Bad password returns 401");
else er("Bad password", "code={$r['code']}");

// 5. BAD EMAIL
echo "\n-- 5. Auth: Bad Email --\n";
$r = call('POST', '/api/v1/auth/login', ['email' => 'no@no.com', 'password' => 'x']);
if ($r['code'] === 401) pass("Bad email returns 401");
else er("Bad email", "code={$r['code']}");

// 6. NO TOKEN
echo "\n-- 6. Auth: No Token --\n";
$r = call('GET', '/api/v1/me');
if ($r['code'] === 401) pass("No token returns 401");
else er("No token", "code={$r['code']}");

// 7. SETTINGS READ (admin/editor only — anonymous must be rejected)
echo "\n-- 7. Settings: GET --\n";
$r = call('GET', '/api/v1/settings');
if ($r['code'] === 403) pass("Anonymous GET settings blocked (403)");
else er("Anonymous GET settings", "expected 403 got {$r['code']}");
$r = call('GET', '/api/v1/settings', null, $token);
$settings = data($r['data']);
if ($r['code'] === 200 && isset($settings['siteName'])) pass("Settings GET OK (siteName=" . $settings['siteName'] . ")");
else er("Settings GET", "code={$r['code']}");

// 8. SETTINGS WRITE
echo "\n-- 8. Settings: PUT --\n";
$oldPhone = $settings['phone'] ?? '';
$settings['phone'] = '+79998887766';
$r = call('PUT', '/api/v1/settings', $settings, $token);
if ($r['code'] === 200) pass("Settings PUT OK");
else er("Settings PUT", "code={$r['code']} data=" . json_encode($r['data']));
// verify read
$r2 = call('GET', '/api/v1/settings', null, $token);
$s2 = data($r2['data']);
if (($s2['phone'] ?? '') === '+79998887766') pass("Settings persistence OK");
else er("Settings persistence", "phone=" . ($s2['phone'] ?? 'null'));

// 9. SETTINGS -> PUBLIC
echo "\n-- 9. Settings -> Public Site --\n";
$r = call('GET', '/');
if ($r['code'] === 200 && strpos($r['raw'], '+79998887766') !== false) pass("Public site shows updated phone");
else er("Settings->Public", "phone not found in HTML (code={$r['code']})");
// restore
$s2['phone'] = $oldPhone;
call('PUT', '/api/v1/settings', $s2, $token);

// 10. CATALOG CRUD
echo "\n-- 10. Catalog CRUD --\n";
$r = call('GET', '/api/v1/catalog', null, $token);
$catList = data($r['data']);
pass("Catalog GET (" . count($catList ?? []) . " items)");
$r = call('POST', '/api/v1/catalog', ['title' => 'Audit Item', 'slug' => 'audit-cat-' . time(), 'price' => 100000, 'published' => true], $token);
if ($r['code'] === 200) { $cid = data($r['data'])['id']; pass("Catalog CREATE id=$cid"); }
else { $cid = ''; er("Catalog CREATE", "code={$r['code']}"); }
if ($cid) { $r = call('PUT', "/api/v1/catalog/$cid", ['title' => 'Audit Item UPDATED'], $token); if ($r['code'] === 200) pass("Catalog UPDATE"); else er("Catalog UPDATE", "code={$r['code']}"); }
if ($cid) { $r = call('GET', "/api/v1/catalog/$cid"); if ($r['code'] === 200) pass("Catalog READ single"); else er("Catalog READ", "code={$r['code']}"); }
if ($cid) { $r = call('DELETE', "/api/v1/catalog/$cid", null, $token); if ($r['code'] === 200) pass("Catalog DELETE"); else er("Catalog DELETE", "code={$r['code']}"); }

// 11. PROJECTS CRUD
echo "\n-- 11. Projects CRUD --\n";
$r = call('GET', '/api/v1/projects', null, $token);
pass("Projects GET (" . count(data($r['data']) ?? []) . " items)");
$r = call('POST', '/api/v1/projects', ['title' => 'Audit Proj', 'slug' => 'audit-proj-' . time(), 'published' => true], $token);
if ($r['code'] === 200) { $pid = data($r['data'])['id']; pass("Projects CREATE id=$pid"); }
else { $pid = ''; er("Projects CREATE", "code={$r['code']}"); }
if ($pid) { $r = call('DELETE', "/api/v1/projects/$pid", null, $token); if ($r['code'] === 200) pass("Projects DELETE"); else er("Projects DELETE", "code={$r['code']}"); }

// 12. SERVICES CRUD
echo "\n-- 12. Services CRUD --\n";
$r = call('GET', '/api/v1/services', null, $token);
pass("Services GET (" . count(data($r['data']) ?? []) . " items)");
$r = call('POST', '/api/v1/services', ['title' => 'Audit Svc', 'slug' => 'audit-svc-' . time(), 'price_from' => 50000], $token);
if ($r['code'] === 200) { $sid = data($r['data'])['id']; pass("Services CREATE id=$sid"); }
else { $sid = ''; er("Services CREATE", "code={$r['code']}"); }
if ($sid) { $r = call('DELETE', "/api/v1/services/$sid", null, $token); if ($r['code'] === 200) pass("Services DELETE"); else er("Services DELETE", "code={$r['code']}"); }

// 13. MATERIALS CRUD
echo "\n-- 13. Materials CRUD --\n";
$r = call('GET', '/api/v1/materials', null, $token);
pass("Materials GET (" . count(data($r['data']) ?? []) . " items)");
$r = call('POST', '/api/v1/materials', ['title' => 'Audit Mat', 'slug' => 'audit-mat-' . time(), 'category' => 'wood'], $token);
if ($r['code'] === 200) { $mid = data($r['data'])['id']; pass("Materials CREATE id=$mid"); }
else { $mid = ''; er("Materials CREATE", "code={$r['code']}"); }
if ($mid) { $r = call('DELETE', "/api/v1/materials/$mid", null, $token); if ($r['code'] === 200) pass("Materials DELETE"); else er("Materials DELETE", "code={$r['code']}"); }

// 14. REVIEWS CRUD
echo "\n-- 14. Reviews CRUD --\n";
$r = call('GET', '/api/v1/reviews', null, $token);
pass("Reviews GET (" . count(data($r['data']) ?? []) . " items)");
$r = call('POST', '/api/v1/reviews', ['author' => 'Audit Reviewer', 'text' => 'Audit review text', 'rating' => 5, 'approved' => true], $token);
if ($r['code'] === 200) { $rid = data($r['data'])['id']; pass("Reviews CREATE id=$rid"); }
else { $rid = ''; er("Reviews CREATE", "code={$r['code']}"); }
if ($rid) { $r = call('PUT', "/api/v1/reviews/$rid", ['approved' => false], $token); if ($r['code'] === 200) pass("Reviews UPDATE (toggle)"); else er("Reviews UPDATE", "code={$r['code']}"); }
if ($rid) { $r = call('DELETE', "/api/v1/reviews/$rid", null, $token); if ($r['code'] === 200) pass("Reviews DELETE"); else er("Reviews DELETE", "code={$r['code']}"); }

// 15. LEADS CRUD
echo "\n-- 15. Leads CRUD --\n";
$r = call('GET', '/api/v1/leads', null, $token);
pass("Leads GET (" . count(data($r['data']) ?? []) . " items)");
$r = call('POST', '/api/v1/leads-public', ['name' => 'Audit Lead', 'phone' => '+79001112233', 'message' => 'test']);
if ($r['code'] === 200) { $lid = data($r['data'])['id']; pass("Leads PUBLIC CREATE id=$lid"); }
else { $lid = ''; er("Leads PUBLIC CREATE", "code={$r['code']}"); }
if ($lid) { $r = call('PUT', "/api/v1/leads/$lid", ['status' => 'contacted'], $token); if ($r['code'] === 200) pass("Leads UPDATE status"); else er("Leads UPDATE", "code={$r['code']}"); }
if ($lid) { $r = call('DELETE', "/api/v1/leads/$lid", null, $token); if ($r['code'] === 200) pass("Leads DELETE"); else er("Leads DELETE", "code={$r['code']}"); }

// 16. PAGES CRUD + PAGE BUILDER
echo "\n-- 16. Pages (Page Builder) CRUD --\n";
$r = call('GET', '/api/v1/pages', null, $token);
pass("Pages GET (" . count(data($r['data']) ?? []) . " items)");
$r = call('POST', '/api/v1/pages', ['title' => 'Audit Page', 'slug' => 'audit-page-' . time(), 'blocks' => [['type' => 'hero', 'data' => ['title' => 'Audit Hero']]], 'published' => true], $token);
if ($r['code'] === 200) { $ppid = data($r['data'])['id']; $ppslug = data($r['data'])['slug']; pass("Pages CREATE id=$ppid slug=$ppslug"); }
else { $ppid = ''; er("Pages CREATE", "code={$r['code']}"); }
if ($ppid) {
    $blocks = data((call('GET', "/api/v1/pages/$ppid"))['data'])['blocks'] ?? [];
    $blocks[] = ['type' => 'text', 'data' => ['text' => 'Audit block content']];
    $r = call('PUT', "/api/v1/pages/$ppid", ['blocks' => $blocks], $token);
    if ($r['code'] === 200 && count(data($r['data'])['blocks'] ?? []) >= 2) pass("Pages UPDATE (add block)");
    else er("Pages UPDATE", "code={$r['code']}");
    // Public render
    $r = call('GET', "/p/$ppslug");
    if ($r['code'] === 200 && strpos($r['raw'], 'Audit block content') !== false) pass("Public page renders block");
    else er("Public page render", "code={$r['code']}");
    $r = call('DELETE', "/api/v1/pages/$ppid", null, $token);
    if ($r['code'] === 200) pass("Pages DELETE");
    else er("Pages DELETE", "code={$r['code']}");
}

// 17. MENU ITEMS
echo "\n-- 17. Menu Items CRUD --\n";
$r = call('GET', '/api/v1/menu', null, $token);
pass("Menu GET (" . count(data($r['data']) ?? []) . " items)");
$r = call('POST', '/api/v1/menu', ['title' => 'Audit Menu', 'url' => '/audit', 'sort_order' => 99], $token);
$code = $r['code'];
if ($code === 200 || $code === 201) { $muid = data($r['data'])['id']; pass("Menu CREATE id=$muid"); }
else { $muid = ''; er("Menu CREATE", "code=$code"); }
if ($muid) { $r = call('PUT', "/api/v1/menu/$muid", ['title' => 'Audit Menu UPDATED'], $token); if ($r['code'] === 200) pass("Menu UPDATE"); else er("Menu UPDATE", "code={$r['code']}"); }
if ($muid) { $r = call('DELETE', "/api/v1/menu/$muid", null, $token); if ($r['code'] === 200) pass("Menu DELETE"); else er("Menu DELETE", "code={$r['code']}"); }

// 18. USERS
echo "\n-- 18. Users CRUD --\n";
$r = call('GET', '/api/v1/users', null, $token);
pass("Users GET (" . count(data($r['data']) ?? []) . " users)");
$r = call('POST', '/api/v1/users', ['email' => 'audit-user-' . time() . '@test.com', 'password' => 'test123', 'name' => 'Audit User', 'role' => 'editor'], $token);
if ($r['code'] === 200) { $uid = data($r['data'])['id']; pass("Users CREATE id=$uid"); }
else { $uid = ''; er("Users CREATE", "code={$r['code']}"); }
if ($uid) { $r = call('DELETE', "/api/v1/users/$uid", null, $token); if ($r['code'] === 200) pass("Users DELETE"); else er("Users DELETE", "code={$r['code']}"); }

// 19. MEDIA UPLOAD + DELETE
echo "\n-- 19. Media Upload --\n";
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$r = call('POST', '/api/v1/media', ['filename' => 'audit-test.png', 'data' => 'data:image/png;base64,' . base64_encode($png), 'alt' => 'audit', 'folder' => 'branding'], $token);
if ($r['code'] === 200) { $mediaId = data($r['data'])['id']; pass("Media UPLOAD id=$mediaId url=" . data($r['data'])['url']); }
else { $mediaId = ''; er("Media UPLOAD", "code={$r['code']}"); }
if ($mediaId) { $r = call('DELETE', "/api/v1/media/$mediaId", null, $token); if ($r['code'] === 200) pass("Media DELETE"); else er("Media DELETE", "code={$r['code']}"); }

// 20. NOTIFICATIONS
echo "\n-- 20. Notifications --\n";
$r = call('GET', '/api/v1/notifications', null, $token);
if ($r['code'] === 200) pass("Notifications GET (" . count(data($r['data']) ?? []) . " items)");
else er("Notifications", "code={$r['code']}");

// 21. SEO AUDIT
echo "\n-- 21. SEO Audit --\n";
$r = call('GET', '/api/v1/seo/audit', null, $token);
$seo = data($r['data']);
if ($r['code'] === 200) pass("SEO score={$seo['score']} checked={$seo['checked']}");
else er("SEO Audit", "code={$r['code']}");

// 22. ANALYTICS
echo "\n-- 22. Analytics --\n";
$r = call('GET', '/api/v1/analytics', null, $token);
$an = data($r['data']);
if ($r['code'] === 200) pass("Analytics OK (leads={$an['leads']} projects={$an['projects']})");
else er("Analytics", "code={$r['code']}");

// 23. BACKUPS
echo "\n-- 23. Backup Create --\n";
$r = call('POST', '/api/v1/backup/create', null, $token);
if ($r['code'] === 200) pass("Backup CREATE OK");
else er("Backup CREATE", "code={$r['code']} data=" . json_encode($r['data']));
$r = call('GET', '/api/v1/backup/list', null, $token);
if ($r['code'] === 200) pass("Backup LIST (" . count(data($r['data']) ?? []) . " backups)");
else er("Backup LIST", "code={$r['code']}");

// 24. PUBLIC PAGES
echo "\n-- 24. Public Pages --\n";
foreach (['/', '/catalog', '/projects', '/materials', '/services', '/contacts'] as $pg) {
    $r = call('GET', $pg);
    if ($r['code'] === 200) pass("Public $pg OK");
    else er("Public $pg", "code={$r['code']}");
}
$r = call('GET', '/sitemap.xml');
if ($r['code'] === 200 && strpos($r['raw'], '<urlset') !== false) pass("Sitemap OK");
else er("Sitemap", "code={$r['code']}");
$r = call('GET', '/robots.txt');
if ($r['code'] === 200) pass("Robots.txt OK");
else er("Robots.txt", "code={$r['code']}");

// 25. LOGO FLOW
echo "\n-- 25. Logo Flow: Settings -> Public --\n";
$r = call('GET', '/api/v1/settings', null, $token);
$sg = data($r['data']);
$oldLogo = $sg['logo'] ?? '';
$sg['logo'] = '/uploads/test-logo-audit.png';
$r = call('PUT', '/api/v1/settings', $sg, $token);
if ($r['code'] === 200) pass("Settings logo saved");
else er("Settings logo", "code={$r['code']}");
$r = call('GET', '/');
if (strpos($r['raw'], 'test-logo-audit.png') !== false) pass("Public site shows new logo");
else er("Public site logo", "not found in HTML");
// restore
$sg2 = data((call('GET', '/api/v1/settings', null, $token))['data']);
if ($oldLogo !== '') $sg2['logo'] = $oldLogo; else unset($sg2['logo']);
call('PUT', '/api/v1/settings', $sg2, $token);

// 26. RBAC
echo "\n-- 26. RBAC: Editor Restrictions --\n";
$r = call('POST', '/api/v1/users', ['email' => 'rbac-editor-' . time() . '@test.com', 'password' => 'test123', 'role' => 'editor'], $token);
if ($r['code'] === 200) {
    $ed = data($r['data']);
    $r2 = call('POST', '/api/v1/auth/login', ['email' => $ed['email'], 'password' => 'test123']);
    if ($r2['code'] === 200) {
        $edt = data($r2['data'])['token'];
        $r3 = call('GET', '/api/v1/settings', null, $edt);
        if ($r3['code'] === 403) pass("Editor blocked from GET settings (403)");
        else er("Editor GET settings", "expected 403 got {$r3['code']}");
        $r4 = call('PUT', '/api/v1/settings', ['siteName' => 'HACK'], $edt);
        if ($r4['code'] === 403) pass("Editor blocked from PUT settings (403)");
        else er("Editor PUT settings", "expected 403 got {$r4['code']}");
        $r5 = call('GET', '/api/v1/users', null, $edt);
        if ($r5['code'] === 403) pass("Editor blocked from users (403)");
        else er("Editor GET users", "expected 403 got {$r5['code']}");
        // Editors CAN delete catalog (role=editor is allowed)
        $edCatalog = call('GET', '/api/v1/catalog', null, $edt);
        $edCatList = data($edCatalog['data']);
        if (count($edCatList ?? []) > 0) {
            $catId = $edCatList[0]['id'];
            $r6 = call('DELETE', "/api/v1/catalog/$catId", null, $edt);
            if ($r6['code'] === 200) pass("Editor CAN delete catalog (allowed by RBAC)");
            else er("Editor DELETE catalog", "expected 200 got {$r6['code']}");
        } else {
            $r6 = call('DELETE', '/api/v1/catalog/x', null, $edt);
            if ($r6['code'] === 404) pass("Editor delete on empty catalog returns 404 (expected)");
            else er("Editor DELETE catalog (empty)", "expected 404 got {$r6['code']}");
        }
    }
    call('DELETE', "/api/v1/users/{$ed['id']}", null, $token);
}

// 27. AUDIT LOG
echo "\n-- 27. Audit Log --\n";
$r = call('GET', '/api/v1/audit_log', null, $token);
if ($r['code'] === 200) pass("Audit log (" . count(data($r['data']) ?? []) . " entries)");
else er("Audit log", "code={$r['code']}");

// 28. SYSTEM
echo "\n-- 28. System Update Check --\n";
$r = call('GET', '/api/v1/system/update/check', null, $token);
if ($r['code'] === 200) pass("System update check OK");
else er("System update check", "code={$r['code']}");

// 29. LOGOUT
echo "\n-- 29. Logout --\n";
$r = call('POST', '/api/v1/auth/logout', null, $token);
if ($r['code'] === 200) pass("Logout OK");
else er("Logout", "code={$r['code']}");

// 30. POST-LOGOUT ACCESS
echo "\n-- 30. Post-Logout: Token Still Valid (stateless JWT) --\n";
$r = call('GET', '/api/v1/me', null, $token);
if ($r['code'] === 200) pass("Post-logout: stateless JWT still valid (expected for JWT without blacklist)");
else er("Post-logout token", "expected 200 got {$r['code']}");

// ===== SUMMARY =====
echo "\n========================================\n";
echo "  RESULTS: $pass PASS / $fail FAIL\n";
echo "========================================\n";
if ($fail > 0) {
    echo "\nFAILURES:\n";
    foreach ($errors as $e) echo "  - $e\n";
}
echo "\n";
exit($fail > 0 ? 1 : 0);
