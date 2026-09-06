<?php
/**
 * Analytics summary (any authenticated user).
 * Pageview tracking happens in index.php front controller (see public/index.php).
 */

declare(strict_types=1);

function handle_analytics(): void
{
    $u = current_user();
    if (!$u) fail('Unauthorized', 401);

    $cnt = function (string $t): int {
        return (int) db()->query('SELECT COUNT(*) FROM `' . safe_ident($t) . '`')->fetchColumn();
    };
    $summary = [
        'leads'    => $cnt('requests'),
        'projects' => $cnt('projects'),
        'catalog'  => $cnt('catalog'),
        'reviews'  => $cnt('reviews'),
        'users'    => $cnt('users'),
    ];

    // views: last 14 days
    $days = [];
    for ($i = 13; $i >= 0; $i--) {
        $date = gmdate('Y-m-d', time() - $i * 86400);
        $st = db()->prepare('SELECT views FROM analytics WHERE date = ?');
        $st->execute([$date]);
        $v = (int) ($st->fetchColumn() ?: 0);
        $days[] = ['date' => $date, 'views' => $v];
    }
    $total = (int) db()->query('SELECT COALESCE(SUM(views),0) FROM analytics')->fetchColumn();
    $today = $days[count($days) - 1]['views'];

    ok(['leads'=>$summary['leads'],'projects'=>$summary['projects'],'catalog'=>$summary['catalog'],
        'reviews'=>$summary['reviews'],'users'=>$summary['users'],'views'=>['total'=>$total,'today'=>$today,'days'=>$days]]);
}
