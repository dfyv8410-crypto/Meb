<?php
/**
 * Public review submission — no auth required.
 * Reviews go to pending status for admin moderation.
 */

declare(strict_types=1);

function handle_review_public(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail('Not found', 404);

    // Rate limit: max 3 reviews per IP per 10 minutes.
    // rate_limits has PRIMARY KEY (bucket, ip, window_ts), so the upsert
    // increments the count column; we must read count, not COUNT(*) rows
    // (a COUNT(*) would stay 1 and the limit would never trip).
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $window = (int) (time() / 600) * 600;
    db()->prepare("INSERT INTO rate_limits (bucket,ip,window_ts,count) VALUES ('review',?,?,1)
                   ON DUPLICATE KEY UPDATE count=count+1")
       ->execute([$ip, $window]);
    $st2 = db()->prepare("SELECT count FROM rate_limits WHERE bucket='review' AND ip=? AND window_ts=?");
    $st2->execute([$ip, $window]);
    if ((int) $st2->fetchColumn() > 3) {
        fail('Слишком много отзывов. Попробуйте позже.', 429);
    }

    $b = read_body();
    $name   = trim((string) ($b['name'] ?? ''));
    $text   = trim((string) ($b['text'] ?? ''));
    $rating = (int) ($b['rating'] ?? 5);

    if ($name === '' || $text === '') fail('Имя и текст отзыва обязательны', 400);
    if (mb_strlen($name) > 120) fail('Имя слишком длинное', 400);
    if (mb_strlen($text) > 2000) fail('Отзыв слишком длинный (макс. 2000 символов)', 400);
    if ($rating < 1 || $rating > 5) fail('Оценка от 1 до 5', 400);

    // Sanitize — strip all HTML tags
    $name = strip_tags($name);
    $text = strip_tags($text);

    $id = new_id();
    $st = db()->prepare(
        'INSERT INTO reviews (id,author,text,rating,approved,created_at,updated_at)
         VALUES (?,?,?,?,"0",?,?)'
    );
    $st->execute([$id, sanitize($name), sanitize($text), $rating, now_db(), now_db()]);

    // Notification for admin
    $noteBody = "Отзыв от " . sanitize($name) . " ({$rating}/5): " . mb_substr(sanitize($text), 0, 100);
    db()->prepare(
        'INSERT INTO notifications (id,type,title,body,meta,`read`,created_at,updated_at)
         VALUES (?,?,?,?,?,0,?,?)'
    )->execute([new_id(), 'review', 'Новый отзыв: ' . $name, $noteBody, null, now_db(), now_db()]);

    audit_log('create', 'reviews', $id);
    ok(['id' => $id, 'status' => 'pending']);
}
