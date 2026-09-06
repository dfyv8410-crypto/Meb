<?php
/** robots.txt */
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Disallow: /admin\n";
echo "Disallow: /installer\n";
echo "Disallow: /api/\n";
echo "Disallow: /uploads/backups\n";
// Absolute sitemap URL with a scheme derived from the request (never hardcode
// https, and never emit a scheme-less / relative URL).
$base = meb_origin();
echo "\nSitemap: " . $base . "/sitemap.xml\n";
